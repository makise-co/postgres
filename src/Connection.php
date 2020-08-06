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
use MakiseCo\Postgres\Exception;
use MakiseCo\Postgres\Sql\ExecutorInterface;
use MakiseCo\Postgres\Sql\QuoterInterface;
use MakiseCo\Postgres\Sql\ReceiverInterface;
use Throwable;

class Connection implements ExecutorInterface, ReceiverInterface, QuoterInterface
{
    private ConnectionConfig $config;
    private ?PqHandle $handle = null;
    private ?Throwable $connError = null;

    public function __construct(ConnectionConfig $config)
    {
        $this->config = $config;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        $connector = new PqConnector($this->config);
        try {
            $pq = $connector->connect();
        } catch (Throwable $e) {
            $this->connError = $e;

            throw $e;
        }

        $this->connError = null;
        $this->handle = new PqHandle($pq);
    }

    public function disconnect(): void
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->connError = null;
        $this->handle->disconnect();
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return null !== $this->handle && $this->handle->isConnected();
    }

    public function getHandle(): ?PqHandle
    {
        return $this->handle;
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, float $timeout = 0)
    {
        $this->checkConnection();

        return $this->handle->query($sql, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = [], array $types = [], float $timeout = 0)
    {
        $this->checkConnection();

        return $this->handle->execute($sql, $params, $types, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(string $sql, ?string $name = null, array $types = [], float $timeout = 0): Statement
    {
        $this->checkConnection();

        return $this->handle->prepare($sql, $name, $types, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function listen(string $channel, ?Closure $callable, float $timeout = 0): ?Listener
    {
        $this->checkConnection();

        return $this->handle->listen($channel, $callable, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function notify(string $channel, string $payload, float $timeout = 0): CommandResult
    {
        $this->checkConnection();

        return $this->handle->notify($channel, $payload, $timeout);
    }

    /**
     * Unlistens from the channel. No more values will be emitted from this listener.
     *
     * @param string $channel Channel name.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return CommandResult
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     */
    public function unlisten(string $channel, float $timeout = 0): CommandResult
    {
        $this->checkConnection();

        return $this->handle->unlisten($channel, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    final public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): Transaction
    {
        $this->checkConnection();

        return $this->handle->beginTransaction($isolation);
    }

    /**
     * {@inheritdoc}
     */
    final public function quoteString(string $data): string
    {
        $this->checkConnection();

        return $this->handle->quoteString($data);
    }

    /**
     * {@inheritdoc}
     */
    final public function quoteName(string $name): string
    {
        $this->checkConnection();

        return $this->handle->quoteName($name);
    }

    private function checkConnection(): void
    {
        if (null === $this->handle) {
            // connError is used for connection pooling feature
            if ($this->connError) {
                throw $this->connError;
            }

            throw new Exception\ConnectionException('Connection is closed');
        }
    }
}
