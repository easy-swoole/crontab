<?php


namespace EasySwoole\Crontab\Protocol;


use EasySwoole\Spl\SplBean;

class Command extends SplBean
{
    const COMMAND_EXEC_JOB = 0x1;

    protected ?int $command = null;
    protected mixed $arg = null;

    /**
     * @return int|null
     */
    public function getCommand(): ?int
    {
        return $this->command;
    }

    /**
     * @param int $command
     */
    public function setCommand(int $command): void
    {
        $this->command = $command;
    }

    /**
     * @return mixed
     */
    public function getArg(): mixed
    {
        return $this->arg;
    }

    /**
     * @param mixed $arg
     */
    public function setArg(mixed $arg): void
    {
        $this->arg = $arg;
    }
}
