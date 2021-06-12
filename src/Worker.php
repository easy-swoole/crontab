<?php


namespace EasySwoole\Crontab;


use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use Swoole\Coroutine\Socket;

class Worker extends AbstractUnixProcess
{
    function onAccept(Socket $socket)
    {

    }
}