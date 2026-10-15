<?php

namespace EasySwoole\Crontab\Tests\Jobs;

use EasySwoole\Crontab\PrivilegeJobInterface;

class JobPerMinWithPrivilege implements PrivilegeJobInterface
{

    public function jobName(): string
    {
        return 'JobPerMinWithPrivilege';
    }

    public function crontabRule(): string
    {
        return '*/1 * * * *';
    }

    public function run(): void
    {
    }

    public function onException(\Throwable $throwable): void
    {
        throw $throwable;
    }
}
