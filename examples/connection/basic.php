<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

require dirname(__DIR__) . '/../vendor/autoload.php';

use MakiseCo\Postgres\ConnectionConfigBuilder;
use MakiseCo\Postgres\Driver\Pq\PqConnection;
use MakiseCo\SqlCommon\Contracts\ResultSet;

use function Swoole\Coroutine\run;

run(
    static function () {
        $config = (new ConnectionConfigBuilder())
            ->withHost('127.0.0.1')
            ->withPort(5432)
            ->withUser('makise')
            ->withPassword('el-psy-congroo')
            ->withDatabase('makise')
            ->build();

        $connection = PqConnection::connect($config);

        /** @var ResultSet $result */
        $result = $connection->query('SHOW ALL');

        while ($row = $result->fetchAssoc()) {
            printf("%-35s = %s (%s)\n", $row['name'], $row['setting'], $row['description']);
        }
    }
);
