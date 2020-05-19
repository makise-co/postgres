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
use MakiseCo\Postgres\Exception\TransactionError;

interface TransactionInterface extends ExecutorInterface, QuoterInterface
{
    public const ISOLATION_UNCOMMITTED = 0;
    public const ISOLATION_COMMITTED = 1;
    public const ISOLATION_REPEATABLE = 2;
    public const ISOLATION_SERIALIZABLE = 4;

    /**
     * @return int
     */
    public function getIsolationLevel(): int;

    /**
     * @return bool True if the transaction is active, false if it has been committed or rolled back.
     */
    public function isActive(): bool;

    /**
     * Commits the transaction and makes it inactive.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function commit(): CommandResult;

    /**
     * Rolls back the transaction and makes it inactive.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function rollback(): CommandResult;

    /**
     * Creates a savepoint with the given identifier.
     *
     * @param string $identifier Savepoint identifier.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function createSavepoint(string $identifier): CommandResult;

    /**
     * Rolls back to the savepoint with the given identifier.
     *
     * @param string $identifier Savepoint identifier.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function rollbackTo(string $identifier): CommandResult;

    /**
     * Releases the savepoint with the given identifier.
     *
     * @param string $identifier Savepoint identifier.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function releaseSavepoint(string $identifier): CommandResult;
}
