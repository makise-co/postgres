<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

class Connector
{
    public function connect(ConnectConfig $config)
    {
        $connection = new Connection($config);
        $connection->connect();

        // TODO: Set encoding
        // TODO: Set timezone
        // TODO: Set search path

        return $connection;
    }
}