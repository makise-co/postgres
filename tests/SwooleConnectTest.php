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
use MakiseCo\Postgres\Driver\Swoole\SwooleConnection;

/**
 * @requires extension swoole_postgresql
 */
class SwooleConnectTest extends AbstractConnectTest
{
    public function connect(ConnectionConfig $connectionConfig): Connection
    {
        return SwooleConnection::connect($connectionConfig);
    }
}
