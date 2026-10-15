<?php

declare(strict_types=1);

namespace EasySwoole\Crontab\Tests\Jobs;

use EasySwoole\Crontab\JobInterface;

final class ProcessIdJob implements JobInterface
{
    public function jobName(): string
    {
        return 'ProcessIdJob';
    }

    public function crontabRule(): string
    {
        return '* * * * *';
    }

    public function run(): int
    {
        return getmypid();
    }

    public function onException(\Throwable $throwable): void
    {
        throw $throwable;
    }
}
