<?php


namespace EasySwoole\Crontab;


interface JobInterface
{
    public function jobName(): string;

    public function crontabRule(): string;

    public function run(): mixed;

    public function onException(\Throwable $throwable): void;
}
