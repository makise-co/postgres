<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Swoole;

use Closure;
use MakiseCo\EvPrimitives\Lock;
use MakiseCo\Postgres\Contracts\Handle;
use MakiseCo\Postgres\Contracts\Listener;
use MakiseCo\Postgres\Driver\Pq\StatementStorage;
use MakiseCo\Postgres\Exception\QueryExecutionError;
use MakiseCo\Postgres\Internal;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\Statement;
use MakiseCo\SqlCommon\Exception;
use MakiseCo\SqlCommon\Exception\ConcurrencyException;
use MakiseCo\SqlCommon\Exception\ConnectionException;
use RuntimeException;
use Swoole\Coroutine\PostgreSQL;

use function sprintf;
use function time;

class SwooleHandle implements Handle
{
    private const PGRES_EMPTY_QUERY = 0;
    private const PGRES_COMMAND_OK = 1;
    private const PGRES_TUPLES_OK = 2;
    private const PGRES_COPY_OUT = 3;
    private const PGRES_COPY_IN = 4;
    private const PGRES_BAD_RESPONSE = 5;
    private const PGRES_NONFATAL_ERROR = 6;
    private const PGRES_FATAL_ERROR = 7;

    private ?PostgreSQL $handle;

    private Lock $lock;
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

    public function __construct(PostgreSQL $handle)
    {
        $this->handle = $handle;

        $this->lock = new Lock();
        $this->lastUsedAt = time();
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
        if ($this->handle !== null) {
            $this->handle = null;
        }
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
    public function query(string $sql)
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return $this->createResult(
            $this->send(Closure::fromCallable([$this->handle, 'query']), $sql),
            $sql
        );
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

            return new SwooleStatement($this, $name, $sql, $names);
        }

        $storage = new StatementStorage();
        $storage->sql = $sql;

        $this->statements[$name] = $storage;

        $storage->promise = true;
        $this->send(Closure::fromCallable([$this->handle, 'prepare']), $name, $modifiedSql);

        switch ($this->handle->resultStatus) {
            case self::PGRES_COMMAND_OK:
                // Success
                break;

            case self::PGRES_NONFATAL_ERROR:
            case self::PGRES_FATAL_ERROR:
                unset($this->statements[$name]);

                throw $this->handleResultError($sql);

            case self::PGRES_BAD_RESPONSE:
                unset($this->statements[$name]);

                throw new Exception\FailureException($this->handle->error);

            default:
                unset($this->statements[$name]);
                throw new Exception\FailureException("Unknown result status");
        }

        $storage->promise = false;

        $storage->wakeupSubscribers(null);

        return new SwooleStatement($this, $name, $sql, $names);
    }

    /**
     * @inheritDoc
     */
    public function execute(string $sql, array $params = [])
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $stmt = $this->prepare($sql);

        return $stmt->execute($params);
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
            return $this->query(sprintf("NOTIFY %s", $this->quoteName($channel)));
        }

        return $this->query(sprintf("NOTIFY %s, %s", $this->quoteName($channel), $this->quoteString($payload)));
    }

    /**
     * @inheritDoc
     */
    public function quoteString(string $data): string
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return $this->handle->escape($data);
    }

    /**
     * @inheritDoc
     */
    public function quoteName(string $name): string
    {
        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        return $this->handle->escape($name);
    }

    /**
     * @inheritDoc
     */
    public function listen(string $channel): Listener
    {
        throw new RuntimeException('Listen is not supported');
    }

    /**
     * Executes the named statement using the given parameters.
     *
     * @param string $name
     * @param array $params
     *
     * @return CommandResult|SwooleResultSet
     * @throws ConcurrencyException
     * @throws Exception\FailureException
     */
    public function statementExecute(string $name, array $params)
    {
        assert(isset($this->statements[$name]), "Named statement not found when executing");

        $storage = $this->statements[$name];

        return $this->createResult(
            $this->send(Closure::fromCallable([$this->handle, 'execute']), $name, $params),
            $storage->sql
        );
    }

    /**
     * @param string $name
     *
     * @return void
     *
     * @throws Exception\FailureException
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

        unset($this->statements[$name]);

        $this->query("DEALLOCATE {$name}");
    }

    private function send(Closure $closure, ...$params)
    {
        if ($this->isConcurrent) {
            // wait previous query execution done
            $this->lock->wait();
        } elseif ($this->lock->isLocked()) {
            throw new ConcurrencyException('Concurrent requests are not allowed');
        }

        if ($this->handle === null) {
            throw new ConnectionException("The connection to the database has been closed");
        }

        $this->lock->lock();

        $this->lastUsedAt = \time();

        try {
            $result = $closure(...$params);
        } finally {
            $this->lock->unlock();
        }

        $this->lastUsedAt = \time();

        return $result;
    }

    private function createResult($result, string $sql)
    {
        switch ($this->handle->resultStatus ?? null) {
            case self::PGRES_EMPTY_QUERY:
                throw new Exception\QueryError('Empty query');

            case self::PGRES_COMMAND_OK:
                return new SwooleCommandResult($this->handle, $result);

            case self::PGRES_TUPLES_OK:
                return new SwooleResultSet($this->handle, $result);

            case self::PGRES_BAD_RESPONSE:
                $this->close();
                throw new Exception\FailureException($this->handle->error ?? 'Bad response');

            case self::PGRES_NONFATAL_ERROR:
            case self::PGRES_FATAL_ERROR:
                throw $this->handleResultError($sql);

            default:
                $this->close();
                throw new Exception\FailureException("Unknown result status");
        }
    }

    private function handleResultError(string $sql): QueryExecutionError
    {
        return new QueryExecutionError(
            $this->handle->error,
            $this->handle->resultStatus,
            $this->handle->resultDiag ?? [],
            null,
            $sql
        );
    }
}
