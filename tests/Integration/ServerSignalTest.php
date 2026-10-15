<?php

declare(strict_types=1);

namespace EasySwoole\Crontab\Tests\Integration;

use EasySwoole\Crontab\Protocol\Response;
use PHPUnit\Framework\TestCase;

final class ServerSignalTest extends TestCase
{
    /** @var resource|null */
    private mixed $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private ?string $runtimeDir = null;

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status['running']) {
                proc_terminate($this->process, SIGKILL);
                $this->waitForProcessExit(3.0);
            }
            proc_close($this->process);
        }

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if ($this->runtimeDir !== null) {
            $this->removeDirectory($this->runtimeDir);
        }
    }

    public function testServerAndCrontabProcessesExitOnSigterm(): void
    {
        $this->requireRuntimeCapabilities();

        // Unix domain socket paths are limited to roughly 104 bytes on macOS.
        $this->runtimeDir = '/tmp/ct-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->runtimeDir, 0700));
        $port = $this->reserveTcpPort();

        $command = [
            PHP_BINARY,
            dirname(__DIR__) . '/Fixtures/server.php',
            $this->runtimeDir,
            (string) $port,
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->process = proc_open($command, $descriptors, $this->pipes, dirname(__DIR__, 2));
        self::assertIsResource($this->process);
        $serverPid = proc_get_status($this->process)['pid'];
        self::assertIsInt($serverPid);
        self::assertGreaterThan(0, $serverPid);
        fclose($this->pipes[0]);
        unset($this->pipes[0]);
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        $readyFile = $this->runtimeDir . '/ready.json';
        $this->waitUntil(
            static fn (): bool => is_file($readyFile),
            10.0,
            'Swoole server did not create its readiness file.'
        );

        $ready = json_decode((string) file_get_contents($readyFile), true, flags: JSON_THROW_ON_ERROR);
        $masterPid = $ready['masterPid'];
        self::assertIsInt($masterPid);
        self::assertGreaterThan(0, $masterPid);

        $managerPidFile = $this->runtimeDir . '/manager.pid';
        $this->waitUntil(
            static fn (): bool => is_file($managerPidFile),
            10.0,
            'Swoole manager did not publish its PID.'
        );
        $managerPid = (int) file_get_contents($managerPidFile);
        self::assertGreaterThan(0, $managerPid);

        $workerSocket = $this->runtimeDir . '/Integration.CrontabWorker.normal.sock';
        $this->waitUntil(
            static fn (): bool => file_exists($workerSocket),
            10.0,
            'Crontab worker did not create its Unix socket.'
        );

        $payload = $this->request($port, '/run');
        self::assertSame(Response::STATUS_OK, $payload['status']);
        self::assertIsInt($payload['result']);
        self::assertGreaterThan(0, $payload['result']);

        $processIds = array_values(array_unique([...$payload['processIds'], $managerPid, $serverPid]));
        self::assertIsArray($processIds);
        self::assertContains($masterPid, $processIds);
        self::assertGreaterThanOrEqual(
            4,
            count($processIds),
            'Expected the Swoole server and both Crontab custom processes: '
                . json_encode($processIds, JSON_THROW_ON_ERROR)
        );

        self::assertTrue(posix_kill($serverPid, SIGTERM));
        $exitCode = $this->waitForProcessExit(10.0);

        self::assertNotNull($exitCode, $this->processOutput('Server did not exit after SIGTERM.'));
        self::assertContains($exitCode, [0, 143], 'Unexpected server exit code.');
        $this->waitUntil(
            fn (): bool => $this->runningProcessIds($processIds) === [],
            5.0,
            'One or more Swoole/Crontab child processes survived SIGTERM.'
        );
        self::assertFileExists($this->runtimeDir . '/shutdown');
    }

    private function requireRuntimeCapabilities(): void
    {
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('The Swoole extension is required.');
        }
        if (!extension_loaded('posix')) {
            self::markTestSkipped('The POSIX extension is required.');
        }
        if (!function_exists('proc_open') || !defined('SIGTERM')) {
            self::markTestSkipped('Process control functions and signals are required.');
        }
    }

    private function reserveTcpPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($socket, $errorMessage);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        self::assertIsString($address);
        $port = (int) substr($address, (int) strrpos($address, ':') + 1);
        self::assertGreaterThan(0, $port);

        return $port;
    }

    /**
     * @return array{status: int|null, result: mixed, processIds: list<int>}
     */
    private function request(int $port, string $path): array
    {
        $socket = stream_socket_client("tcp://127.0.0.1:{$port}", $errorCode, $errorMessage, 3);
        self::assertIsResource($socket, $errorMessage);
        fwrite($socket, "GET {$path} HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
        stream_set_timeout($socket, 5);
        $rawResponse = stream_get_contents($socket);
        fclose($socket);

        self::assertIsString($rawResponse);
        self::assertStringContainsString('HTTP/1.1 200', $rawResponse, $this->processOutput('Request failed.'));
        [, $body] = explode("\r\n\r\n", $rawResponse, 2);

        return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<int> $processIds
     * @return list<int>
     */
    private function runningProcessIds(array $processIds): array
    {
        return array_values(array_filter(
            $processIds,
            static fn (int $pid): bool => posix_kill($pid, 0)
        ));
    }

    private function waitForProcessExit(float $timeout): ?int
    {
        $deadline = microtime(true) + $timeout;
        do {
            $status = proc_get_status($this->process);
            if (!$status['running']) {
                return $status['exitcode'];
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        return null;
    }

    private function waitUntil(callable $condition, float $timeout, string $failureMessage): void
    {
        $deadline = microtime(true) + $timeout;
        do {
            if ($condition()) {
                return;
            }
            if (is_resource($this->process) && !proc_get_status($this->process)['running']) {
                self::fail($this->processOutput($failureMessage));
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        self::fail($this->processOutput($failureMessage));
    }

    private function processOutput(string $message): string
    {
        $stdout = isset($this->pipes[1]) ? stream_get_contents($this->pipes[1]) : '';
        $stderr = isset($this->pipes[2]) ? stream_get_contents($this->pipes[2]) : '';

        return trim("{$message}\nSTDOUT:\n{$stdout}\nSTDERR:\n{$stderr}");
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \FilesystemIterator($directory);
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($directory);
    }
}
