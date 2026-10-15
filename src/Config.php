<?php


namespace EasySwoole\Crontab;


use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    protected string $serverName = "EasySwoole";
    protected string $tempDir = '';
    /** @var callable|null */
    protected mixed $onException = null;

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
     * @return string
     */
    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    /**
     * @param string $tempDir
     */
    public function setTempDir(string $tempDir): void
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
