<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

require \dirname(__DIR__) . '/../vendor/autoload.php';

use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ConnectionConfigBuilder;

use MakiseCo\Postgres\ConnectionPool;
use MakiseCo\Postgres\PoolConfig;

use function Swoole\Coroutine\run;

run(
    static function () {
        $connectionConfig = (new ConnectionConfigBuilder())
            ->withHost('127.0.0.1')
            ->withPort(5432)
            ->withUser('makise')
            ->withPassword('el-psy-congroo')
            ->withDatabase('makise')
            ->build();

        $poolConfig = new PoolConfig(0, 1);

        $pool = new ConnectionPool($poolConfig, $connectionConfig);
        $pool->init();

        $connection = $pool->borrow();
        $result = $connection->query('SELECT 1');

        print_r($result->fetchAll());

        $pool->return($connection);

        $pool->close();
    }
);
