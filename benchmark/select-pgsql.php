<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once 'random_data.php';

use MakiseCo\Postgres\ConnectionConfigBuilder;
use MakiseCo\Postgres\Driver\PgSql\PgSqlConnection;
use MakiseCo\SqlCommon\Contracts\ResultSet;

Swoole\Coroutine\run(function () {
    $config = (new ConnectionConfigBuilder())
        ->withHost('127.0.0.1')
        ->withPort(5434)
        ->withUser('makise')
        ->withPassword('el-psy-congroo')
        ->withDatabase('makise')
        ->withApplicationName('Makise Postgres Client Benchmark')
        ->withUnbuffered(false)
        ->build();

    $connection = PgSqlConnection::connect($config);
//    seedData($connection);

    // preload code
    $connection->query('SELECT 1');

    $start = microtime(true);

    for ($i = 0; $i < 100; $i++) {
        /* @var ResultSet $resultSet */
        $resultSet = $connection->query('SELECT * FROM test');
        while (null !== ($row = $resultSet->fetchAssoc())) {

        }
    }

    $end = microtime(true);

    $total = $end - $start;

    printf("Execution time for PHP client (PgSql): %f secs\n", $total);
    printf("Peak memory usage for PHP client (PgSql): %.2f kb\n", memory_get_peak_usage() / 1024);
});
