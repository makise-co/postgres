<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Sql;

use MakiseCo\Postgres\CommandResult;
use MakiseCo\Postgres\Exception;
use MakiseCo\Postgres\ResultSet;
use MakiseCo\Postgres\Statement;
use MakiseCo\Postgres\Transaction;

interface ExecutorInterface
{
    /**
     * @param string $sql SQL query to execute.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return ResultSet|CommandResult result set for rows queries or command result for system queries
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function query(string $sql, float $timeout = 0);

    /**
     * @param string $sql SQL query to prepare and execute.
     * @param array[] $params Query parameters.
     * @param array $types SQL statement parameter types. (optional)
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return CommandResult|ResultSet
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function execute(string $sql, array $params = [], array $types = [], float $timeout = 0);

    /**
     * @param string $sql SQL query to prepare.
     * @param string|null $name SQL statement name. (optional)
     * @param array $types SQL statement parameter types. (optional)
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return Statement
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     */
    public function prepare(
        string $sql,
        ?string $name = null,
        array $types = [],
        float $timeout = 0
    ): StatementInterface;

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
    public function notify(string $channel, string $payload, float $timeout = 0): CommandResult;

    /**
     * Starts a transaction on a single connection.
     * WARNING: Do not use it to nest transactions
     *
     * @param int $isolation Transaction isolation level.
     *
     * @return TransactionInterface
     */
    public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): TransactionInterface;
}
