<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use MakiseCo\Postgres\Exception;

class Connection
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
     * @param string $sql SQL query to execute.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return ResultSet|CommandResult
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function query(string $sql, float $timeout = 0)
    {
        return $this->handle->query($sql, $timeout);
    }

    /**
     * @param string $sql SQL query to prepare.
     * @param string|null $name SQL statement name. (optional)
     * @param int[] $types SQL statement parameter types. (optional)
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return Statement
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function prepare(string $sql, ?string $name = null, array $types = [], float $timeout = 0): Statement
    {
        return $this->handle->prepare($sql, $name, $types, $timeout);
    }

    /**
     * @param string $channel Channel name.
     * @param \Closure $callable Notifications receiver callback.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return CommandResult
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function listen(string $channel, \Closure $callable, float $timeout = 0): CommandResult
    {
        return $this->handle->listen($channel, $callable, $timeout);
    }

    /**
     * @param string $channel Channel name.
     * @param string $payload Notification payload.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return CommandResult
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
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
}