<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\PgSql;

use MakiseCo\Connection\ConnectionConfigInterface;
use MakiseCo\Connection\ConnectionInterface;
use MakiseCo\Connection\ConnectorInterface;

class PgSqlConnector implements ConnectorInterface
{
    public function connect(ConnectionConfigInterface $config): ConnectionInterface
    {
        return PgSqlConnection::connect($config);
    }
}
