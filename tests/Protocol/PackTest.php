<?php

declare(strict_types=1);

namespace EasySwoole\Crontab\Tests\Protocol;

use EasySwoole\Crontab\Protocol\Pack;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PackTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function payloadProvider(): iterable
    {
        yield 'empty payload' => [''];
        yield 'plain text' => ['crontab'];
        yield 'binary data' => ["\x00\x01\xff"];
        yield 'multibyte text' => ['定时任务'];
    }

    #[DataProvider('payloadProvider')]
    public function testPayloadCanBePackedAndUnpacked(string $payload): void
    {
        $packet = Pack::pack($payload);

        self::assertSame(strlen($payload), Pack::packDataLength(substr($packet, 0, 4)));
        self::assertSame($payload, Pack::unpack($packet));
        self::assertSame(strlen($payload) + 4, strlen($packet));
    }
}
