<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use Closure;
use pq;
use pq\Connection as PqConnection;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Timer;

use function sprintf;

class PqConnector
{
    private pq\Connection $pq;
    private ConnectionConfig $config;
    private ConnectContext $connectContext;

    public function __construct(ConnectionConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @return PqConnection
     * @throws Exception\FailureException
     * @throws Exception\ConnectionException when can't connect to Postgres server
     */
    public function connect(): pq\Connection
    {
        $timeout = $this->config->getConnectTimeout();

        try {
            $pq = $this->pq = new PqConnection($this->config->__toString(), PqConnection::ASYNC);
        } catch (pq\Exception\RuntimeException $e) {
            throw new Exception\ConnectionException($e->getMessage());
        }

        $this->pq->unbuffered = $this->config->getUnbuffered();
        $this->pq->nonblocking = true;

        $connectContext = $this->connectContext = new ConnectContext();
        $connectContext->cid = Coroutine::getCid();

        if (!Event::add(
            $this->pq->socket,
            Closure::fromCallable([$this, 'connectCallback']),
            Closure::fromCallable([$this, 'connectCallback']),
            SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE
        )) {
            throw new Exception\FailureException('Cannot add postgres events');
        }

        if ($timeout > 0) {
            $connectContext->timerId = Timer::after(
                (int)($timeout * 1000),
                static function (ConnectContext $ctx) {
                    $ctx->state = ConnectContext::STATE_CONNECTION_TIMEOUT;

                    Coroutine::resume($ctx->cid);
                },
                $connectContext
            );
        }

        Coroutine::yield();

        if ($connectContext->timerId) {
            Timer::clear($connectContext->timerId);
        }

        Event::del($pq->socket);

        switch ($connectContext->state) {
            case ConnectContext::STATE_CONNECTION_TIMEOUT:
                throw new Exception\ConnectionException(sprintf("Connection timeout. Timeout: %f secs", $timeout));
            case ConnectContext::STATE_CONNECTION_ERROR:
                throw new Exception\ConnectionException($pq->errorMessage);
            case ConnectContext::STATE_CONNECTED:
                break;
            default:
                throw new Exception\FailureException("Unknown connection state: {$connectContext->state}");
        }

        return $pq;
    }

    private function connectCallback(): void
    {
        switch ($this->pq->poll()) {
            case PqConnection::POLLING_READING: // Connection not ready, poll again.
            case PqConnection::POLLING_WRITING: // Still writing...
                return;

            case PqConnection::POLLING_FAILED:
                $this->connectContext->state = ConnectContext::STATE_CONNECTION_ERROR;

                Coroutine::resume($this->connectContext->cid);
                return;

            case PqConnection::POLLING_OK:
                $this->connectContext->state = ConnectContext::STATE_CONNECTED;

                Coroutine::resume($this->connectContext->cid);
                return;
        }
    }
}
