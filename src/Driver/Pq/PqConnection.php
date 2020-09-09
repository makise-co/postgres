<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use Closure;
use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ConnectionConfig;
use MakiseCo\Postgres\Util\Deferred;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use MakiseCo\SqlCommon\Exception\FailureException;
use pq;
use Swoole\Event;
use Swoole\Timer;

final class PqConnection extends Connection
{
    /**
     * @inheritDoc
     */
    public static function connect(ConnectionConfig $connectionConfig): self
    {
        try {
            $connection = new pq\Connection($connectionConfig->getConnectionString(), pq\Connection::ASYNC);
        } catch (pq\Exception $exception) {
            throw new ConnectionException("Could not connect to PostgreSQL server", 0, $exception);
        }

        $connection->nonblocking = true;
        $connection->unbuffered = $connectionConfig->getUnbuffered();

        $deferred = new Deferred();

        $callback = static function () use ($connection, $deferred): void {
            switch ($connection->poll()) {
                case pq\Connection::POLLING_READING: // Connection not ready, poll again.
                case pq\Connection::POLLING_WRITING: // Still writing...
                    return;

                case pq\Connection::POLLING_FAILED:
                    // TODO: Fix possible duplicated call of Coroutine::resume
                    $deferred->fail(new ConnectionException($connection->errorMessage));
                    return;

                case pq\Connection::POLLING_OK:
                    // TODO: Fix possible duplicated call of Coroutine::resume
                    Event::del($connection->socket);
                    $deferred->resolve(new self($connection));
                    return;
            }
        };

        if (!Event::add($connection->socket, $callback, $callback, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE)) {
            throw new FailureException('Unable to register src event');
        }

        $tid = null;
        if ($connectionConfig->getConnectTimeout() > 0) {
            $tid = Timer::after(
                (int)($connectionConfig->getConnectTimeout() * 1000),
                Closure::fromCallable([$deferred, 'fail']),
                new ConnectionException('Connection is timed out')
            );
        }

        try {
            return $deferred->wait();
        } catch (\Throwable $e) {
            Event::del($connection->socket);

            throw $e;
        } finally {
            if ($tid > 0) {
                Timer::clear($tid);
            }
        }
    }

    /**
     * @param pq\Connection $handle
     */
    public function __construct(pq\Connection $handle)
    {
        parent::__construct(new PqHandle($handle));
    }

    /**
     * @return bool True if result sets are buffered in memory, false if unbuffered.
     */
    public function isBufferingResults(): bool
    {
        return $this->handle->isBufferingResults();
    }

    /**
     * Sets result sets to be fully buffered in local memory.
     */
    public function shouldBufferResults(): void
    {
        $this->handle->shouldBufferResults();
    }

    /**
     * Sets result sets to be streamed from the database server.
     */
    public function shouldNotBufferResults(): void
    {
        $this->handle->shouldNotBufferResults();
    }
}
