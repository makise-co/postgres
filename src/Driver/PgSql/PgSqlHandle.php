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
use MakiseCo\Postgres\ConnectionListener;
use MakiseCo\Postgres\Contracts\Handle;
use MakiseCo\Postgres\Contracts\Listener;
use MakiseCo\Postgres\Driver\Pq\StatementStorage;
use MakiseCo\Postgres\Exception\QueryExecutionError;
use MakiseCo\Postgres\Notification;
use MakiseCo\Postgres\Util\Deferred;
use MakiseCo\Postgres\Internal;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\Statement;
use MakiseCo\SqlCommon\Exception\ConcurrencyException;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use MakiseCo\SqlCommon\Exception\FailureException;
use MakiseCo\SqlCommon\Exception\QueryError;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Event;

class PgSqlHandle implements Handle
{
    private const DIAGNOSTIC_CODES = [
        \PGSQL_DIAG_SEVERITY => "severity",
        \PGSQL_DIAG_SQLSTATE => "sqlstate",
        \PGSQL_DIAG_MESSAGE_PRIMARY => "message_primary",
        \PGSQL_DIAG_MESSAGE_DETAIL => "message_detail",
        \PGSQL_DIAG_MESSAGE_HINT => "message_hint",
        \PGSQL_DIAG_STATEMENT_POSITION => "statement_position",
        \PGSQL_DIAG_INTERNAL_POSITION => "internal_position",
        \PGSQL_DIAG_INTERNAL_QUERY => "internal_query",
        \PGSQL_DIAG_CONTEXT => "context",
        \PGSQL_DIAG_SOURCE_FILE => "source_file",
        \PGSQL_DIAG_SOURCE_LINE => "source_line",
        \PGSQL_DIAG_SOURCE_FUNCTION => "source_function",
    ];

    /** @var resource PostgreSQL connection handle. */
    private $handle;

    /**
     * @var int PostgreSQL Socket File Descriptor
     */
    private int $fd;

    private Deferred $deferred;
    private int $lastUsedAt;

    /**
     * Makes this handle to accept concurrent queries
     * Next query will not be executed until previous finished
     */
    private bool $isConcurrent = true;

    /**
     * @var StatementStorage[]
     */
    private array $statements = [];

    /**
     * @var Channel[]
     */
    private array $listeners = [];

    /**
     * Points to $this->unlisten()
     */
    private Closure $unlisten;

    /**
     * @param resource $handle PostgreSQL connection handle.
     * @param resource $socket PostgreSQL connection stream socket.
     */
    public function __construct($handle, $socket)
    {
        $this->handle = $handle;

        $this->deferred = new Deferred();
        $this->lastUsedAt = time();

        $this->unlisten = Closure::fromCallable([$this, 'unlisten']);

        if (!$this->fd = Event::add(
            $socket,
            Closure::fromCallable([$this, 'poll']),
            Closure::fromCallable([$this, 'await']),
            SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE
        )) {
            throw new FailureException('Unable to add postgres event');
        }

        // disable await callback
        Event::set($this->fd, null, null, SWOOLE_EVENT_READ);
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @inheritDoc
     */
    public function close()
    {
        if ($this->deferred->isWaiting()) {
            $this->deferred->fail(new ConnectionException("The connection was closed"));
        }

        $this->free();
    }

    /**
     * @inheritDoc
     */
    public function isAlive(): bool
    {
        return \is_resource($this->handle);
    }

    /**
     * @inheritDoc
     */
    public function getLastUsedAt(): int
    {
        return $this->lastUsedAt;
    }

    /**
     * @inheritDoc
     */
    public function query(string $sql)
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return $this->createResult($this->send("pg_send_query", $sql), $sql);
    }

    /**
     * @inheritDoc
     */
    public function prepare(string $sql): Statement
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $modifiedSql = Internal\parseNamedParams($sql, $names);

        $name = Handle::STATEMENT_NAME_PREFIX . sha1($modifiedSql);

        if (isset($this->statements[$name])) {
            $storage = $this->statements[$name];

            ++$storage->refCount;

            if ($storage->promise) {
                // Do not return promised prepared statement object, as the $names array may differ.
                $storage->subscribe();
            }

            return new PgSqlStatement($this, $name, $sql, $names);
        }

        $storage = new StatementStorage();
        $storage->sql = $sql;

        $this->statements[$name] = $storage;

        $storage->promise = true;
        $result = $this->send("pg_send_prepare", $name, $modifiedSql);

        switch (\pg_result_status($result, \PGSQL_STATUS_LONG)) {
            case \PGSQL_COMMAND_OK:
                // Success
                break;

            case \PGSQL_NONFATAL_ERROR:
            case \PGSQL_FATAL_ERROR:
                unset($this->statements[$name]);

                throw $this->handleResultError($result, $sql);

            case \PGSQL_BAD_RESPONSE:
                unset($this->statements[$name]);

                throw new FailureException(\pg_result_error($result));

            default:
                unset($this->statements[$name]);
                throw new FailureException("Unknown result status");
        }

        $storage->promise = false;

        $storage->wakeupSubscribers(null);

        return new PgSqlStatement($this, $name, $sql, $names);
    }

    /**
     * Executes the named statement using the given parameters.
     *
     * @param string $name
     * @param array $params
     *
     * @return CommandResult|PgSqlResultSet
     * @throws ConcurrencyException
     * @throws FailureException
     */
    public function statementExecute(string $name, array $params)
    {
        assert(isset($this->statements[$name]), "Named statement not found when executing");

        $storage = $this->statements[$name];

        return $this->createResult(
            $this->send("pg_send_execute", $name, $params),
            $storage->sql
        );
    }

    /**
     * @param string $name
     *
     * @return void
     *
     * @throws FailureException
     */
    public function statementDeallocate(string $name): void
    {
        if (!\is_resource($this->handle)) {
            return;
        }

        assert(isset($this->statements[$name]), "Named statement not found when deallocating");

        $storage = $this->statements[$name];

        if (--$storage->refCount) {
            return;
        }

        unset($this->statements[$name]);

        $this->query("DEALLOCATE {$name}");
    }

    /**
     * @inheritDoc
     */
    public function execute(string $sql, array $params = [])
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $sql = Internal\parseNamedParams($sql, $names);
        $params = Internal\replaceNamedParams($params, $names);

        return $this->createResult(
            $this->send("pg_send_query_params", $sql, $params),
            $sql
        );
    }

    /**
     * @inheritDoc
     */
    public function notify(string $channel, string $payload = ""): CommandResult
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        if ($payload === "") {
            return $this->query(\sprintf("NOTIFY %s", $this->quoteName($channel)));
        }

        return $this->query(\sprintf("NOTIFY %s, %s", $this->quoteName($channel), $this->quoteString($payload)));
    }

    /**
     * @inheritDoc
     */
    public function listen(string $channel): Listener
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        if (isset($this->listeners[$channel])) {
            throw new QueryError(\sprintf("Already listening on channel '%s'", $channel));
        }

        $this->listeners[$channel] = $emitter = new Channel();

        try {
            $this->query(\sprintf("LISTEN %s", $this->quoteName($channel)));
        } catch (\Throwable $e) {
            $emitter->close();
            unset($this->listeners[$channel]);

            throw $e;
        }

        return new ConnectionListener(
            $emitter,
            $channel,
            $this->unlisten
        );
    }

    private function unlisten(string $channel): ?CommandResult
    {
        assert(isset($this->listeners[$channel]), "Not listening on that channel");

        $emitter = $this->listeners[$channel];
        unset($this->listeners[$channel]);

        $emitter->close();

        if ($this->handle === null) {
            return null; // Connection already closed.
        }

        return $this->query(\sprintf("UNLISTEN %s", $this->quoteName($channel)));
    }

    /**
     * @inheritDoc
     */
    public function quoteString(string $data): string
    {
        if (!\is_resource($this->handle)) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return \pg_escape_literal($this->handle, $data);
    }

    /**
     * @inheritDoc
     */
    public function quoteName(string $name): string
    {
        if (!\is_resource($this->handle)) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return \pg_escape_identifier($this->handle, $name);
    }

    /**
     * @param callable $function Function name to execute.
     * @param mixed ...$args Arguments to pass to function.
     *
     * @return resource
     *
     * @throws FailureException
     */
    private function send(callable $function, ...$args)
    {
        if ($this->isConcurrent) {
            // wait previous query execution done
            while ($this->deferred->isWaiting()) {
                $this->deferred->subscribe();
            }
        } elseif ($this->deferred->isWaiting()) {
            throw new ConcurrencyException('Concurrent requests are not allowed');
        }

        if (!\is_resource($this->handle)) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $result = $function($this->handle, ...$args);

        if ($result === false) {
            throw new FailureException(\pg_last_error($this->handle));
        }

        if (0 === $result) {
            Event::set($this->fd, null, null, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
        }

        $result = $this->deferred->wait();

        return $result;
    }

    /**
     * @param resource $result PostgreSQL result resource.
     * @param string $sql Query SQL.
     *
     * @return CommandResult|PgSqlResultSet
     *
     * @throws FailureException
     * @throws QueryError
     */
    private function createResult($result, string $sql)
    {
        switch (\pg_result_status($result, \PGSQL_STATUS_LONG)) {
            case \PGSQL_EMPTY_QUERY:
                throw new QueryError("Empty query string");

            case \PGSQL_COMMAND_OK:
                return new PgSqlCommandResult($result);

            case \PGSQL_TUPLES_OK:
                return new PgSqlResultSet($result);

            case \PGSQL_NONFATAL_ERROR:
            case \PGSQL_FATAL_ERROR:
                throw $this->handleResultError($result, $sql);

            case \PGSQL_BAD_RESPONSE:
                $this->close();
                throw new FailureException(\pg_result_error($result));

            default:
                $this->close();
                throw new FailureException("Unknown result status");
        }
    }

    private function handleResultError($result, string $sql): QueryExecutionError
    {
        $diagnostics = [];

        foreach (self::DIAGNOSTIC_CODES as $fieldCode => $description) {
            $diagnostics[$description] = \pg_result_error_field($result, $fieldCode);
        }

        $message = \pg_result_error($result);
        $code = \pg_result_status($result, \PGSQL_STATUS_LONG);

        while (\pg_connection_busy($this->handle) && $result = \pg_get_result($this->handle)) {
            \pg_free_result($result);
        }

        return new QueryExecutionError($message, $code, $diagnostics, null, $sql);
    }

    private function free(): void
    {
        if ($this->fd > 0) {
            Event::del($this->fd);
            $this->fd = 0;
        }

        $handle = $this->handle;
        if (\is_resource($handle)) {
            \pg_close($handle);
            $this->handle = null;

            foreach ($this->listeners as $listener) {
                $listener->push(new ConnectionException("The connection was closed"));
            }
        }
    }

    private function poll(): void
    {
        $this->lastUsedAt = time();

        $handle = $this->handle;

        if (!\pg_consume_input($handle)) {
            $exception = new ConnectionException(\pg_last_error($handle));
            $this->handle = null; // Marks connection as dead.

            Event::del($this->fd);
            $this->fd = 0;

            foreach ($this->listeners as $listener) {
                Coroutine::create(
                    Closure::fromCallable([$listener, 'push']),
                    $exception
                );
            }

            if ($this->deferred->isWaiting()) {
                $this->deferred->fail($exception);
            }

            return;
        }

        while ($result = \pg_get_notify($handle, \PGSQL_ASSOC)) {
            $channel = $result["message"];

            $listener = $this->listeners[$channel] ?? null;
            if (null === $listener) {
                continue;
            }

            Coroutine::create(
                Closure::fromCallable([$listener, 'push']),
                new Notification($channel, $result['pid'], $result['payload'])
            );
        }

        if (!$this->deferred->isWaiting()) {
            return; // No active query, only notification listeners.
        }

        if (\pg_connection_busy($handle)) {
            return; // Not finished receiving data, poll again.
        }

        $this->deferred->resolve(\pg_get_result($handle));
    }

    private function await(): void
    {
        $handle = $this->handle;

        $flush = \pg_flush($handle);
        if ($flush === 0) {
            return; // Not finished sending data, listen again.
        }

        // disable write event
        Event::set($this->fd, null, null, SWOOLE_EVENT_READ);

        if ($flush === false) {
            $exception = new ConnectionException(\pg_last_error($handle));
            $this->handle = null; // Marks connection as dead.

            foreach ($this->listeners as $listener) {
                Coroutine::create(
                    Closure::fromCallable([$listener, 'push']),
                    $exception
                );
            }

            if ($this->deferred->isWaiting()) {
                $this->deferred->fail($exception);
            }
        }
    }
}
