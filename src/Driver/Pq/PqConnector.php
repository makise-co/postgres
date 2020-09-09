<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use MakiseCo\Connection\ConnectionConfigInterface;
use MakiseCo\Connection\ConnectorInterface;

class PqConnector implements ConnectorInterface
{
    public function connect(ConnectionConfigInterface $config): PqConnection
    {
        return PqConnection::connect($config);
    }
}
