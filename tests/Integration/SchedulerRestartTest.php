<?php

declare(strict_types=1);

namespace EasySwoole\Crontab\Tests\Integration;

use Cron\CronExpression;
use EasySwoole\Crontab\Crontab;
use EasySwoole\Crontab\JobInterface;
use EasySwoole\Crontab\Scheduler;
use PHPUnit\Framework\TestCase;
use Swoole\Process;

final class SchedulerRestartTest extends TestCase
{
    public function testRestartPreservesUpdatedRuleAndStoppedState(): void
    {
        if (!extension_loaded('swoole') || !defined('SIGTERM')) {
            self::markTestSkipped('Swoole and SIGTERM are required.');
        }

        $crontab = new Crontab();
        $jobName = 'ReviewHeartbeat';
        $job = $this->createStub(JobInterface::class);
        $job->method('jobName')->willReturn($jobName);
        $job->method('crontabRule')->willReturn('0 0 1 1 *');
        $crontab->register($job);
        $table = $crontab->schedulerTable();
        $args = [
            'jobs' => [$jobName => $job],
            'schedulerTable' => $table,
            'sockFileMap' => [],
        ];

        $this->withScheduler($args, function () use ($crontab, $table, $job, $jobName): void {
            self::assertSame($job->crontabRule(), $table->get($jobName, 'taskRule'));
            self::assertSame(0, $table->get($jobName, 'isStop'));
            self::assertTrue($crontab->resetJobRule($jobName, '5 0 1 1 *'));
            self::assertTrue($crontab->stop($jobName));
            self::assertSame(0, $table->get($jobName, 'taskNextRunTime'));
        });

        $this->withScheduler($args, function () use ($table, $jobName): void {
            self::assertSame('5 0 1 1 *', $table->get($jobName, 'taskRule'));
            self::assertSame(1, $table->get($jobName, 'isStop'));
            self::assertSame(0, $table->get($jobName, 'taskNextRunTime'));
        });

        self::assertTrue($crontab->resume($jobName));
        $this->withScheduler($args, function () use ($table, $jobName): void {
            $expected = (new CronExpression('5 0 1 1 *'))->getNextRunDate()->getTimestamp();
            self::assertSame(0, $table->get($jobName, 'isStop'));
            self::assertSame($expected, $table->get($jobName, 'taskNextRunTime'));
        });
    }

    private function withScheduler(array $args, callable $check): void
    {
        $scheduler = new class('Test.CrontabScheduler', $args) extends Scheduler {
            protected function run(mixed $arg): void
            {
                parent::run($arg);
                $this->getProcess()->write('ready');
            }
        };

        $process = $scheduler->getProcess();
        $pid = $process->start();
        self::assertIsInt($pid);

        try {
            $process->setTimeout(3);
            self::assertSame('ready', $process->read());
            $check();
        } finally {
            Process::kill($pid, SIGTERM);
            Process::wait();
        }
    }
}
