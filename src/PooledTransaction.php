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
use MakiseCo\Postgres\Exception\TransactionError;
use MakiseCo\Postgres\Sql\TransactionInterface;

class PooledTransaction implements TransactionInterface
{
    private ?Transaction $transaction;
    private Closure $release;
    private int $refCount = 1;

    public function __construct(Transaction $transaction, Closure $release)
    {
        $this->release = $release;

        if ($transaction->isActive()) {
            $this->transaction = $transaction;

            $refCount = &$this->refCount;
            $this->release = static function () use (&$refCount, $release) {
                if (--$refCount === 0) {
                    $release();
                }
            };
        } else {
            $release();
            $this->transaction = null;
        }
    }

    public function __destruct()
    {
        if ($this->transaction) {
            try {
                $this->transaction = null;
            } finally {
                ($this->release)();
            }
        }
    }

    public function close(): void
    {
        if (!$this->transaction) {
            return;
        }

        try {
            $this->transaction->commit();
            $this->transaction = null;
        } finally {
            ($this->release)();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, float $timeout = 0)
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        $result = $this->transaction->query($sql, $timeout);

        if ($result instanceof ResultSet) {
            $this->refCount++;

            return new PooledResultSet($result, $this->release);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = [], array $types = [], float $timeout = 0)
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        $result = $this->transaction->execute($sql, $params, $types, $timeout);

        if ($result instanceof ResultSet) {
            $this->refCount++;

            return new PooledResultSet($result, $this->release);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(string $sql, ?string $name = null, array $types = [], float $timeout = 0): PooledStatement
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        $stmt = $this->transaction->prepare($sql, $name, $types, $timeout);
        $this->refCount++;

        return new PooledStatement($stmt, $this->release);
    }

    /**
     * {@inheritDoc}
     */
    public function notify(string $channel, string $payload, float $timeout = 0): CommandResult
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->notify($channel, $payload, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function getIsolationLevel(): int
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->getIsolationLevel();
    }

    /**
     * {@inheritDoc}
     */
    public function isActive(): bool
    {
        return $this->transaction && $this->transaction->isActive();
    }

    /**
     * {@inheritDoc}
     */
    public function commit(): CommandResult
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        try {
            $result = $this->transaction->commit();
        } finally {
            $this->transaction = null;
            ($this->release)();
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function rollback(): CommandResult
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        try {
            $result = $this->transaction->rollback();
        } finally {
            $this->transaction = null;
            ($this->release)();
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function createSavepoint(string $identifier): CommandResult
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->createSavepoint($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function rollbackTo(string $identifier): CommandResult
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->rollbackTo($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function releaseSavepoint(string $identifier): CommandResult
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->releaseSavepoint($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): PooledTransaction
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        $result = $this->transaction->beginTransaction($isolation);

        $this->refCount++;

        return new PooledTransaction($result, $this->release);
    }

    /**
     * {@inheritDoc}
     */
    public function quoteString(string $data): string
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->quoteString($data);
    }

    /**
     * {@inheritDoc}
     */
    public function quoteName(string $name): string
    {
        if (!$this->transaction) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->quoteName($name);
    }
}
