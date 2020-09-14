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

function seedDataSwoole(\Swoole\Coroutine\PostgreSQL $connection): void
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
    $connection = new \Swoole\Coroutine\PostgreSQL();
    $connection->connect("host=127.0.0.1 port=5434 user=makise password=el-psy-congroo dbname=makise");

//    seedDataSwoole($connection);

    $start = microtime(true);

    for ($i = 0; $i < 100; $i++) {
        $result = $connection->query('SELECT * FROM test');

        while ($row = $connection->fetchAssoc($result)) {
        }
    }

    $end = microtime(true);

    $total = $end - $start;

    printf("Execution time for Swoole client: %f secs\n", $total);
    printf("Peak memory usage for Swoole client: %.2f kb\n", memory_get_peak_usage() / 1024);
});
