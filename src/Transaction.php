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
     * {@inheritDoc}
     */
    public function isActive(): bool
    {
        return $this->handle !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function getIsolationLevel(): int
    {
        return $this->isolation;
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, float $timeout = 0)
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->query($sql, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(string $sql, ?string $name = null, array $types = [], float $timeout = 0): Statement
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->prepare($sql, $name, $types, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = [], array $types = [], float $timeout = 0)
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->execute($sql, $params, $types, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function notify(string $channel, string $payload, float $timeout = 0): CommandResult
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->notify($channel, $payload, $timeout);
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function createSavepoint(string $identifier): CommandResult
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->query("SAVEPOINT " . $this->quoteName($identifier));
    }

    /**
     * {@inheritDoc}
     */
    public function rollbackTo(string $identifier): CommandResult
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->query("ROLLBACK TO " . $this->quoteName($identifier));
    }

    /**
     * {@inheritDoc}
     */
    public function releaseSavepoint(string $identifier): CommandResult
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->query("RELEASE SAVEPOINT " . $this->quoteName($identifier));
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): Transaction
    {
        if ($this->handle === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->handle->beginTransaction($isolation);
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
