<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use Closure;
use Error;
use MakiseCo\Postgres\ConnectionListener;
use MakiseCo\Postgres\Contracts\Handle;
use MakiseCo\Postgres\Contracts\Listener;
use MakiseCo\Postgres\Exception;
use MakiseCo\Postgres\Internal;
use MakiseCo\Postgres\Notification;
use MakiseCo\Postgres\Util\Deferred;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\ResultSet;
use MakiseCo\SqlCommon\Contracts\Statement;
use MakiseCo\SqlCommon\Exception\ConcurrencyException;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use MakiseCo\SqlCommon\Exception\FailureException;
use MakiseCo\SqlCommon\Exception\QueryError;
use pq;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Event;
use Throwable;

use function assert;
use function sha1;
use function sprintf;
use function time;

class PqHandle implements Handle
{
    private ?pq\Connection $handle;
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
     * Points to $this->fetch()
     *
     * Used for unbuffered results fetching
     */
    private Closure $fetch;

    /**
     * Points to $this->release()
     *
     * Used for continuation of blocked coroutines after unbuffered results fetching done
     */
    private Closure $release;

    /**
     * Points to $this->unlisten()
     */
    private Closure $unlisten;

    public function __construct(pq\Connection $connection)
    {
        $this->handle = $connection;

        $this->deferred = new Deferred();
        $this->lastUsedAt = time();

        $this->fetch = Closure::fromCallable([$this, 'fetch']);
        $this->release = Closure::fromCallable([$this, 'release']);
        $this->unlisten = Closure::fromCallable([$this, 'unlisten']);

        if (!Event::add(
            $this->handle->socket,
            Closure::fromCallable([$this, 'poll']),
            Closure::fromCallable([$this, 'await']),
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
     * @inheritDoc
     */
    public function isAlive(): bool
    {
        return $this->handle !== null;
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
    public function close(): void
    {
        if ($this->deferred->isWaiting()) {
            $this->deferred->fail(new ConnectionException("The connection was closed"));
        }

        $this->free();
    }

    /**
     * @inheritDoc
     */
    public function query(string $sql)
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return $this->send($sql, [$this->handle, "execAsync"], $sql);
    }

    /**
     * @inheritDoc
     */
    public function prepare(string $sql): Statement
    {
        if (!$this->handle) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $modifiedSql = Internal\parseNamedParams($sql, $names);

        $name = Handle::STATEMENT_NAME_PREFIX . sha1($modifiedSql);

        // prevent returning of promised statement which is in de-allocation state
        while (isset($this->statements[$name])) {
            $storage = $this->statements[$name];

            ++$storage->refCount;

            // Statement may be being allocated or deallocated. Wait to finish, then check for existence again.
            if ($storage->lock->isLocked()) {
                // Do not return promised prepared statement object, as the $names array may differ.
                $storage->lock->wait();

                --$storage->refCount;

                if ($storage->error !== null) {
                    throw $storage->error;
                }

                continue;
            }

            return new PqStatement($this, $name, $sql, $names);
        }

        $storage = new StatementStorage();
        $storage->sql = $sql;

        $this->statements[$name] = $storage;

        $storage->lock->lock();

        try {
            $storage->statement = $this->send(
                $sql,
                [$this->handle, "prepareAsync"],
                $name,
                $modifiedSql
            );
        } catch (Throwable $exception) {
            unset($this->statements[$name]);

            $storage->error = $exception;

            throw $exception;
        } finally {
            $storage->isAllocating = false;
            $storage->isDeallocating = false;
            $storage->lock->unlock();
        }

        return new PqStatement($this, $name, $sql, $names);
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

        return $this->send($sql, [$this->handle, "execParamsAsync"], $sql, $params);
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
            throw new QueryError(sprintf("Already listening on channel '%s'", $channel));
        }

        $this->listeners[$channel] = $emitter = new Channel();

        try {
            $this->send(
                null,
                [$this->handle, "listenAsync"],
                $channel,
                static function (string $channel, string $message, int $pid) use ($emitter) {
                    Coroutine::create(
                        Closure::fromCallable([$emitter, 'push']),
                        new Notification($channel, $pid, $message)
                    );
                }
            );
        } catch (Throwable $exception) {
            $emitter->close();
            unset($this->listeners[$channel]);

            throw $exception;
        }

        return new ConnectionListener(
            $emitter,
            $channel,
            $this->unlisten
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

        $this->send(null, [$this->handle, "notifyAsync"], $channel, $payload);

        return new PqRawCommandResult();
    }

    /**
     * @inheritDoc
     */
    public function quoteString(string $data): string
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return $this->handle->quote($data);
    }

    /**
     * @inheritDoc
     */
    public function quoteName(string $name): string
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
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

    /**
     * @return bool True if this handle can process pseudo-concurrent queries
     */
    public function isConcurrent(): bool
    {
        return $this->isConcurrent;
    }

    /**
     * Allow to process concurrent queries
     */
    public function enableConcurrency(): void
    {
        $this->isConcurrent = true;
    }

    /**
     * Fail with ConcurrencyException on concurrent queries immediately
     */
    public function disableConcurrency(): void
    {
        $this->isConcurrent = false;
    }

    /**
     * Executes the named statement using the given parameters.
     *
     * @param string $name
     * @param array $params
     *
     * @return CommandResult|ResultSet
     * @throws ConcurrencyException
     * @throws FailureException
     */
    public function statementExecute(string $name, array $params)
    {
        assert(isset($this->statements[$name]), "Named statement not found when executing");

        $storage = $this->statements[$name];

        assert($storage->statement instanceof pq\Statement, "Statement storage in invalid state");

        return $this->send($storage->sql, [$storage->statement, "execAsync"], $params);
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
        if ($this->handle === null) {
            return;
        }

        assert(isset($this->statements[$name]), "Named statement not found when deallocating");

        $storage = $this->statements[$name];

        if (--$storage->refCount) {
            return;
        }

        $storage->lock->lock();
        $storage->isDeallocating = true;

        try {
            $this->send(null, [$storage->statement, "deallocateAsync"]);
        } finally {
            unset($this->statements[$name]);

            $storage->isDeallocating = false;
            $storage->lock->unlock();
        }
    }

    private function free(): void
    {
        $handle = $this->handle;

        if ($handle !== null) {
            $this->handle = null;
            Event::del($handle->socket);

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
     * @throws ConcurrencyException
     * @throws FailureException
     */
    private function send(?string $sql, callable $method, ...$args)
    {
        if ($this->isConcurrent) {
            // wait previous query execution done
            while ($this->deferred->isWaiting()) {
                $this->deferred->subscribe();
            }
        } elseif ($this->deferred->isWaiting()) {
            throw new ConcurrencyException('Concurrent requests are not allowed');
        }

        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        try {
            $handle = $method(...$args);

//            Event::set($this->handle->socket, null, null, SWOOLE_EVENT_READ);
            if (!$this->handle->flush()) {
                Event::set($this->handle->socket, null, null, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
            }

            $result = $this->deferred->wait();
        } catch (pq\Exception $exception) {
            throw new FailureException($this->handle->errorMessage, 0, $exception);
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
                $this->deferred->lock();

                return new PqUnbufferedResultSet(
                    $this->fetch,
                    $result,
                    $this->release
                );

            case pq\Result::NONFATAL_ERROR:
            case pq\Result::FATAL_ERROR:
                while ($this->handle->busy && $this->handle->getResult()) {
                }
                throw new Exception\QueryExecutionError(
                    $result->errorMessage,
                    $result->status,
                    $result->diag,
                    null,
                    $sql ?? ''
                );

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
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        if (!$this->handle->busy) { // Results buffered.
            $result = $this->handle->getResult();
        } else {
//            Event::set($this->handle->socket, null, null, SWOOLE_EVENT_READ);
            if (!$this->handle->flush()) {
                Event::set($this->handle->socket, null, null, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
            }

            $result = $this->deferred->wait();
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

    /**
     * Used on end of fetching unbuffered results to resume blocked coroutines
     */
    private function release(): void
    {
        // https://github.com/amphp/postgres/pull/44
        while ($this->handle->busy && $this->handle->getResult()) {
            // nothing
        }

        $this->deferred->unlock();
    }

    /**
     * @param string $channel
     *
     * @return CommandResult|null
     *
     * @throws Error
     */
    private function unlisten(string $channel): ?CommandResult
    {
        assert(isset($this->listeners[$channel]), "Not listening on that channel");

        $emitter = $this->listeners[$channel];
        unset($this->listeners[$channel]);

        $emitter->close();

        if ($this->handle === null) {
            return null; // Connection already closed.
        }

        return $this->send(null, [$this->handle, "unlistenAsync"], $channel);
    }

    private function poll(): void
    {
        $this->lastUsedAt = time();

        $handle = $this->handle;

        if ($handle->poll() === pq\Connection::POLLING_FAILED) {
            $exception = new ConnectionException($handle->errorMessage);
            $this->handle = null; // Marks connection as dead.

            Event::del($handle->socket);

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

        if (!$this->deferred->isWaiting()) {
            return; // No active query, only notification listeners.
        }

        if ($handle->busy) {
            return; // Not finished receiving data, poll again.
        }

        $res = $handle->getResult();

        $this->deferred->resolve($res);
    }

    private function await(): void
    {
        $handle = $this->handle;

        try {
            if (!$handle->flush()) {
                return; // Not finished sending data, continue polling for writability.
            }
        } catch (pq\Exception $exception) {
            $exception = new ConnectionException("Flushing the connection failed", 0, $exception);
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

        // disable write event
        Event::set($handle->socket, null, null, SWOOLE_EVENT_READ);
    }
}
