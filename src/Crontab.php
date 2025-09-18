<?php


namespace EasySwoole\Crontab;


use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use EasySwoole\Crontab\Exception\Exception;
use EasySwoole\Crontab\Protocol\Command;
use EasySwoole\Crontab\Protocol\Pack;
use EasySwoole\Crontab\Protocol\Response;
use EasySwoole\Crontab\Protocol\UnixClient;
use Swoole\Server;
use Swoole\Table;
use EasySwoole\Component\Process\Config as ProcessConfig;

class Crontab
{
    private $schedulerTable;
    private $jobs = [];
    /** @var Config */
    private $config;
    private $hasAttach = false;

    function __construct(?Config $config = null)
    {
        if ($config == null) {
            $config = new Config();
        }
        $this->config = $config;
        $this->schedulerTable = new Table(2048);
        $this->schedulerTable->column('taskRule', Table::TYPE_STRING, 35);
        $this->schedulerTable->column('taskRunTimes', Table::TYPE_INT, 8);
        $this->schedulerTable->column('taskNextRunTime', Table::TYPE_INT, 10);
        $this->schedulerTable->column('taskCurrentRunTime', Table::TYPE_INT, 10);
        $this->schedulerTable->column('isStop', Table::TYPE_INT, 1);
        $this->schedulerTable->create();
    }

    function getConfig(): Config
    {
        return $this->config;
    }

    public function register(JobInterface $job): Crontab
    {
        if (!isset($this->jobs[$job->jobName()])) {
            $this->jobs[$job->jobName()] = $job;
            return $this;
        } else {
            throw new Exception("{$job->jobName()} hash been register");
        }
    }

    public function attachToServer(Server $server)
    {
        if (empty($this->jobs)) {
            return;
        }

        if($this->hasAttach){
            return;
        }

        $this->hasAttach = true;

        //创建调度进程
        $c = new ProcessConfig();
        $c->setEnableCoroutine(true);
        $c->setProcessName("{$this->config->getServerName()}.CrontabScheduler");
        $c->setProcessGroup("{$this->config->getServerName()}.Crontab");
        $sockFileMap = [];
        $sockFileMap['normal'] = $this->indexToSockFile();
        foreach ($this->jobs as $job) {
            if($job instanceof PrivilegeJobInterface){
                $sockFileMap[$job->jobName()] = $this->indexToSockFile($job->jobName());
            }
        }
        $c->setArg([
            'jobs' => $this->jobs,
            'schedulerTable' => $this->schedulerTable,
            'sockFileMap' => $sockFileMap
        ]);
        $server->addProcess((new Scheduler($c))->getProcess());

        //创建常规任务worker
        $c = new UnixProcessConfig();
        $c->setEnableCoroutine(true);
        $c->setProcessName("{$this->config->getServerName()}.CrontabWorker");
        $c->setProcessGroup("{$this->config->getServerName()}.Crontab");
        $c->setArg([
            'jobs' => $this->jobs,
            'schedulerTable' => $this->schedulerTable,
            'onException'=>$this->config->getOnException()
        ]);
        $c->setSocketFile($this->indexToSockFile());
        $server->addProcess((new Worker($c))->getProcess());

        //创建特权进程
        foreach ($this->jobs as $name => $job){
            if($job instanceof PrivilegeJobInterface){
                $c = new UnixProcessConfig();
                $c->setEnableCoroutine(true);
                $c->setProcessName("{$this->config->getServerName()}.CrontabWorker.{$name}");
                $c->setProcessGroup("{$this->config->getServerName()}.Crontab");
                $c->setArg([
                    'jobs' => $this->jobs,
                    'schedulerTable' => $this->schedulerTable,
                    'onException'=>$this->config->getOnException()
                ]);
                $c->setSocketFile($this->indexToSockFile($name));
                $server->addProcess((new Worker($c))->getProcess());
            }
        }
    }

    public function rightNow(string $jobName): ?Response
    {
        if(!isset($this->jobs[$jobName])){
            throw new Exception("crontab job {$jobName} not exist");
        }
        $request = new Command();
        $request->setCommand(Command::COMMAND_EXEC_JOB);
        $request->setArg($jobName);
        if($this->jobs[$jobName] instanceof PrivilegeJobInterface){
            $sockFile = $this->indexToSockFile($jobName);
        }else{
            $sockFile = $this->indexToSockFile();
        }
        return static::sendToWorker($request,$sockFile);

    }

    public function stop(string $jobName): bool
    {
        if (isset($this->jobs[$jobName])) {
            $this->schedulerTable->set($jobName, ['isStop' => 1]);
            return true;
        } else {
            return false;
        }
    }

    public function stopAll(): bool
    {
        foreach ($this->schedulerTable as $key => $item) {
            $this->schedulerTable->set($key, ['isStop' => 1]);
        }
        return true;
    }

    public function resume(string $jobName): bool
    {
        if (isset($this->jobs[$jobName])) {
            $this->schedulerTable->set($jobName, ['isStop' => 0]);
            return true;
        } else {
            return false;
        }
    }

    public function resumeAll(): bool
    {
        foreach ($this->schedulerTable as $key => $item) {
            $this->schedulerTable->set($key, ['isStop' => 0]);
        }
        return true;
    }

    function resetJobRule($jobName, $taskRule): bool
    {
        if (isset($this->jobs[$jobName])) {
            $this->schedulerTable->set($jobName, ['taskRule' => $taskRule]);
            return true;
        } else {
            return false;
        }
    }

    function schedulerTable(): Table
    {
        return $this->schedulerTable;
    }

    private function indexToSockFile(string $index = 'normal'): string
    {
        return $this->config->getTempDir() . "/{$this->config->getServerName()}.CrontabWorker.{$index}.sock";
    }

    public static function sendToWorker(Command $command, string $sockFile): ?Response
    {
        $data = Pack::pack(serialize($command));
        $client = new UnixClient($sockFile, 10 * 1024 * 1024);
        $client->send($data);
        $data = $client->recv(3);
        if ($data) {
            $data = Pack::unpack($data);
            $data = unserialize($data);
            if ($data instanceof Response) {
                return $data;
            } else {
                return (new Response())->setStatus(Response::STATUS_ILLEGAL_PACKAGE)->setMsg('unserialize response as an Response instance fail');
            }
        } else {
            return (new Response())->setStatus(Response::STATUS_PACKAGE_READ_TIMEOUT)->setMsg('recv timeout from worker');
        }
    }

}