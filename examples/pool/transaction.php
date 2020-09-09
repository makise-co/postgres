<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

require \dirname(__DIR__) . '/../vendor/autoload.php';

use MakiseCo\Postgres\ConnectionConfigBuilder;
use MakiseCo\Postgres\PostgresPool;

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

        $pool = new PostgresPool($connectionConfig);
        $pool->setMaxActive(1);
        $pool->setMinActive(0);
        $pool->init();

        $pool->query('DROP TABLE IF EXISTS test');

        $transaction = $pool->beginTransaction();

        $transaction->query('CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))');

        $statement = $transaction->prepare('INSERT INTO test VALUES (?, ?)');

        $statement->execute(['amphp', 'org']);
        $statement->execute(['google', 'com']);
        $statement->execute(['github', 'com']);

        /** @var \MakiseCo\SqlCommon\Contracts\ResultSet $result */
        $result = $transaction->execute('SELECT * FROM test WHERE tld = :tld', ['tld' => 'com']);

        $format = "%-20s | %-10s\n";
        printf($format, 'TLD', 'Domain');
        while ($row = $result->fetchAssoc()) {
            printf($format, $row['domain'], $row['tld']);
        }

        $transaction->rollback();

        $pool->close();
    }
);
