<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use InvalidArgumentException;
use MakiseCo\Postgres\Exception\TransactionError;
use MakiseCo\Postgres\Sql\TransactionInterface;

class Transaction implements TransactionInterface
{
    private ?PqHandle $handle;

    private int $isolation;

    /**
     * @param PqHandle $handle
     * @param int $isolation
     *
     * @throws InvalidArgumentException If the isolation level is invalid.
     */
    public function __construct(PqHandle $handle, int $isolation = self::ISOLATION_COMMITTED)
    {
        switch ($isolation) {
            case self::ISOLATION_UNCOMMITTED:
            case self::ISOLATION_COMMITTED:
            case self::ISOLATION_REPEATABLE:
            case self::ISOLATION_SERIALIZABLE:
                $this->isolation = $isolation;
                break;

            default:
                throw new InvalidArgumentException("Isolation must be a valid transaction isolation level");
        }

        $this->handle = $handle;
    }

    public function __destruct()
    {
        if ($this->handle && $this->handle->isConnected()) {
            $this->rollback(); // Invokes $this->release callback.
        }
    }

    /**
     * Closes the executor. No further queries may be performed.
     */
    public function close(): void
    {
        if ($this->handle) {
            $this->commit(); // Invokes $this->release callback.
        }
    }

    /**
     * @return bool True if the transaction is active, false if it has been committed or rolled back.
     */
    public function isActive(): bool
    {
        return $this->handle !== null;
    }

    /**
     * @return int
     */
    public function getIsolationLevel(): int
    {
        return $this->isolation;
    }

    /**
     * @param string $sql SQL query to execute.
     *
     * @return CommandResult|ResultSet
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function query(string $sql)
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->query($sql);
    }

    /**
     * @param string $sql SQL query to prepare.
     *
     * @return Statement
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function prepare(string $sql): Statement
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->prepare($sql);
    }

    /**
     * @param string $sql SQL query to prepare and execute.
     * @param mixed[] $params Query parameters.
     *
     * @return CommandResult|ResultSet
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function execute(string $sql, array $params = [])
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->execute($sql, $params);
    }

    /**
     * Commits the transaction and makes it inactive.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function commit(): CommandResult
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        $result = $this->handle->query("COMMIT");
        $this->handle = null;

        return $result;
    }

    /**
     * Rolls back the transaction and makes it inactive.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function rollback(): CommandResult
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        $result = $this->handle->query("ROLLBACK");
        $this->handle = null;

        return $result;
    }

    /**
     * Creates a savepoint with the given identifier.
     *
     * @param string $identifier Savepoint identifier.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function createSavepoint(string $identifier): CommandResult
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->query("SAVEPOINT " . $this->quoteName($identifier));
    }

    /**
     * Rolls back to the savepoint with the given identifier.
     *
     * @param string $identifier Savepoint identifier.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function rollbackTo(string $identifier): CommandResult
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->query("ROLLBACK TO " . $this->quoteName($identifier));
    }

    /**
     * Releases the savepoint with the given identifier.
     *
     * @param string $identifier Savepoint identifier.
     *
     * @return CommandResult
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function releaseSavepoint(string $identifier): CommandResult
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->query("RELEASE SAVEPOINT " . $this->quoteName($identifier));
    }

    /**
     * {@inheritdoc}
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function quoteString(string $data): string
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->quoteString($data);
    }

    /**
     * {@inheritdoc}
     *
     * @throws TransactionError If the transaction has been committed or rolled back.
     */
    public function quoteName(string $name): string
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->quoteName($name);
    }
}