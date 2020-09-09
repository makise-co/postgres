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
use Error;
use MakiseCo\Postgres\Contracts\Handle;
use MakiseCo\Postgres\Contracts\Link;
use MakiseCo\Postgres\Contracts\Listener;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\Statement;
use MakiseCo\Postgres\Contracts\Transaction;
use MakiseCo\Postgres\Util\Deferred;

abstract class Connection implements Link, Handle
{
    protected Handle $handle;

    /** Used to only allow one transaction at a time. */
    private Deferred $busy;

    /**
     * @param ConnectionConfig $connectionConfig
     * @return Connection
     */
    abstract public static function connect(ConnectionConfig $connectionConfig): Connection;

    /**
     * @param Handle $handle
     */
    public function __construct(Handle $handle)
    {
        $this->handle = $handle;
//        $this->busy = new Deferred();
    }

    public function __destruct()
    {
        $this->handle->close();
    }

    /**
     * {@inheritdoc}
     */
    final public function isAlive(): bool
    {
        return $this->handle->isAlive();
    }

    /**
     * {@inheritdoc}
     */
    final public function getLastUsedAt(): int
    {
        return $this->handle->getLastUsedAt();
    }

    /**
     * {@inheritdoc}
     */
    final public function close(): void
    {
        $this->handle->close();
    }

    /**
     * Wait for transaction complete
     */
    private function waitForTransaction(): void
    {
        while ($this->busy->isWaiting()) {
            $this->busy->subscribe();
        }
    }

    /**
     * Reserves the connection for a transaction.
     */
    private function reserve(): void
    {
//        $this->busy->lock();
    }

    /**
     * Releases the transaction lock.
     */
    private function release(): void
    {
//        $this->busy->unlock();
    }

    /**
     * {@inheritdoc}
     */
    final public function query(string $sql)
    {
//        $this->waitForTransaction();

        return $this->handle->query($sql);
    }

    /**
     * {@inheritdoc}
     */
    final public function execute(string $sql, array $params = [])
    {
//        $this->waitForTransaction();

        return $this->handle->execute($sql, $params);
    }

    /**
     * {@inheritdoc}
     */
    final public function prepare(string $sql): Statement
    {
//        $this->waitForTransaction();

        return $this->handle->prepare($sql);
    }

    /**
     * {@inheritdoc}
     */
    final public function notify(string $channel, string $payload = ""): CommandResult
    {
//        $this->waitForTransaction();

        return $this->handle->notify($channel, $payload);
    }

    /**
     * {@inheritdoc}
     */
    final public function listen(string $channel): Listener
    {
//        $this->waitForTransaction();

        return $this->handle->listen($channel);
    }

    /**
     * {@inheritdoc}
     */
    final public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): Transaction
    {
//        $this->reserve();

//        try {
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
                    throw new Error("Invalid transaction type");
            }
//        } catch (Throwable $exception) {
//            $this->release();
//
//            throw $exception;
//        }

        return new ConnectionTransaction($this->handle, Closure::fromCallable([$this, 'release']), $isolation);
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

