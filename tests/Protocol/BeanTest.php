<?php

declare(strict_types=1);

namespace EasySwoole\Crontab\Tests\Protocol;

use EasySwoole\Crontab\Protocol\Command;
use EasySwoole\Crontab\Protocol\Response;
use PHPUnit\Framework\TestCase;

final class BeanTest extends TestCase
{
    public function testCommandStoresCommandAndArgument(): void
    {
        $command = new Command();
        $argument = ['job' => 'cleanup'];

        $command->setCommand(Command::COMMAND_EXEC_JOB);
        $command->setArg($argument);

        self::assertSame(Command::COMMAND_EXEC_JOB, $command->getCommand());
        self::assertSame($argument, $command->getArg());
    }

    public function testResponseStoresValuesAndSupportsFluentSetters(): void
    {
        $response = new Response();
        $result = ['executed' => true];

        self::assertSame($response, $response->setStatus(Response::STATUS_OK));
        self::assertSame($response, $response->setMsg('done'));
        $response->setResult($result);

        self::assertSame(Response::STATUS_OK, $response->getStatus());
        self::assertSame('done', $response->getMsg());
        self::assertSame($result, $response->getResult());
    }

    public function testResponseSurvivesSerializationRoundTrip(): void
    {
        $response = (new Response())
            ->setStatus(Response::STATUS_JOB_NOT_EXIST)
            ->setMsg('missing');
        $response->setResult(42);

        $restored = unserialize(serialize($response));

        self::assertInstanceOf(Response::class, $restored);
        self::assertSame(Response::STATUS_JOB_NOT_EXIST, $restored->getStatus());
        self::assertSame('missing', $restored->getMsg());
        self::assertSame(42, $restored->getResult());
    }

    public function testReasonPhraseLookup(): void
    {
        self::assertSame('OK', Response::getReasonPhrase(Response::STATUS_OK));
        self::assertSame('', Response::getReasonPhrase(PHP_INT_MAX));
    }
}
