# crontab

基于 Swoole 自定义进程的定时任务组件，支持 Cron 表达式、独立任务进程、立即执行、暂停恢复，以及运行状态和最近异常查询。

## 环境与安装

需要 PHP 8.1 或以上版本，以及支持进程、共享内存表和协程 socket 的 Swoole 扩展。

```bash
composer require easyswoole/crontab
```

## 快速开始

下面定义一个每分钟执行一次的任务，并挂载到 Swoole HTTP 服务。保存为 `server.php` 后执行 `php server.php`；访问 HTTP 服务可以查询任务状态。

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use EasySwoole\Crontab\Config;
use EasySwoole\Crontab\Crontab;
use EasySwoole\Crontab\JobInterface;

class HeartbeatJob implements JobInterface
{
    public function jobName(): string
    {
        return 'Heartbeat';
    }

    public function crontabRule(): string
    {
        return '* * * * *';
    }

    public function run(): mixed
    {
        echo date('Y-m-d H:i:s') . " 执行心跳任务\n";
        return '完成';
    }

    public function onException(\Throwable $throwable): void
    {
        error_log($throwable->getMessage());
    }
}

$config = new Config();
$config->setServerName('Demo');
$config->setTempDir(sys_get_temp_dir());

$crontab = new Crontab($config);
$crontab->register(new HeartbeatJob());

$http = new Swoole\Http\Server('127.0.0.1', 9501);
$crontab->attachToServer($http);
$http->on('request', function ($request, $response) use ($crontab): void {
    $task = $crontab->schedulerTable()->get('Heartbeat');
    $response->header('Content-Type', 'application/json; charset=utf-8');
    $response->end(json_encode($task, JSON_UNESCAPED_UNICODE));
});
$http->start();
```

在服务启动前完成配置、任务注册和 `attachToServer()` 调用。注册不会直接启动任务；没有注册任务时，挂载方法不会创建调度进程和 Worker。任务在自定义进程中执行。

## 定义与注册任务

普通任务实现 `JobInterface`：

| 方法 | 说明 |
| --- | --- |
| `jobName(): string` | 返回唯一且稳定的任务名称，用于注册、状态查询和任务控制。 |
| `crontabRule(): string` | 返回 Cron 表达式。 |
| `run(): mixed` | 执行业务逻辑；立即执行时可读取返回值，返回值应可序列化。 |
| `onException(\Throwable $throwable): void` | 处理该任务执行时抛出的异常。 |

`register()` 返回当前 `Crontab` 实例，支持链式调用。重复注册同名任务、表达式无效或名称不符合限制时，会抛出 `EasySwoole\Crontab\Exception\Exception`。

### 名称限制

所有任务名称最长为 **128 字节**，按 `strlen()` 计算，多字节字符按实际字节数计算。

特权任务名称还用于生成 socket 文件路径，必须满足：

- 名称不能为空。
- 仅允许 ASCII 英文字母、数字、下划线 `_` 和连字符 `-`。
- 不能为 `normal`，该名称保留给普通 Worker。

例如 `Report_01-daily` 可以作为特权任务名，`App\Jobs\Report`、`report/daily` 和 `report.daily` 会被拒绝。普通任务不受上述字符白名单限制。

### Cron 表达式

使用五段格式，依次为分钟、小时、日期、月份、星期，不包含秒字段。

| 表达式 | 执行时间 |
| --- | --- |
| `* * * * *` | 每分钟 |
| `*/5 * * * *` | 每 5 分钟 |
| `0 * * * *` | 每小时整点 |
| `0 2 * * *` | 每天 02:00 |
| `0 9 * * 1-5` | 周一至周五 09:00 |

时间计算使用 PHP 默认时区。需要固定时区时，请在服务启动前设置，例如 `date_default_timezone_set('Asia/Shanghai')`。

## 普通任务与特权任务

组件创建一个 Scheduler 调度进程、一个普通任务共享的 Worker，并为每个实现 `PrivilegeJobInterface` 的任务额外创建独立 Worker。

特权接口继承自 `JobInterface`，无需实现额外方法。在前面的示例基础上，可以定义：

```php
class DedicatedHeartbeatJob extends HeartbeatJob implements \EasySwoole\Crontab\PrivilegeJobInterface
{
    public function jobName(): string
    {
        return 'DedicatedHeartbeat';
    }
}

// 在 attachToServer() 之前注册。
$crontab->register(new DedicatedHeartbeatJob());
```

该任务会被路由到自己的 Worker。独立进程用于隔离执行环境，不表示获得操作系统额外权限，也不提供防止同一任务重复并发执行的锁。

socket 路径格式：

```text
普通 Worker：{tempDir}/{serverName}.CrontabWorker.normal.sock
特权 Worker：{tempDir}/{serverName}.CrontabWorker.{任务名}.sock
```

`tempDir` 应为已存在、进程可写的目录。同一目录内运行多个服务实例时，请使用不同的 `serverName`。Unix socket 完整路径受运行系统长度限制，名称通过 128 字节校验并不保证完整路径可用，请使用较短的目录、服务名和特权任务名。

## 配置项

将 `Config` 传入 `Crontab` 构造方法，也可以通过 `$crontab->getConfig()` 取得配置对象。配置应在挂载进程前完成。

| 配置方法 | 默认值 | 说明 |
| --- | --- | --- |
| `setServerName(string $serverName)` | `EasySwoole` | 进程名称和 socket 文件名前缀。 |
| `setTempDir(string $tempDir)` | 当前工作目录 `getcwd()` | socket 文件存放目录。 |
| `setOnWorkerStart(callable $callback)` | `null`，不调用 | Worker 启动初始化回调。 |
| `setOnException(callable $callback)` | `null`，不调用 | 任务异常处理回调再次抛出异常时的处理回调。 |

对应读取方法为 `getServerName()`、`getTempDir()`、`getOnWorkerStart()` 和 `getOnException()`。两个回调 setter 都要求有效的 `callable`，不能传入 `null` 清除已设置的回调。

### Worker 启动回调

`onWorkerStart` 在 Worker 初始化任务列表、共享表和异常回调后、开始监听 socket 前执行，接收当前 `Worker` 实例。普通 Worker 和每个特权 Worker 都会调用，Scheduler 不调用。

```php
use EasySwoole\Crontab\Worker;

$config->setOnWorkerStart(static function (Worker $worker): void {
    $workerName = (string) $worker->getArg()['crontabWorkerName'];

    if ($workerName === 'normal') {
        // 初始化普通任务共享的进程资源。
    } else {
        // 特权 Worker 的标识为对应任务名，可按名称初始化资源。
    }
});
```

适合在此初始化进程内的连接池等资源。回调应及时返回，Worker 才会开始监听任务。回调抛出异常会中断本次 Worker 启动，不会写入某个任务的 `lastError`，也不经过配置中的任务异常回调。

### 异常处理

当任务 `run()` 抛出 `Throwable` 时：

1. 将执行响应状态设置为 `Response::STATUS_JOB_EXEC_ERROR`。
2. 将原始异常的 `getMessage()` 和当前 Unix 时间戳写入任务的 `lastError`、`lastErrorTime`。
3. 调用任务自身的 `onException($throwable)`。
4. 如果任务的 `onException()` 再次抛出异常，则调用配置中的 `onException`；没有配置时继续向外抛出。

配置回调接收的是任务 `onException()` 抛出的异常。若希望统一处理原始任务异常，可以在任务类中重新抛出：

```php
// 任务类中的方法。
public function onException(\Throwable $throwable): void
{
    throw $throwable;
}
```

然后在服务启动前配置：

```php
$config->setOnException(static function (\Throwable $throwable): void {
    error_log((string) $throwable);
});
```

## 立即执行与任务控制

以下操作应在服务已启动、Worker 可接收请求后使用。

### 立即执行

```php
use EasySwoole\Crontab\Protocol\Response;

$result = $crontab->rightNow('Heartbeat');
if ($result !== null && $result->getStatus() === Response::STATUS_OK) {
    $value = $result->getResult();
} elseif ($result !== null) {
    $message = $result->getMsg()
        ?? Response::getReasonPhrase($result->getStatus());
}
```

`rightNow()` 通过对应 Worker 执行任务并等待响应，任务未注册时抛出异常。它也会更新执行次数、最近开始时间及异常信息。接收响应的等待时间为 3 秒；响应超时不代表任务已取消。socket 连接等通信异常也可能直接向外抛出。

### 暂停、恢复和修改规则

```php
$crontab->stop('Heartbeat');
$crontab->resume('Heartbeat');
$crontab->stopAll();
$crontab->resumeAll();
$crontab->resetJobRule('Heartbeat', '*/5 * * * *');
```

| 方法 | 返回值与行为 |
| --- | --- |
| `stop($jobName)` | 已注册返回 `true`，未知任务返回 `false`；暂停后续自动调度。 |
| `resume($jobName)` | 已注册返回 `true`，未知任务返回 `false`；恢复自动调度。 |
| `stopAll()` / `resumeAll()` | 更新当前表中所有任务的暂停状态，返回 `true`。 |
| `resetJobRule($jobName, $rule)` | 已注册且规则有效时返回 `true`；未知任务返回 `false`，规则无效抛出异常。 |

暂停不会终止已经运行的任务，也不会阻止 `rightNow()` 手动执行。Scheduler 每 7 秒进行一次调度检查，状态更新和定时器调整并非同步完成。`resetJobRule()` 修改的是共享表中的运行规则，服务或 Scheduler 重新启动时仍从任务类的 `crontabRule()` 初始化。

## 查询运行状态

通过 `schedulerTable()` 获取 `Swoole\Table`，按名称读取或遍历：

```php
$task = $crontab->schedulerTable()->get('Heartbeat');
if ($task !== false) {
    echo '执行次数：' . $task['taskRunTimes'] . PHP_EOL;
    echo '最近开始时间：' . $task['taskLastRunTime'] . PHP_EOL;
    echo '最近异常：' . $task['lastError'] . PHP_EOL;
}

foreach ($crontab->schedulerTable() as $name => $status) {
    // 按任务名称处理状态。
}
```

状态由 Scheduler 启动时初始化。服务尚未启动或任务不存在时，`get()` 可能返回 `false`。

| 字段 | 含义 | 初始化值 |
| --- | --- | --- |
| `taskName` | 任务名称，最大 128 字节。 | 注册的任务名 |
| `taskRule` | 当前调度规则，字符串列容量为 64 字节。 | 任务的 Cron 表达式 |
| `taskRunTimes` | 执行尝试次数，在调用 `run()` 前递增，包含失败和手动执行。 | `0` |
| `taskNextRunTime` | 下次计划执行的 Unix 时间戳。 | 根据规则计算 |
| `taskLastRunTime` | 最近一次开始执行的 Unix 时间戳，不是完成时间或耗时。 | `0` |
| `isStop` | 是否暂停自动调度，`1` 为暂停，`0` 为正常。 | `0` |
| `needRestJobTimer` | 内部定时器重建标记，由 Scheduler 消费，通常无需业务代码操作。 | `0` |
| `lastError` | 最近任务执行异常的消息，不包含完整堆栈，字符串列容量为 1024 字节。 | 空字符串 |
| `lastErrorTime` | 最近一次记录任务异常的 Unix 时间戳。 | `0` |

`taskLastRunTime` 为原 `taskCurrentRunTime` 的更名，请使用新字段。

后续执行成功不会清空 `lastError` 和 `lastErrorTime`，它们表示最近一次历史错误，不能用于判断最近一次执行是否成功。共享表不是持久化存储；服务或 Scheduler 重新启动时，执行次数、错误信息和运行中的规则调整会重新初始化。

## 测试

在项目源码目录安装开发依赖后运行单元测试：

```bash
composer install
./vendor/bin/phpunit --testsuite Unit --do-not-cache-result
```

集成测试涉及真实服务和子进程，在具备相应 Swoole 运行环境时执行：

```bash
composer test:integration
```
