# crontab

## 脚本测试
```php
use EasySwoole\Crontab\Crontab;
use EasySwoole\Crontab\Tests\Jobs\JobPerMin;

require_once 'vendor/autoload.php';


$http = new Swoole\Http\Server('0.0.0.0', 9501);

Crontab::getInstance()->register(new JobPerMin());

Crontab::getInstance()->__attachServer($http);
$http->on('request', function ($request, $response) {

    $ret = Crontab::getInstance()->rightNow('JobPerMin');

    $response->header('Content-Type', 'text/plain');
    $response->end('Hello World '.$ret);
});

$http->start();
```