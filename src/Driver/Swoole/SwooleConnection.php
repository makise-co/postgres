<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Swoole;

use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ConnectionConfig;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use Swoole\Coroutine\PostgreSQL;

final class SwooleConnection extends Connection
{
    public function __construct(PostgreSQL $handle)
    {
        parent::__construct(new SwooleHandle($handle));
    }

    /**
     * @inheritDoc
     */
    public static function connect(ConnectionConfig $connectionConfig): Connection
    {
        $handle = new PostgreSQL();
        $handle->connect($connectionConfig->getConnectionString());

        if ($handle->error !== null) {
            throw new ConnectionException($handle->error);
        }

        return new self($handle);
    }
}
