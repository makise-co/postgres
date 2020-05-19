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
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Throwable;

final class Connector implements ConnectorInterface
{
    /**
     * {@inheritDoc}
     */
    public function connect(array $config): Connection
    {
        $config = $config['connection_config'];
        if (!$config instanceof ConnectionConfig) {
            throw new InvalidArgumentException('connection_config must be an instance of ConnectionConfig');
        }

        $conn = new Connection($config);
        try {
            $conn->connect();
        } catch (Throwable $e) {
            // ignore connection errors
        }

        return $conn;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect($connection): void
    {
        /* @var Connection $connection */
        $connection->disconnect();
    }

    /**
     * {@inheritDoc}
     */
    public function isConnected($connection): bool
    {
        /* @var Connection $connection */
        return $connection->isConnected();
    }

    /**
     * {@inheritDoc}
     */
    public function reset($connection, array $config): void
    {
        /* @var Connection $connection */
    }

    /**
     * {@inheritDoc}
     */
    public function validate($connection): bool
    {
        return $connection instanceof Connection;
    }
}
