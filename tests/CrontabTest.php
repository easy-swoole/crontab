<?php

declare(strict_types=1);

namespace EasySwoole\Crontab\Tests;

use EasySwoole\Crontab\Crontab;
use EasySwoole\Crontab\Exception\Exception;
use EasySwoole\Crontab\JobInterface;
use EasySwoole\Crontab\PrivilegeJobInterface;
use EasySwoole\Crontab\Tests\Jobs\JobPerMin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CrontabTest extends TestCase
{
    public static function invalidPrivilegedJobNames(): iterable
    {
        foreach (['', '../task', 'path/task', 'path\\task', "task\0name", "task\n", 'task name', 'task.name', 'normal'] as $name) {
            yield [$name];
        }
    }

    #[DataProvider('invalidPrivilegedJobNames')]
    public function testInvalidPrivilegedJobNameIsRejected(string $name): void
    {
        $job = $this->createMock(PrivilegeJobInterface::class);
        $job->method('jobName')->willReturn($name);
        $job->method('crontabRule')->willReturn('* * * * *');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('privileged job name');

        (new Crontab())->register($job);
    }

    public function testValidPrivilegedJobNameCanBeRegistered(): void
    {
        $job = $this->createMock(PrivilegeJobInterface::class);
        $job->method('jobName')->willReturn('Task_01-backup');
        $job->method('crontabRule')->willReturn('* * * * *');
        $crontab = new Crontab();

        self::assertSame($crontab, $crontab->register($job));
    }

    public function testNormalJobNameIsNotRestrictedToSocketSafeCharacters(): void
    {
        $crontab = new Crontab();

        self::assertSame($crontab, $crontab->register($this->createJob('App\\Jobs\\Backup', '* * * * *')));
    }

    public function testJobCanBeRegistered(): void
    {
        $crontab = new Crontab();

        self::assertSame($crontab, $crontab->register(new JobPerMin()));
    }

    public function testDuplicateJobIsRejected(): void
    {
        $crontab = new Crontab();
        $crontab->register(new JobPerMin());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('JobPerMin hash been register');

        $crontab->register(new JobPerMin());
    }

    public function testInvalidCronRuleIsRejected(): void
    {
        $crontab = new Crontab();
        $job = $this->createJob('InvalidJob', 'not a cron rule');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('crontab rule for InvalidJob is invalid');

        $crontab->register($job);
    }

    public function testJobNameAtMaximumLengthCanBeRegistered(): void
    {
        $crontab = new Crontab();

        self::assertSame(
            $crontab,
            $crontab->register($this->createJob(str_repeat('a', 128), '* * * * *'))
        );
    }

    public function testJobNameExceedingMaximumLengthIsRejected(): void
    {
        $crontab = new Crontab();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('crontab job name length cannot exceed 128');

        $crontab->register($this->createJob(str_repeat('a', 129), '* * * * *'));
    }

    public function testJobCanBeStoppedResumedAndReset(): void
    {
        $crontab = new Crontab();
        $crontab->register(new JobPerMin());

        self::assertTrue($crontab->stop('JobPerMin'));
        self::assertSame(1, $crontab->schedulerTable()->get('JobPerMin', 'isStop'));

        self::assertTrue($crontab->resume('JobPerMin'));
        self::assertSame(0, $crontab->schedulerTable()->get('JobPerMin', 'isStop'));

        self::assertTrue($crontab->resetJobRule('JobPerMin', '*/5 * * * *'));
        self::assertSame('*/5 * * * *', $crontab->schedulerTable()->get('JobPerMin', 'taskRule'));
        self::assertSame(1, $crontab->schedulerTable()->get('JobPerMin', 'needRestJobTimer'));
    }

    public function testOperationsOnUnknownJobReturnFalse(): void
    {
        $crontab = new Crontab();

        self::assertFalse($crontab->stop('missing'));
        self::assertFalse($crontab->resume('missing'));
        self::assertFalse($crontab->resetJobRule('missing', '* * * * *'));
    }

    public function testResetRejectsInvalidCronRule(): void
    {
        $crontab = new Crontab();
        $crontab->register(new JobPerMin());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('crontab rule for JobPerMin is invalid');

        $crontab->resetJobRule('JobPerMin', 'invalid');
    }

    public function testRightNowRejectsUnknownJobBeforeOpeningSocket(): void
    {
        $crontab = new Crontab();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('crontab job missing not exist');

        $crontab->rightNow('missing');
    }

    private function createJob(string $name, string $rule): JobInterface
    {
        return new class ($name, $rule) implements JobInterface {
            public function __construct(
                private readonly string $name,
                private readonly string $rule
            ) {
            }

            public function jobName(): string
            {
                return $this->name;
            }

            public function crontabRule(): string
            {
                return $this->rule;
            }

            public function run(): mixed
            {
                return null;
            }

            public function onException(\Throwable $throwable): void
            {
                throw $throwable;
            }
        };
    }
}
