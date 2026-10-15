<?php


namespace EasySwoole\Crontab\Protocol;


use EasySwoole\Spl\SplBean;

class Response extends SplBean
{
    const STATUS_OK = 0;

    const STATUS_PACKAGE_READ_TIMEOUT = 101;
    const STATUS_ILLEGAL_PACKAGE = 102;

    const STATUS_UNKNOWN_COMMAND = 201;

    const STATUS_JOB_NOT_EXIST = 301;
    const STATUS_JOB_EXEC_ERROR = 302;

    private static array $phrases = [
        self::STATUS_OK => 'OK',
        self::STATUS_PACKAGE_READ_TIMEOUT => 'crontab: recv unix socket timeout',
        self::STATUS_ILLEGAL_PACKAGE => 'crontab: unserialize response as an Response instance fail',
        self::STATUS_UNKNOWN_COMMAND => 'crontab: unknown command',
        self::STATUS_JOB_NOT_EXIST => 'crontab: job not exist',
        self::STATUS_JOB_EXEC_ERROR => 'crontab: job exec error'
    ];

    protected ?int $status = null;
    protected mixed $result = null;
    protected ?string $msg = null;

    /**
     * @return int|null
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status): Response
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * @param mixed $result
     */
    public function setResult(mixed $result): void
    {
        $this->result = $result;
    }

    /**
     * @return mixed
     */
    public function getMsg(): ?string
    {
        return $this->msg;
    }

    /**
     * @param string $msg
     */
    public function setMsg(string $msg): Response
    {
        $this->msg = $msg;
        return $this;
    }

    public static function getReasonPhrase(int $statusCode): ?string
    {
        return self::$phrases[$statusCode] ?? '';
    }
}
