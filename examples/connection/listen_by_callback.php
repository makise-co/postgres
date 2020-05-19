<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

require \dirname(__DIR__) . '/../vendor/autoload.php';

use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ConnectionConfigBuilder;
use MakiseCo\Postgres\Notification;
use Swoole\Timer;

use function Swoole\Coroutine\run;

run(
    static function () {
        $config = (new ConnectionConfigBuilder())
            ->withHost('127.0.0.1')
            ->withPort(5432)
            ->withUser('makise')
            ->withPassword('el-psy-congroo')
            ->withDatabase('makise')
            ->build();

        $connection = new Connection($config);
        $connection->connect();

        $channel = "test";

        $connection->listen(
            $channel,
            static function (Notification $notification) {
                printf(
                    "Received notification from PID %d on channel '%s' with payload: %s\n",
                    $notification->pid,
                    $notification->channel,
                    $notification->payload
                );
            }
        );

        printf("Listening on channel '%s'\n", $channel);

        Timer::after(
            3000,
            function () use ($connection, $channel) { // Unlisten in 3 seconds.
                printf("Unlistening from channel '%s'\n", $channel);
                $connection->unlisten($channel);
            }
        );

        Timer::after(
            1000,
            function () use ($connection, $channel) {
                $connection->notify($channel, "Data 1"); // Send first notification.
            }
        );

        Timer::after(
            2000,
            function () use ($connection, $channel) {
                $connection->notify($channel, "Data 2"); // Send second notification.
            }
        );

        print_r("[+] Done\n");
    }
);

