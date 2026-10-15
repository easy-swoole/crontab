<?php

declare(strict_types=1);

use EasySwoole\Crontab\Config;
use EasySwoole\Crontab\Crontab;
use EasySwoole\Crontab\Protocol\Response;
use EasySwoole\Crontab\Tests\Jobs\ProcessIdJob;
use Swoole\Http\Request;
use Swoole\Http\Response as HttpResponse;
use Swoole\Http\Server;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php server.php <runtime-dir> <port>\n");
    exit(64);
}

$runtimeDir = $argv[1];
$port = (int) $argv[2];

$config = new Config();
$config->setServerName('Integration');
$config->setTempDir($runtimeDir);

$crontab = new Crontab($config);
$crontab->register(new ProcessIdJob());

$server = new Server('127.0.0.1', $port);
$server->set([
    'worker_num' => 1,
    'max_wait_time' => 3,
    'log_file' => $runtimeDir . '/swoole.log',
    'log_level' => SWOOLE_LOG_WARNING,
]);

$server->on('start', static function (Server $server) use ($runtimeDir): void {
    file_put_contents($runtimeDir . '/ready.json', json_encode([
        'masterPid' => $server->master_pid,
        'managerPid' => $server->manager_pid,
    ], JSON_THROW_ON_ERROR));
});

$server->on('managerStart', static function (Server $server) use ($runtimeDir): void {
    file_put_contents($runtimeDir . '/manager.pid', (string) $server->manager_pid);
});

$server->on('shutdown', static function () use ($runtimeDir): void {
    file_put_contents($runtimeDir . '/shutdown', 'ok');
});

$server->on('request', static function (Request $request, HttpResponse $response) use ($crontab, $server): void {
    if ($request->server['request_uri'] === '/run') {
        $jobResponse = $crontab->rightNow('ProcessIdJob');
        $processIds = [$server->master_pid, $server->manager_pid];
        for ($workerId = 0; $workerId < 5; ++$workerId) {
            $workerPid = $server->getWorkerPid($workerId);
            if (is_int($workerPid) && $workerPid > 0) {
                $processIds[] = $workerPid;
            }
        }
        if (is_int($jobResponse?->getResult())) {
            $processIds[] = $jobResponse->getResult();
        }
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'status' => $jobResponse?->getStatus(),
            'result' => $jobResponse?->getResult(),
            'processIds' => array_values(array_unique($processIds)),
        ], JSON_THROW_ON_ERROR));
        return;
    }

    $response->status(404);
    $response->end((string) Response::STATUS_UNKNOWN_COMMAND);
});

$crontab->attachToServer($server);
$server->start();
