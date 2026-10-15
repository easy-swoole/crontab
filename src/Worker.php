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
    /** @var array<string, JobInterface> */
    private array $jobs = [];
    private Table $schedulerTable;
    /** @var callable|null */
    private mixed $onException = null;

    public function run(mixed $arg): void
    {
        $this->schedulerTable = $arg['schedulerTable'];
        $this->jobs = $arg['jobs'];
        $this->onException = $arg['onException'];
        parent::run($arg);
    }

    public function onAccept(Socket $socket): void
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
                $job = $this->jobs[$jobName];
                $this->schedulerTable->incr($jobName, 'taskRunTimes', 1);
                $this->schedulerTable->set($jobName, ['taskLastRunTime' => time()]);
                try {
                    $ret = $job->run();
                    $response->setResult($ret);
                    $response->setStatus(Response::STATUS_OK);
                } catch (\Throwable $throwable) {
                    $response->setStatus(Response::STATUS_JOB_EXEC_ERROR);
                    $this->schedulerTable->set($jobName, [
                        'lastError' => $throwable->getMessage(),
                        'lastErrorTime' => time(),
                    ]);
                    try {
                        $job->onException($throwable);
                    } catch (\Throwable $t) {
                        if (is_callable($this->onException)) {
                            call_user_func($this->onException, $t);
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

    private function reply(Socket $socket, Response $response): void
    {
        $data = serialize($response);
        $socket->sendAll(Pack::pack($data));
        $socket->close();
    }
}
