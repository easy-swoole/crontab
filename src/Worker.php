<?php


namespace EasySwoole\Crontab;


use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use EasySwoole\Crontab\Protocol\Pack;
use EasySwoole\Crontab\Protocol\Command;
use EasySwoole\Crontab\Protocol\Response;
use Swoole\Coroutine\Socket;
use Swoole\Table;

class Worker extends AbstractUnixProcess
{
    /** @var Crontab */
    private $crontabInstance;
    /** @var Table */
    private $jobs = [];
    /** @var Table */
    private $schedulerTable;

    public function run($arg)
    {
        $this->crontabInstance = $arg['crontabInstance'];
        $this->schedulerTable = $arg['schedulerTable'];
        $this->jobs = $arg['jobs'];
        parent::run($arg);
    }

    function onAccept(Socket $socket)
    {
        $response = new Response();

        $header = $socket->recvAll(4, 1);
        if (strlen($header) != 4) {
            $response->setStatus(Response::STATUS_PACKAGE_READ_TIMEOUT)->setMsg('recv from client timeout');
            $this->reply($socket, $response);
            return;
        }
        $allLength = Pack::packDataLength($header);
        $data = $socket->recvAll($allLength, 1);
        if (strlen($data) != $allLength) {
            $response->setStatus(Response::STATUS_PACKAGE_READ_TIMEOUT)->setMsg('recv from client timeout');
            $this->reply($socket, $response);
            return;
        }
        $data = unserialize($data);
        if (!$data instanceof Command) {
            $response->setStatus(Response::STATUS_ILLEGAL_PACKAGE)->setMsg('unserialize request as an Command instance fail');
            $this->reply($socket, $response);
            return;
        }
        if ($data->getCommand() === Command::COMMAND_EXEC_JOB) {
            $jobName = $data->getArg();
            if (isset($this->jobs[$jobName])) {
                /** @var JobInterface $job */
                $job = $this->jobs[$jobName];
                $this->schedulerTable->incr($jobName, 'taskRunTimes', 1);
                $this->schedulerTable->set($jobName, ['taskCurrentRunTime' => time()]);
                try {
                    $ret = $job->run();
                    $response->setResult($ret);
                    $response->setStatus(Response::STATUS_OK);
                } catch (\Throwable $throwable) {
                    $response->setStatus(Response::STATUS_JOB_EXEC_ERROR);
                    try {
                        $job->onException($throwable);
                    } catch (\Throwable $t) {
                        $call = $this->crontabInstance->getConfig()->getOnException();
                        if (is_callable($call)) {
                            call_user_func($call, $t);
                        } else {
                            throw $t;
                        }
                    }
                } finally {
                    $this->reply($socket, $response);
                }
            } else {
                $response->setStatus(Response::STATUS_JOB_NOT_EXIST);
                $this->reply($socket, $response);
            }
        } else {
            $response->setStatus(Response::STATUS_UNKNOWN_COMMAND);
            $this->reply($socket, $response);
        }
    }

    private function reply(Socket $socket, Response $response)
    {
        $data = serialize($response);
        $socket->sendAll(Pack::pack($data));
        $socket->close();
    }
}