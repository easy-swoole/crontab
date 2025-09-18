<?php


namespace EasySwoole\Crontab;


use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    protected $serverName = "EasySwoole";
    protected $tempDir;
    /** @var callable|null */
    protected $onException;

    /**
     * @return string
     */
    public function getServerName(): string
    {
        return $this->serverName;
    }

    /**
     * @param string $serverName
     */
    public function setServerName(string $serverName): void
    {
        $this->serverName = $serverName;
    }

    /**
     * @return int
     */

    /**
     * @return mixed
     */
    public function getTempDir()
    {
        return $this->tempDir;
    }

    /**
     * @param mixed $tempDir
     */
    public function setTempDir($tempDir): void
    {
        $this->tempDir = $tempDir;
    }

    /**
     * @return callable|null
     */
    public function getOnException(): ?callable
    {
        return $this->onException;
    }

    /**
     * @param callable|null $onException
     */
    public function setOnException(?callable $onException): void
    {
        $this->onException = $onException;
    }

    protected function initialize(): void
    {
        if (empty($this->tempDir)) {
            $this->tempDir = getcwd();
        }
    }

}