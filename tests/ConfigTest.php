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
    }

    public function testValuesCanBeConfigured(): void
    {
        $callback = static function (\Throwable $throwable): void {
        };
        $config = new Config();

        $config->setServerName('TestServer');
        $config->setTempDir('/tmp/crontab-tests');
        $config->setOnException($callback);

        self::assertSame('TestServer', $config->getServerName());
        self::assertSame('/tmp/crontab-tests', $config->getTempDir());
        self::assertSame($callback, $config->getOnException());
    }
}
