<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\PgSql;

use Closure;
use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\ConnectionConfig;
use MakiseCo\Postgres\Util\Deferred;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use MakiseCo\SqlCommon\Exception\FailureException;
use Swoole\Event;
use Swoole\Timer;

final class PgSqlConnection extends Connection
{
    public static function connect(ConnectionConfig $config): self
    {
        if (!$connection = @\pg_connect(
            $config->getConnectionString(),
            \PGSQL_CONNECT_ASYNC | \PGSQL_CONNECT_FORCE_NEW
        )) {
            throw new ConnectionException("Failed to create connection resource");
        }

        if (\pg_connection_status($connection) === \PGSQL_CONNECTION_BAD) {
            throw new ConnectionException(\pg_last_error($connection));
        }

        if (!$socket = \pg_socket($connection)) {
            throw new ConnectionException("Failed to access connection socket");
        }

        $deferred = new Deferred();

        $callback = static function () use ($connection, $socket, $deferred): void {
            switch (\pg_connect_poll($connection)) {
                case \PGSQL_POLLING_READING: // Connection not ready, poll again.
                case \PGSQL_POLLING_WRITING: // Still writing...
                    return;

                case \PGSQL_POLLING_FAILED:
                    $deferred->fail(new ConnectionException(\pg_last_error($connection)));
                    return;

                case \PGSQL_POLLING_OK:
                    Event::del($socket);
                    $deferred->resolve(new self($connection, $socket));
                    return;
            }
        };

        if (!Event::add($socket, $callback, $callback, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE)) {
            throw new FailureException('Unable to register src event');
        }

        $tid = null;
        if ($config->getConnectTimeout() > 0) {
            $tid = Timer::after(
                (int)($config->getConnectTimeout() * 1000),
                Closure::fromCallable([$deferred, 'fail']),
                new ConnectionException('Connection is timed out')
            );
        }

        try {
            return $deferred->wait();
        } catch (\Throwable $e) {
            Event::del($socket);

            throw $e;
        } finally {
            if ($tid > 0) {
                Timer::clear($tid);
            }
        }
    }

    /**
     * @param resource $handle PostgreSQL connection handle.
     * @param resource $socket PostgreSQL connection stream socket.
     */
    public function __construct($handle, $socket)
    {
        parent::__construct(new PgSqlHandle($handle, $socket));
    }
}
