<?php


namespace EasySwoole\Crontab\Protocol;


use EasySwoole\Spl\SplBean;

class Command extends SplBean
{
    const COMMAND_EXEC_JOB = 0x1;

    const COMMAND_STOP_JOB = 0x2;
    const COMMAND_STOP_ALL_JOB = 0x3;

    const COMMAND_START_JOB = 0x4;
    const COMMAND_START_ALL_JOB = 0x5;

    const COMMAND_RESET_JOB = 0x6;

    protected $command;
    protected $arg;

    /**
     * @return mixed
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param mixed $command
     */
    public function setCommand($command): void
    {
        $this->command = $command;
    }

    /**
     * @return mixed
     */
    public function getArg()
    {
        return $this->arg;
    }

    /**
     * @param mixed $arg
     */
    public function setArg($arg): void
    {
        $this->arg = $arg;
    }
}