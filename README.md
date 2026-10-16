# crontab

## Script Test

```php
<?php

use EasySwoole\Crontab\Crontab;
use EasySwoole\Crontab\Tests\Jobs\JobPerMin;

require_once 'vendor/autoload.php';


$http = new Swoole\Http\Server('0.0.0.0', 9501);
$crontab = new Crontab();
$crontab->register(new JobPerMin());

$crontab->attachToServer($http);
$http->on('request', function ($request, $response) use ($crontab) {

    $ret = $crontab->rightNow('JobPerMin');

    $response->header('Content-Type', 'text/plain');
    $response->end('Hello World ' . $ret);
});

$http->start();
```

## Task Name

The value returned by `JobInterface::jobName()` must not exceed 128 bytes. An
exception is thrown when registering a job whose name exceeds this limit.

For jobs implementing `PrivilegeJobInterface`, names are also used in socket
file paths. These names must be non-empty and contain only ASCII letters,
digits, underscores (`_`) and hyphens (`-`). The name `normal` is reserved for
the normal Worker. Invalid privileged job names are rejected during registration.

## Task Status

Task status is stored in the scheduler table and can be read by task name:

```php
$task = $crontab->schedulerTable()->get('JobPerMin');

echo $task['taskLastRunTime'];
echo $task['lastError'];
echo $task['lastErrorTime'];
```

The table includes the following runtime fields:

| Field | Description |
| --- | --- |
| `taskLastRunTime` | Unix timestamp when the task most recently started. The initial value is `0`. |
| `lastError` | Message from the most recent exception thrown by the task. The initial value is an empty string. |
| `lastErrorTime` | Unix timestamp when `lastError` was recorded. The initial value is `0`. |

`lastError` and `lastErrorTime` retain the most recent task execution error and
are updated before the task's `onException()` handler is called.

## Worker Start Callback

Use `Config::setOnWorkerStart()` to run initialization code after a crontab
Worker has initialized its internal state and before it starts listening for
tasks. The callback receives the started `Worker` instance and is called for
both the normal Worker and every privileged Worker.

```php
use EasySwoole\Crontab\Config;
use EasySwoole\Crontab\Worker;

$config = new Config();
$config->setOnWorkerStart(static function (Worker $worker): void {
    // Initialize resources used by this worker process.
});

$crontab = new Crontab($config);
```
