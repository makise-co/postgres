<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\Postgres\Contracts\Handle;
use MakiseCo\SqlCommon\Contracts\ResultSet;
use MakiseCo\SqlCommon\Contracts\Statement;
use MakiseCo\Postgres\Contracts\Transaction;
use MakiseCo\SqlCommon\Exception;
use MakiseCo\SqlCommon\PooledResultSet;
use MakiseCo\SqlCommon\PooledStatement;

final class ConnectionTransaction implements Transaction
{
    private ?Handle $handle;

    private int $isolation;

    private \Closure $release;

    private int $refCount = 1;

    /**
     * @param Handle $handle
     * @param callable $release
     * @param int $isolation
     *
     * @throws \Error If the isolation level is invalid.
     */
    public function __construct(Handle $handle, callable $release, int $isolation = Transaction::ISOLATION_COMMITTED)
    {
        switch ($isolation) {
            case Transaction::ISOLATION_UNCOMMITTED:
            case Transaction::ISOLATION_COMMITTED:
            case Transaction::ISOLATION_REPEATABLE:
            case Transaction::ISOLATION_SERIALIZABLE:
                $this->isolation = $isolation;
                break;

            default:
                throw new \Error("Isolation must be a valid transaction isolation level");
        }

        $this->handle = $handle;

        $refCount =& $this->refCount;
        $this->release = static function () use (&$refCount, $release) {
            if (--$refCount === 0) {
                $release();
            }
        };
    }

    public function __destruct()
    {
        if ($this->handle !== null && $this->handle->isAlive()) {
            $this->rollback(); // Invokes $this->release callback.
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLastUsedAt(): int
    {
        return $this->handle->getLastUsedAt();
    }

    /**
     * {@inheritdoc}
     *
     * Closes and commits all changes in the transaction.
     */
    public function close(): void
    {
        if ($this->handle) {
            $this->commit(); // Invokes $this->release callback.
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAlive(): bool
    {
        return $this->handle && $this->handle->isAlive();
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
     * {@inheritdoc}
     *
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function query(string $sql)
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
        }

        ++$this->refCount;

        try {
            $result = $this->handle->query($sql);
        } finally {
            ($this->release)();
        }

        if ($result instanceof ResultSet) {
            ++$this->refCount;

            return new PooledResultSet($result, $this->release);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function prepare(string $sql): Statement
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
        }

        ++$this->refCount;

        try {
            $statement = $this->handle->prepare($sql);
        } catch (\Throwable $exception) {
            ($this->release)();

            throw $exception;
        }

        return new PooledStatement($statement, $this->release);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function execute(string $sql, array $params = [])
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
        }

        ++$this->refCount;

        try {
            $result = $this->handle->execute($sql, $params);
        } finally {
            ($this->release)();
        }

        if ($result instanceof ResultSet && $result->isUnbuffered()) {
            ++$this->refCount;

            return new PooledResultSet($result, $this->release);
        }

        return $result;
    }


    /**
     * {@inheritdoc}
     *
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function notify(string $channel, string $payload = ""): CommandResult
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->notify($channel, $payload);
    }

    /**
     * Commits the transaction and makes it inactive.
     *
     * @return CommandResult
     *
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function commit(): CommandResult
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
        }

        $promise = $this->handle->query("COMMIT");
        $this->handle = null;
        ($this->release)();

        return $promise;
    }

    /**
     * Rolls back the transaction and makes it inactive.
     *
     * @return CommandResult
     *
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function rollback(): CommandResult
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
        }

        $promise = $this->handle->query("ROLLBACK");
        $this->handle = null;
        ($this->release)();

        return $promise;
    }

    /**
     * Creates a savepoint with the given identifier.
     *
     * @param string $identifier Savepoint identifier.
     *
     * @return CommandResult
     *
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function createSavepoint(string $identifier): CommandResult
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
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
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function rollbackTo(string $identifier): CommandResult
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
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
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function releaseSavepoint(string $identifier): CommandResult
    {
        return $this->query("RELEASE SAVEPOINT " . $this->quoteName($identifier));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function quoteString(string $data): string
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->quoteString($data);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception\TransactionError If the transaction has been committed or rolled back.
     */
    public function quoteName(string $name): string
    {
        if ($this->handle === null) {
            throw new Exception\TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->quoteName($name);
    }
}
