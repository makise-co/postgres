<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use MakiseCo\Postgres\ConnectionConfigBuilder;

function getRandomData(): Generator
{
    for ($i = 0; $i < 10000; $i++) {
        yield [
            md5((string)random_int(100000, 900000)),
            md5((string)random_int(100000, 900000))
        ];
    }
}

function seedData(\Swoole\Coroutine\PostgreSQL $connection): void
{
    $connection->query("DROP TABLE IF EXISTS test");
    $connection->query("CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

    $connection->prepare("insert", "INSERT INTO test VALUES (\$1, \$2)");

    foreach (getRandomData() as $row) {
        $connection->execute("insert", $row);
    }

    $connection->query("DEALLOCATE insert");
}

Swoole\Coroutine\run(function () {
    $config = (new ConnectionConfigBuilder())
        ->withHost('127.0.0.1')
        ->withPort(5433)
        ->withUser('makise')
        ->withPassword('el-psy-congroo')
        ->withDatabase('makise')
        ->withApplicationName('Makise Postgres Client Benchmark')
        ->withUnbuffered(true)
        ->build();

    $connection = new \Swoole\Coroutine\PostgreSQL();
    $connection->connect($config->__toString());

//    seedData($connection);

    $start = microtime(true);

    for ($i = 0; $i < 500; $i++) {
        $result = $connection->query('SELECT * FROM test');

        while (false !== ($row = $connection->fetchAssoc($result))) {
        }
    }

    $end = microtime(true);

    $total = $end - $start;

    printf("Execution time for Swoole client: %.2f secs\n", $total);
    printf("Peak memory usage for Swoole client: %.2f kb\n", memory_get_peak_usage() / 1024);
});
