<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

use MakiseCo\Postgres\Connection;

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

    unset($stmt);
}
