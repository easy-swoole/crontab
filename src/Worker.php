<?php


namespace EasySwoole\Crontab;


use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use Swoole\Coroutine\Socket;
use Swoole\Table;

class Worker extends AbstractUnixProcess
{
    /** @var Crontab */
    private $crontabInstance;
    /** @var Table */
    private $workerStatisticTable;


    public function run($arg)
    {
        $this->crontabInstance = $arg['crontabInstance'];
        $this->workerStatisticTable = $arg['workerStatisticTable'];
        $this->workerStatisticTable->set($arg['workerIndex'],[
            'runningNum'=>0
        ]);
        parent::run($arg);
    }

    function onAccept(Socket $socket)
    {

    }
}