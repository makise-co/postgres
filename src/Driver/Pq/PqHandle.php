<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use MakiseCo\Postgres\ConnectionListener;
use MakiseCo\Postgres\Contracts\Handle;
use MakiseCo\Postgres\Contracts\Listener;
use MakiseCo\Postgres\Exception\QueryExecutionError;
use MakiseCo\Postgres\Internal;
use MakiseCo\Postgres\Notification;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\ResultSet;
use MakiseCo\SqlCommon\Contracts\Statement;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use MakiseCo\SqlCommon\Exception\FailureException;
use MakiseCo\SqlCommon\Exception\QueryError;
use pq;
use Swoole\Coroutine;
use Swoole\Event;

class PqHandle implements Handle
{
    /** PostgreSQL connection object. */
    private ?pq\Connection $handle;

    /** @var Deferred|null */
    private $deferred;

    /** @var Deferred|null */
    private $busy;

    private \Closure $poll;
    private \Closure $await;

    /** @var Coroutine\Channel[] */
    private $listeners = [];

    /** @var array<string, PqStatementStorage> */
    private $statements = [];

    private int $lastUsedAt;

    /**
     * Connection constructor.
     *
     * @param pq\Connection $handle
     */
    public function __construct(pq\Connection $handle)
    {
        $this->handle = $handle;
        $this->lastUsedAt = \time();

        $handle = &$this->handle;
        $lastUsedAt = &$this->lastUsedAt;
        $deferred = &$this->deferred;
        $listeners = &$this->listeners;

        $this->poll = static function () use (&$deferred, &$lastUsedAt, &$listeners, &$handle): void {
            $lastUsedAt = \time();

            if ($handle->poll() === pq\Connection::POLLING_FAILED) {
                $exception = new ConnectionException($handle->errorMessage);
                Event::del($handle->socket);
                $handle = null; // Marks connection as dead.

                foreach ($listeners as $listener) {
                    $listener->push($exception);
                }

                if ($deferred !== null) {
                    $deferred->fail($exception);
                }

                return;
            }

            if ($deferred === null) {
                return; // No active query, only notification listeners.
            }

            if ($handle->busy) {
                return; // Not finished receiving data, poll again.
            }

            $deferred->resolve($handle->getResult());

            if (!$deferred && empty($listeners)) {
            }
        };

        $this->await = static function () use (&$deferred, &$listeners, &$handle): void {
            try {
                if (!$handle->flush()) {
                    return; // Not finished sending data, continue polling for writability.
                }
            } catch (pq\Exception $exception) {
                $exception = new ConnectionException("Flushing the connection failed", 0, $exception);
                $handle = null; // Marks connection as dead.

                foreach ($listeners as $listener) {
                    $listener->push($exception);
                }

                if ($deferred !== null) {
                    $deferred->fail($exception);
                }
            }

            // disable write event
            if ($handle) {
                Event::set($handle->socket, null, null, SWOOLE_EVENT_READ);
            }
        };

        if (!Event::add(
            $this->handle->socket,
            $this->poll,
            $this->await,
            SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE
        )) {
            throw new FailureException('Unable to add postgres event');
        }

        // disable await callback
        Event::set($this->handle->socket, null, null, SWOOLE_EVENT_READ);
    }

    /**
     * Frees Io watchers from loop.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * {@inheritdoc}
     */
    public function isAlive(): bool
    {
        return $this->handle !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastUsedAt(): int
    {
        return $this->lastUsedAt;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->deferred) {
            $deferred = $this->deferred;
            $this->deferred = null;
            $deferred->fail(new ConnectionException("The connection was closed"));
        }

        if ($this->handle !== null) {
            Event::del($this->handle->socket);
            $this->handle = null;

            foreach ($this->listeners as $listener) {
                $listener->push(new ConnectionException("The connection was closed"));
            }
        }
    }

    /**
     * @param string|null Query SQL or null if not related.
     * @param callable $method Method to execute.
     * @param mixed ...$args Arguments to pass to function.
     *
     * @return PqCommandResult|PqBufferedResultSet|PqUnbufferedResultSet|pq\Statement
     *
     * @throws FailureException
     */
    private function send(?string $sql, callable $method, ...$args)
    {
        while ($this->busy) {
            try {
                $this->busy->getResult();
            } catch (\Throwable $exception) {
                // Ignore failure from another operation.
            }
        }

        if (!$this->handle) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        try {
            $this->deferred = $this->busy = new Deferred;

            $handle = $method(...$args);

//            Loop::reference($this->poll);
            if (!$this->handle->flush()) {
//                Loop::enable($this->await);
                Event::set($this->handle->socket, null, null, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
            }

            $result = $this->deferred->getResult();
        } catch (pq\Exception $exception) {
            throw new FailureException($this->handle->errorMessage, 0, $exception);
        } finally {
            $this->deferred = $this->busy = null;
        }

        if (!$result instanceof pq\Result) {
            throw new FailureException("Unknown query result");
        }

        switch ($result->status) {
            case pq\Result::EMPTY_QUERY:
                throw new QueryError("Empty query string");

            case pq\Result::COMMAND_OK:
                if ($handle instanceof pq\Statement) {
                    return $handle; // Will be wrapped into a PqStatement object.
                }

                return new PqCommandResult($result);

            case pq\Result::TUPLES_OK:
                return new PqBufferedResultSet($result);

            case pq\Result::SINGLE_TUPLE:
                $this->busy = new Deferred;
                return new PqUnbufferedResultSet(
                    \Closure::fromCallable([$this, 'fetch']),
                    $result,
                    \Closure::fromCallable([$this, 'release'])
                );

            case pq\Result::NONFATAL_ERROR:
            case pq\Result::FATAL_ERROR:
                while ($this->handle->busy && $this->handle->getResult()) {
                }
                throw new QueryExecutionError($result->errorMessage, $result->status, $result->diag, null, $sql ?? '');

            case pq\Result::BAD_RESPONSE:
                $this->close();
                throw new FailureException($result->errorMessage);

            default:
                $this->close();
                throw new FailureException("Unknown result status");
        }
    }

    private function fetch()
    {
        if (!$this->handle->busy) { // Results buffered.
            $result = $this->handle->getResult();
        } else {
            $this->deferred = new Deferred;

//            Loop::reference($this->poll);
            if (!$this->handle->flush()) {
//                Loop::enable($this->await);
                Event::set($this->handle->socket, null, null, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
            }

            try {
                $result = $this->deferred->getResult();
            } finally {
                $this->deferred = null;
            }
        }

        if (!$result) {
            throw new ConnectionException("Connection closed");
        }

        switch ($result->status) {
            case pq\Result::TUPLES_OK: // End of result set.
                return null;

            case pq\Result::SINGLE_TUPLE:
                return $result;

            default:
                $this->close();
                throw new FailureException($result->errorMessage);
        }
    }

    private function release(): void
    {
        \assert(
            $this->busy instanceof Deferred && $this->busy !== $this->deferred,
            "Connection in invalid state when releasing"
        );

        while ($this->handle->busy && $this->handle->getResult()) {
        }

        $deferred = $this->busy;
        $this->busy = null;
        $deferred->resolve();
    }

    /**
     * Executes the named statement using the given parameters.
     *
     * @param string $name
     * @param array $params
     *
     * @return CommandResult|ResultSet
     * @throws FailureException
     */
    public function statementExecute(string $name, array $params)
    {
        \assert(isset($this->statements[$name]), "Named statement not found when executing");

        $storage = $this->statements[$name];

        \assert($storage->statement instanceof pq\Statement, "Statement storage in invalid state");

        return $this->send($storage->sql, [$storage->statement, "execAsync"], $params);
    }

    /**
     * @param string $name
     *
     * @return Promise
     *
     * @throws FailureException
     */
    public function statementDeallocate(string $name): void
    {
        if (!$this->handle) {
            return; // Connection dead.
        }

        \assert(isset($this->statements[$name]), "Named statement not found when deallocating");

        $storage = $this->statements[$name];

        if (--$storage->refCount) {
            return;
        }

        \assert($storage->statement instanceof pq\Statement, "Statement storage in invalid state");

        $storage->promise = new Promise(
            function () use ($storage, $name) {
                try {
                    return $this->send(null, [$storage->statement, "deallocateAsync"]);
                } finally {
                    unset($this->statements[$name]);
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql)
    {
        if (!$this->handle) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return $this->send($sql, [$this->handle, "execAsync"], $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $sql, array $params = [])
    {
        if (!$this->handle) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $sql = Internal\parseNamedParams($sql, $names);
        $params = Internal\replaceNamedParams($params, $names);

        return $this->send($sql, [$this->handle, "execParamsAsync"], $sql, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql): Statement
    {
        if (!$this->handle) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $modifiedSql = Internal\parseNamedParams($sql, $names);

        $name = Handle::STATEMENT_NAME_PREFIX . \sha1($modifiedSql);

        while (isset($this->statements[$name])) {
            $storage = $this->statements[$name];

            ++$storage->refCount;

            // Statement may be being allocated or deallocated. Wait to finish, then check for existence again.
            if ($storage->promise instanceof Promise) {
                // Do not return promised prepared statement object, as the $names array may differ.
                $storage->promise->wait();
                --$storage->refCount;
                continue;
            }

            return new PqStatement($this, $name, $sql, $names);
        }

        $storage = new PqStatementStorage();

        $storage->sql = $sql;

        $this->statements[$name] = $storage;

        $storage->promise = new Promise(
            function () use ($sql, $name, $modifiedSql) {
                return $this->send($sql, [$this->handle, "prepareAsync"], $name, $modifiedSql);
            }
        );

        try {
            $storage->statement = $storage->promise->getResult();
        } catch (\Throwable $exception) {
            unset($this->statements[$name]);
            throw $exception;
        } finally {
            $storage->promise = null;
        }

        return new PqStatement($this, $name, $sql, $names);
    }

    /**
     * {@inheritdoc}
     */
    public function notify(string $channel, string $payload = ""): CommandResult
    {
        $result = $this->send(null, [$this->handle, "notifyAsync"], $channel, $payload);
        if ($result instanceof CommandResult) {
            return $result;
        }

        // simulate command res
        $pqRes = new pq\Result();

        return new PqCommandResult($pqRes);
    }

    /**
     * {@inheritdoc}
     */
    public function listen(string $channel): Listener
    {
        if (isset($this->listeners[$channel])) {
            throw new QueryError(\sprintf("Already listening on channel '%s'", $channel));
        }

        $this->listeners[$channel] = $emitter = new Coroutine\Channel();

        try {
            $this->send(
                null,
                [$this->handle, "listenAsync"],
                $channel,
                static function (string $channel, string $message, int $pid) use ($emitter) {
                    $notification = new Notification($channel, $pid, $message);
                    $emitter->push($notification);
                }
            );
        } catch (\Throwable $exception) {
            unset($this->listeners[$channel]);
            throw $exception;
        }

        return new ConnectionListener(
            $emitter,
            $channel,
            \Closure::fromCallable([$this, 'unlisten'])
        );
    }

    /**
     * @param string $channel
     *
     * @throws \Error
     */
    private function unlisten(string $channel): ?CommandResult
    {
        \assert(isset($this->listeners[$channel]), "Not listening on that channel");

        $emitter = $this->listeners[$channel];
        unset($this->listeners[$channel]);

        $emitter->close();

        if (!$this->handle) {
            return null; // Connection already closed.
        }

        return $this->send(null, [$this->handle, "unlistenAsync"], $channel);
    }

    /**
     * {@inheritdoc}
     */
    public function quoteString(string $data): string
    {
        if (!$this->handle) {
            throw new \Error("The connection to the database has been closed");
        }

        return $this->handle->quote($data);
    }

    /**
     * {@inheritdoc}
     */
    public function quoteName(string $name): string
    {
        if (!$this->handle) {
            throw new \Error("The connection to the database has been closed");
        }

        return $this->handle->quoteName($name);
    }

    /**
     * @return bool True if result sets are buffered in memory, false if unbuffered.
     */
    public function isBufferingResults(): bool
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return !$this->handle->unbuffered;
    }

    /**
     * Sets result sets to be fully buffered in local memory.
     */
    public function shouldBufferResults(): void
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $this->handle->unbuffered = false;
    }

    /**
     * Sets result sets to be streamed from the database server.
     */
    public function shouldNotBufferResults(): void
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $this->handle->unbuffered = true;
    }
}
