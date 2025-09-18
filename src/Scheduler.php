<?php


namespace EasySwoole\Crontab;


use Cron\CronExpression;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use EasySwoole\Crontab\Protocol\Command;
use Swoole\Table;

class Scheduler extends AbstractProcess
{
    /** @var Table */
    private $schedulerTable;
    private array $sockFileMap;

    private $timerIds = [];

    protected function run($arg)
    {
        $this->sockFileMap = $arg['sockFileMap'];
        $this->schedulerTable = $arg['schedulerTable'];
        //异常的时候，worker会退出。先清空一遍规则,禁止循环的时候删除key
        $keys = [];
        foreach ($this->schedulerTable as $key => $value) {
            $keys[] = $key;
        }
        foreach ($keys as $key) {
            $this->schedulerTable->del($key);
        }

        $jobs = $arg['jobs'];
        /**
         * @var  $jobName
         * @var JobInterface $job
         */
        foreach ($jobs as $jobName => $job) {
            $nextTime = CronExpression::factory($job->crontabRule())->getNextRunDate()->getTimestamp();
            $this->schedulerTable->set($jobName, [
                'taskName'=>$jobName,
                'taskRule' => $job->crontabRule(),
                'taskRunTimes' => 0,
                'taskNextRunTime' => $nextTime,
                'taskCurrentRunTime' => 0,
                'isStop' => 0
            ]);
        }
        $this->cronProcess();
        //60无法被7整除。
        Timer::getInstance()->loop(7 * 1000, function () {
            $this->cronProcess();
        });
    }

    private function cronProcess()
    {
        foreach ($this->schedulerTable as $jobName => $task) {
            if (intval($task['isStop']) == 1) {
                // 删除已添加的定时器
                if(isset($this->timerIds[$jobName])){
                    $timerId = $this->timerIds[$jobName];
                    Timer::getInstance()->clear($timerId);
                    unset($this->timerIds[$jobName]);
                }
                continue;
            }
            $nextRunTime = CronExpression::factory($task['taskRule'])->getNextRunDate()->getTimestamp();
            if ($task['taskNextRunTime'] != $nextRunTime) {
                $this->schedulerTable->set($jobName, ['taskNextRunTime' => $nextRunTime]);
            }

            //本轮已经创建过任务
            if (isset($this->timerIds[$jobName])) {
                continue;
            }

            $distanceTime = $nextRunTime - time();
            $timerId = Timer::getInstance()->after($distanceTime * 1000, function () use ($jobName) {
                $taskInfo = $this->schedulerTable->get($jobName);
                if (intval($taskInfo['isStop']) == 1) {
                    unset($this->timerIds[$jobName]);
                    return;
                }
                unset($this->timerIds[$jobName]);
                if(isset($this->sockFileMap[$jobName])){
                    $sockFile = $this->sockFileMap[$jobName];
                }else{
                    $sockFile = $this->sockFileMap['normal'];
                }
                $request = new Command();
                $request->setCommand(Command::COMMAND_EXEC_JOB);
                $request->setArg($jobName);
                Crontab::sendToWorker($request,$sockFile);
            });
            if ($timerId) {
                $this->timerIds[$jobName] = $timerId;
            }
        }
    }
}