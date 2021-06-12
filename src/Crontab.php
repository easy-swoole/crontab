<?php


namespace EasySwoole\Crontab;


use EasySwoole\Component\Singleton;
use Swoole\Table;

class Crontab
{
    use Singleton;

    private $scheduleTable;
    private $workerStatisticTable;

    function __construct()
    {
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


}