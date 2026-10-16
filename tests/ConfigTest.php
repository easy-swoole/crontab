<?php

declare(strict_types=1);

namespace EasySwoole\Crontab\Tests;

use EasySwoole\Crontab\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaultsAreInitialized(): void
    {
        $config = new Config();

        self::assertSame('EasySwoole', $config->getServerName());
        self::assertSame(getcwd(), $config->getTempDir());
        self::assertNull($config->getOnException());
        self::assertNull($config->getOnWorkerStart());
    }

    public function testValuesCanBeConfigured(): void
    {
        $callback = static function (\Throwable $throwable): void {
        };
        $onWorkerStart = static function (): void {
        };
        $config = new Config();

        $config->setServerName('TestServer');
        $config->setTempDir('/tmp/crontab-tests');
        $config->setOnException($callback);
        $config->setOnWorkerStart($onWorkerStart);

        self::assertSame('TestServer', $config->getServerName());
        self::assertSame('/tmp/crontab-tests', $config->getTempDir());
        self::assertSame($callback, $config->getOnException());
        self::assertSame($onWorkerStart, $config->getOnWorkerStart());
    }
}
