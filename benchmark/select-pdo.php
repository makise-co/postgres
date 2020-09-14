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

Swoole\Coroutine\run(function () {
    $pdo = new PDO(
        'pgsql:host=127.0.0.1;port=5434;dbname=makise',
        'makise',
        'el-psy-congroo',
        [
            'application_name' => 'Makise Postgres Client Benchmark',
            'client_encoding' => 'utf-8',
            'options' => '-ctimezone=UTC -csearch_path=public'
        ]
    );

    $start = microtime(true);

    for ($i = 0; $i < 100; $i++) {
        $stmt = $pdo->query('SELECT * FROM test');
        while ($row = $stmt->fetch()) {
        }
    }

    $end = microtime(true);

    $total = $end - $start;

    printf("Execution time for PDO: %f secs\n", $total);
    printf("Peak memory usage for PDO: %.2f kb\n", memory_get_peak_usage() / 1024);
});
