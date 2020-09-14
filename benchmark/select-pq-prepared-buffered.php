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
use MakiseCo\Postgres\Driver\Pq\PqConnection;
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

    $connection = PqConnection::connect($config);

//    seedData($connection);

    $start = microtime(true);

    $stmt = $connection->prepare('SELECT * FROM test');

    for ($i = 0; $i < 500; $i++) {
        /* @var ResultSet $resultSet */
        $resultSet = $stmt->execute();
        while (null !== ($row = $resultSet->fetchAssoc())) {

        }
    }

    unset($stmt);

    $end = microtime(true);

    $total = $end - $start;

    printf("Execution time for PHP client (Pq, buffered): %.2f secs\n", $total);
    printf("Peak memory usage for PHP client (Pq, buffered): %.2f kb\n", memory_get_peak_usage() / 1024);
});
