<?php

declare(strict_types=1);

require \dirname(__DIR__) . '/vendor/autoload.php';

use MakiseCo\Postgres\ConnectConfigBuilder;
use MakiseCo\Postgres\Connection;

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

    $connection->query('DROP TABLE IF EXISTS test');

    $transaction = $connection->beginTransaction();

    $transaction->query('CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))');

    $statement = $transaction->prepare('INSERT INTO test VALUES (?, ?)');

    $statement->execute(['amphp', 'org']);
    $statement->execute(['google', 'com']);
    $statement->execute(['github', 'com']);

    /** @var \MakiseCo\Postgres\ResultSet $result */
    $result = $transaction->execute('SELECT * FROM test WHERE tld = :tld', ['tld' => 'com']);

    $format = "%-20s | %-10s\n";
    printf($format, 'TLD', 'Domain');
    while ($row = $result->fetchAssoc()) {
        printf($format, $row['domain'], $row['tld']);
    }

    $transaction->rollback();
});
