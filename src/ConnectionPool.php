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
use MakiseCo\Postgres\Sql\ExecutorInterface;
use MakiseCo\Postgres\Sql\QuoterInterface;
use MakiseCo\Postgres\Sql\ReceiverInterface;
use Smf\ConnectionPool\ConnectionPool as BaseConnectionPool;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Throwable;

class ConnectionPool extends BaseConnectionPool implements ExecutorInterface, ReceiverInterface, QuoterInterface
{
    private ?Connection $listeningConnection = null;
    private int $listenersCount = 0;

    public function __construct(
        PoolConfig $poolConfig,
        ConnectionConfig $connectionConfig,
        ?ConnectorInterface $connector = null
    ) {
        if (null === $connector) {
            $connector = new Connector();
        }

        $connConfig = [
            'connection_config' => $connectionConfig,
        ];

        parent::__construct($poolConfig->toArray(), $connector, $connConfig);
    }

    /**
     * {@inheritDoc}
     */
    public function borrow(): Connection
    {
        return parent::borrow();
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, float $timeout = 0)
    {
        $connection = $this->borrow();

        try {
            $result = $connection->query($sql, $timeout);
            if ($result instanceof ResultSet) {
                return new PooledResultSet(
                    $result,
                    function () use ($connection) {
                        $this->return($connection);
                    }
                );
            }

            $this->return($connection);

            return $result;
        } catch (Throwable $e) {
            $this->return($connection);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = [], array $types = [], float $timeout = 0)
    {
        $connection = $this->borrow();

        try {
            $result = $connection->execute($sql, $params, $types, $timeout);
            if ($result instanceof ResultSet) {
                return new PooledResultSet(
                    $result,
                    function () use ($connection) {
                        $this->return($connection);
                    }
                );
            }

            $this->return($connection);

            return $result;
        } catch (Throwable $e) {
            $this->return($connection);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(string $sql, ?string $name = null, array $types = [], float $timeout = 0): PooledStatement
    {
        $connection = $this->borrow();

        try {
            $stmt = $connection->prepare($sql, $name, $types, $timeout);

            return new PooledStatement(
                $stmt,
                function () use ($connection) {
                    $this->return($connection);
                }
            );
        } catch (Throwable $e) {
            $this->return($connection);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function listen(string $channel, ?Closure $callable, float $timeout = 0): ?PooledListener
    {
        if (null !== $callable) {
            throw new InvalidArgumentException('Closures not supported on pool');
        }

        if (null === $this->listeningConnection) {
            $this->listeningConnection = $this->borrow();
        }

        try {
            $listener = $this->listeningConnection->listen($channel, $callable, $timeout);
        } catch (Throwable $e) {
            if (0 === $this->listenersCount) {
                $connection = $this->listeningConnection;
                $this->listeningConnection = null;
                $this->return($connection);
            }

            throw $e;
        }

        $this->listenersCount++;

        return new PooledListener(
            $listener,
            function () {
                if (--$this->listenersCount === 0) {
                    $connection = $this->listeningConnection;
                    $this->listeningConnection = null;
                    $this->return($connection);
                }
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function notify(string $channel, string $payload, float $timeout = 0): CommandResult
    {
        $connection = $this->borrow();

        try {
            return $connection->notify($channel, $payload, $timeout);
        } finally {
            $this->return($connection);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function quoteString(string $data): string
    {
        $connection = $this->borrow();

        try {
            return $connection->quoteString($data);
        } finally {
            $this->return($connection);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function quoteName(string $name): string
    {
        $connection = $this->borrow();

        try {
            return $connection->quoteString($name);
        } finally {
            $this->return($connection);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): PooledTransaction
    {
        $connection = $this->borrow();

        try {
            $transaction = $connection->beginTransaction($isolation);
        } catch (Throwable $e) {
            $this->return($connection);

            throw $e;
        }

        return new PooledTransaction(
            $transaction,
            function () use ($connection) {
                $this->return($connection);
            }
        );
    }
}
