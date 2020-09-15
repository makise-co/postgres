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
use MakiseCo\Connection\ConnectorInterface;
use MakiseCo\Postgres\Contracts\Link;
use MakiseCo\Postgres\Contracts\Listener;
use MakiseCo\Postgres\Driver\PgSql\PgSqlConnector;
use MakiseCo\Postgres\Driver\Pq\PqConnector;
use MakiseCo\Postgres\Driver\Swoole\SwooleConnector;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\Postgres\Contracts\Transaction;
use MakiseCo\SqlCommon\Contracts\Transaction as SqlTransaction;
use MakiseCo\SqlCommon\DatabasePool;

use function extension_loaded;

class PostgresPool extends DatabasePool implements Link
{
    private ?Connection $listeningConnection = null;
    private int $listenerCount = 0;

    /**
     * @param ConnectionTransaction $transaction
     * @param Closure $release
     * @return Transaction
     */
    protected function createTransaction(SqlTransaction $transaction, Closure $release): Transaction
    {
        return new PooledTransaction($transaction, $release);
    }

    protected function pop(): Connection
    {
        /** @var Connection */
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::pop();
    }

    public function close(): void
    {
        if ($this->listeningConnection !== null) {
            $listeningConnection = $this->listeningConnection;
            $this->listeningConnection = null;

            $this->push($listeningConnection);
        }

        parent::close();
    }

    public function beginTransaction(int $isolation = SqlTransaction::ISOLATION_COMMITTED): Transaction
    {
        /** @var PooledTransaction */
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::beginTransaction($isolation);
    }

    /**
     * @inheritDoc
     */
    public function notify(string $channel, string $payload = ""): CommandResult
    {
        $connection = $this->pop();

        try {
            return $connection->notify($channel, $payload);
        } finally {
            $this->push($connection);
        }
    }

    protected function createDefaultConnector(): ConnectorInterface
    {
        if (extension_loaded('swoole_postgresql')) {
            return new SwooleConnector();
        }

        if (extension_loaded('pq')) {
            return new PqConnector();
        }

        if (extension_loaded('pgsql')) {
            return new PgSqlConnector();
        }

        throw new \RuntimeException('Please install pq or pgsql extension');
    }

    /**
     * @inheritDoc
     */
    public function listen(string $channel): Listener
    {
        ++$this->listenerCount;

        if (null === $this->listeningConnection) {
            $this->listeningConnection = $this->pop();
        }

        try {
            $listener = $this->listeningConnection->listen($channel);
        } catch (\Throwable $e) {
            if (--$this->listenerCount === 0) {
                $connection = $this->listeningConnection;
                $this->listeningConnection = null;
                $this->push($connection);
            }

            throw $e;
        }

        return new PooledListener($listener, function () {
            if (--$this->listenerCount === 0 && $this->listeningConnection !== null) {
                $connection = $this->listeningConnection;
                $this->listeningConnection = null;
                $this->push($connection);
            }
        });
    }
}
