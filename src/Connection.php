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
use InvalidArgumentException;
use MakiseCo\Postgres\Exception;
use MakiseCo\Postgres\Sql\ExecutorInterface;
use MakiseCo\Postgres\Sql\QuoterInterface;

class Connection implements ExecutorInterface, QuoterInterface
{
    private ConnectConfig $config;
    private ?PqHandle $handle = null;

    public function __construct(ConnectConfig $config)
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
        $pq = $connector->connect();

        $this->handle = new PqHandle($pq);
    }

    public function disconnect(): void
    {
        if (!$this->isConnected()) {
            return;
        }

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
        return $this->handle->query($sql, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = [], array $types = [], float $timeout = 0)
    {
        return $this->handle->execute($sql, $params, $types, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(string $sql, ?string $name = null, array $types = [], float $timeout = 0): Statement
    {
        return $this->handle->prepare($sql, $name, $types, $timeout);
    }

    /**
     * @param string $channel Channel name.
     * @param Closure|null $callable Notifications receiver callback.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return Listener|null When callable is null - Listener object is returned
     *      When callable is not null - null is returned
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function listen(string $channel, ?Closure $callable, float $timeout = 0): ?Listener
    {
        return $this->handle->listen($channel, $callable, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function notify(string $channel, string $payload, float $timeout = 0): CommandResult
    {
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
        return $this->handle->unlisten($channel, $timeout);
    }

    /**
     * Starts a transaction on a single connection.
     *
     * @param int $isolation Transaction isolation level.
     *
     * @return Transaction
     *
     * @throws Exception\FailureException
     */
    final public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): Transaction
    {
        // TODO: Add support for READ ONLY transactions
        // TODO: Add support for DEFERRABLE transactions
        // Link: https://www.postgresql.org/docs/9.1/sql-set-transaction.html
        // Link: https://mdref.m6w6.name/pq/Connection/startTransaction

        switch ($isolation) {
            case Transaction::ISOLATION_UNCOMMITTED:
                $this->handle->query("BEGIN TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
                break;

            case Transaction::ISOLATION_COMMITTED:
                $this->handle->query("BEGIN TRANSACTION ISOLATION LEVEL READ COMMITTED");
                break;

            case Transaction::ISOLATION_REPEATABLE:
                $this->handle->query("BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                break;

            case Transaction::ISOLATION_SERIALIZABLE:
                $this->handle->query("BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE");
                break;

            default:
                throw new InvalidArgumentException("Invalid transaction type");
        }

        return new Transaction($this->handle, $isolation);
    }

    /**
     * {@inheritdoc}
     */
    final public function quoteString(string $data): string
    {
        return $this->handle->quoteString($data);
    }

    /**
     * {@inheritdoc}
     */
    final public function quoteName(string $name): string
    {
        return $this->handle->quoteName($name);
    }
}