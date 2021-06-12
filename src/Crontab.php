<?php


namespace EasySwoole\Crontab;


use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use EasySwoole\Component\Singleton;
use EasySwoole\Crontab\Exception\Exception;
use Swoole\Server;
use Swoole\Table;
use EasySwoole\Component\Process\Config as ProcessConfig;

class Crontab
{
    use Singleton;

    private $scheduleTable;
    private $workerStatisticTable;
    private $jobs = [];
    /** @var Config */
    private $config;
    function __construct(?Config $config = null)
    {
        if($config == null){
            $config = new Config();
        }
        $this->config = $config;
        $this->scheduleTable = new Table(1024);
        $this->scheduleTable->column('taskRule', Table::TYPE_STRING, 35);
        $this->scheduleTable->column('taskRunTimes', Table::TYPE_INT, 8);
        $this->scheduleTable->column('taskNextRunTime', Table::TYPE_INT, 10);
        $this->scheduleTable->column('taskCurrentRunTime', Table::TYPE_INT, 10);
        $this->scheduleTable->column('isStop', Table::TYPE_INT, 1);
        $this->scheduleTable->create();

        $this->workerStatisticTable = new Table(1024);
        $this->scheduleTable->column('runningNum', Table::TYPE_INT, 8);
        $this->workerStatisticTable->create();
    }

    function getConfig():Config
    {
        return $this->config;
    }

    public function register(JobInterface $job):Crontab
    {
        if(!isset($this->jobs[$job->jobName()])){
            $this->jobs[$job->jobName()] = $job;
            return $this;
        }else{
            throw new Exception("{$job->jobName()} hash been register");
        }
    }

    public function __attachServer(Server $server)
    {
        $c = new ProcessConfig();
        $c->setEnableCoroutine(true);
        $c->setProcessName("{$this->config->getServerName()}.CrontabScheduler");
        $c->setProcessGroup("EasySwoole.Crontab");
        $c->setArg([
            'jobs'=>$this->jobs,
            'scheduleTable'=>$this->scheduleTable,
            'crontabInstance'=>$this
        ]);
        $server->addProcess((new Scheduler($c))->getProcess());

        for($i = 0;$i < $this->config->getWorkerNum();$i++)
        {
            $c = new UnixProcessConfig();
            $c->setEnableCoroutine(true);
            $c->setProcessName("{$this->config->getServerName()}.CrontabWorker.{$i}");
            $c->setProcessGroup("EasySwoole.Crontab");
            $c->setArg([
                'jobs'=>$this->jobs,
                'scheduleTable'=>$this->scheduleTable,
                'workerStatisticTable'=>$this->workerStatisticTable,
                'crontabInstance'=>$this
            ]);
            $c->setSocketFile($this->indexToSockFile($i));
            $server->addProcess((new Worker($c))->getProcess());
        }
    }

    private function indexToSockFile(int $index):string
    {
        return $this->config->getTempDir()."/{$this->config->getServerName()}.CrontabWorker.{$index}.sock";
    }

}