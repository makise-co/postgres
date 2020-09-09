<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ConnectionConfig;
use MakiseCo\Postgres\Driver\Pq\PqConnection;

/**
 * @requires extension pq
 */
class PqConnectTest extends AbstractConnectTest
{
    public function connect(ConnectionConfig $connectionConfig): Connection
    {
        return PqConnection::connect($connectionConfig);
    }
}
