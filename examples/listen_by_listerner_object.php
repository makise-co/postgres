<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

require \dirname(__DIR__) . '/vendor/autoload.php';

use MakiseCo\Postgres\ConnectConfigBuilder;
use MakiseCo\Postgres\Connection;
use Swoole\Timer;

use function Swoole\Coroutine\run;

\Swoole\Coroutine::set(['log_level' => SWOOLE_LOG_INFO]);
run(static function () {
    $config = (new ConnectConfigBuilder())
        ->withHost('127.0.0.1')
        ->withPort(5432)
        ->withUser('makise')
        ->withPassword('el-psy-congroo')
        ->build();

    $connection = new Connection($config);
    $connection->connect();

    $channel = "test";

    /* @var \MakiseCo\Postgres\Listener $listener */
    $listener = $connection->listen($channel, null);

    printf("Listening on channel '%s'\n", $channel);

    Timer::after(3000, function () use ($listener) { // Unlisten in 3 seconds.
        printf("Unlistening from channel '%s'\n", $listener->getChannel());
        $listener->close();
    });

    Timer::after(1000, function () use ($connection, $channel) {
        $connection->notify($channel, "Data 1"); // Send first notification.
    });

    Timer::after(2000, function () use ($connection, $channel) {
        $connection->notify($channel, "Data 2"); // Send second notification.
    });

    while ($notification = $listener->getNotification()) {
        printf(
            "Received notification from PID %d on channel '%s' with payload: %s\n",
            $notification->pid,
            $notification->channel,
            $notification->payload
        );
    }

    print_r("[+] Done\n");
});

