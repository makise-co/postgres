<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ConnectionConfigBuilder;
use MakiseCo\Postgres\ResultSet;

function getRandomData(): Generator
{
    for ($i = 0; $i < 10000; $i++) {
        yield [
            md5((string)random_int(100000, 900000)),
            md5((string)random_int(100000, 900000))
        ];
    }
}

function seedData(Connection $connection): void
{
    $connection->query("DROP TABLE IF EXISTS test");
    $connection->query("CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

    $stmt = $connection->prepare("INSERT INTO test VALUES (\$1, \$2)");

    foreach (getRandomData() as $row) {
        $stmt->execute($row);
    }

    $stmt->close();
}

Swoole\Coroutine\run(function () {
    $config = (new ConnectionConfigBuilder())
        ->withHost('127.0.0.1')
        ->withPort(5433)
        ->withUser('makise')
        ->withPassword('el-psy-congroo')
        ->withDatabase('makise')
        ->withApplicationName('Makise Postgres Client Benchmark')
        ->withUnbuffered(false)
        ->build();

    $connection = new Connection($config);
    $connection->connect();

//    seedData($connection);

    $start = microtime(true);

    for ($i = 0; $i < 500; $i++) {
        /* @var ResultSet $resultSet */
        $resultSet = $connection->query('SELECT * FROM test');
        while (null !== ($row = $resultSet->fetchAssoc())) {

        }
    }

    $end = microtime(true);

    $total = $end - $start;

    printf("Execution time for PHP client (buffered): %.2f secs\n", $total);
    printf("Peak memory usage for PHP client (buffered): %.2f kb\n", memory_get_peak_usage() / 1024);
});
