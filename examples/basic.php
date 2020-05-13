<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MakiseCo\Postgres\ConnectConfigBuilder;
use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ResultSet;

use function Swoole\Coroutine\run;

run(static function () {
    $config = (new ConnectConfigBuilder())
        ->withHost('127.0.0.1')
        ->withPort(5432)
        ->withUser('makise')
        ->withPassword('el-psy-congroo')
        ->build();

    $connection = new Connection($config);
    $connection->connect();

    /** @var ResultSet $result */
    $result = $connection->query('SHOW ALL');

    while ($row = $result->fetchAssoc()) {
        printf("%-35s = %s (%s)\n", $row['name'], $row['setting'], $row['description']);
    }
});
