<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use pq;
use pq\Connection as PqConnection;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Timer;

class PqHandle
{
    private PqConnection $pq;
    private bool $closed = false;

    /**
     * @var Exception\ConnectionException|null exception from poll/await callbacks
     */
    private ?Exception\ConnectionException $connectionException = null;

    // query execution context
    private ?QueryContext $queryContext = null;

    /**
     * @var Statement[]
     */
    private array $statements = [];

    /**
     * @var int[]
     */
    private array $listeners = [];

    protected \Closure $pollClosure;
    protected \Closure $awaitClosure;

    public function __construct(pq\Connection $pq)
    {
        $this->pq = $pq;

        $this->pollClosure = \Closure::fromCallable([$this, 'pollCallback']);
        $this->awaitClosure = \Closure::fromCallable([$this, 'awaitCallback']);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function disconnect(): void
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->free();
    }

    public function getPq(): pq\Connection
    {
        return $this->pq;
    }

    private function free(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        // destruct listeners
        $this->listeners = [];

        // destruct statements
        // ignore statement errors
        try {
            foreach ($this->statements as $statement) {
                $statement->close();
            }
        } catch (\Throwable $e) {

        }

        // statements may not call deallocate method
        $this->statements = [];

        if ($this->pq->socket && Event::isset($this->pq->socket)) {
            Event::del($this->pq->socket);
        }

        unset($this->pq);
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return false === $this->closed;
    }

    /**
     * @param string $sql SQL query to execute.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return ResultSet|CommandResult result set for rows queries or command result for system queries
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function query(string $sql, float $timeout = 0)
    {
        $result = $this->coroExec(function () use ($sql) {
            $this->pq->execAsync($sql);
        }, $timeout);

        switch ($result->status) {
            case pq\Result::EMPTY_QUERY:
                throw new Exception\QueryError("Empty query string");

            case pq\Result::COMMAND_OK:
//                if ($handle instanceof pq\Statement) {
//                    return $handle; // Will be wrapped into a PqStatement object.
//                }
//
                return new CommandResult($result);

            case pq\Result::TUPLES_OK:
                return new BufferedResultSet($result);

            case pq\Result::SINGLE_TUPLE:
                return new UnbufferedResultSet(\Closure::fromCallable([$this, 'fetch']), $result);

            case pq\Result::NONFATAL_ERROR:
            case pq\Result::FATAL_ERROR:
                throw new Exception\QueryExecutionError($result->errorMessage, $sql, $result->diag);

            case pq\Result::BAD_RESPONSE:
                throw new Exception\FailureException($result->errorMessage);

            default:
                throw new Exception\FailureException("Unknown result status {$result->status}");
        }
    }

    /**
     * @param string $sql SQL query to prepare.
     * @param string|null $name SQL statement name. (optional)
     * @param array $types SQL statement parameter types. (optional)
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return Statement
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     */
    public function prepare(string $sql, ?string $name = null, array $types = [], float $timeout = 0): Statement
    {
        if (null === $name) {
            $name = md5($sql);
        }

        if (array_key_exists($name, $this->statements)) {
            return $this->statements[$name];
        }

        $modifiedSql = Internal\parseNamedParams($sql, $names);
        $pqStatement = null;

        $result = $this->coroExec(function () use ($name, $modifiedSql, $types, &$pqStatement) {
            $pqStatement = $this->pq->prepareAsync($name, $modifiedSql, $types);
        }, $timeout);

        switch ($result->status) {
            case pq\Result::EMPTY_QUERY:
                throw new Exception\QueryError("Empty query string");

            case pq\Result::COMMAND_OK:
                if (!$pqStatement instanceof pq\Statement) {
                    throw new Exception\FailureException("prepareAsync returned not a pq\\Statement object");
                }
                // success
                break;

            case pq\Result::NONFATAL_ERROR:
            case pq\Result::FATAL_ERROR:
                throw new Exception\QueryExecutionError($result->errorMessage, $sql, $result->diag);

            case pq\Result::BAD_RESPONSE:
                throw new Exception\FailureException($result->errorMessage);

            default:
                throw new Exception\FailureException("Unknown result status {$result->status}");
        }

        $statement = new Statement($this, $pqStatement, $name, $modifiedSql, $names);
        $this->statements[$name] = $statement;

        return $statement;
    }

    /**
     * @param string $name
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     * @throws Exception\FailureException
     */
    public function statementDeallocate(string $name, float $timeout = 0): void
    {
        $statement = $this->statements[$name] ?? null;
        if (null === $statement) {
            throw new Exception\FailureException("Statement {$name} not found");
        }

        // deallocate statements only when there is connection problems
        if (null === $this->connectionException) {
            $this->coroExec(static function () use ($statement) {
                $statement->getPqStatement()->deallocateAsync();
            }, $timeout);
        }

        unset($this->statements[$name]);
    }

    /**
     * Executes the named statement using the given parameters.
     *
     * @param string $name
     * @param array $params
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return ResultSet|CommandResult
     * @throws Exception\FailureException when statement not found
     */
    public function statementExecute(string $name, array $params, float $timeout = 0)
    {
        $statement = $this->statements[$name] ?? null;
        if (null === $statement) {
            throw new Exception\FailureException("Statement {$name} not found");
        }

        $result = $this->coroExec(static function () use ($statement, $params) {
            $statement->getPqStatement()->execAsync($params);
        }, $timeout);

        switch ($result->status) {
            case pq\Result::EMPTY_QUERY:
                throw new Exception\QueryError("Empty query string");

            case pq\Result::COMMAND_OK:
                return new CommandResult($result);

            case pq\Result::TUPLES_OK:
                return new BufferedResultSet($result);

            case pq\Result::SINGLE_TUPLE:
                return new UnbufferedResultSet(\Closure::fromCallable([$this, 'fetch']), $result);

            case pq\Result::NONFATAL_ERROR:
            case pq\Result::FATAL_ERROR:
                throw new Exception\QueryExecutionError($result->errorMessage, $statement->getQuery(), $result->diag);

            case pq\Result::BAD_RESPONSE:
                throw new Exception\FailureException($result->errorMessage);

            default:
                throw new Exception\FailureException("Unknown result status {$result->status}");
        }
    }

    /**
     * @param string $channel Channel name.
     * @param \Closure $callable Notifications receiver callback.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return CommandResult
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function listen(string $channel, \Closure $callable, float $timeout = 0): CommandResult
    {
        if (array_key_exists($channel, $this->listeners)) {
            throw new Exception\FailureException("Listener on {$channel} already exists");
        }

        $result = $this->coroExec(function () use ($channel, $callable) {
            $this->pq->listenAsync(
                $channel,
                static function (string $channel, string $message, int $pid) use ($callable) {
                    $notification = new Notification();
                    $notification->channel = $channel;
                    $notification->payload = $message;
                    $notification->pid = $pid;

                    Coroutine::create($callable, $notification);
                }
            );
        }, $timeout);

        if ($result->status !== pq\Result::COMMAND_OK) {
            throw new Exception\FailureException("Unable to listen, status={$result->statusMessage}");
        }

        // Enabling poll callback to get notifications from Postgres
        $this->enablePollCallback();

        // TODO: Implement lister objects
        $this->listeners[$channel] = 1;

        return new CommandResult($result);
    }

    /**
     * @param string $channel Channel name.
     * @param string $payload Notification payload.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return CommandResult
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     */
    public function notify(string $channel, string $payload, float $timeout = 0): CommandResult
    {
        $result = $this->coroExec(function () use ($channel, $payload) {
            $this->pq->notifyAsync($channel, $payload);
        }, $timeout);

        if ($result->status !== pq\Result::COMMAND_OK) {
            throw new Exception\FailureException("Unable to notify, status={$result->statusMessage}");
        }

        return new CommandResult($result);
    }

    /**
     * Unlistens from the channel. No more values will be emitted from this listener.
     *
     * @param string $channel Channel name.
     * @param float $timeout Maximum allowed time (seconds) to wait results from database. (optional)
     *
     * @return CommandResult
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     */
    public function unlisten(string $channel, float $timeout = 0): CommandResult
    {
        $result = $this->coroExec(function () use ($channel) {
            $this->pq->unlistenAsync($channel);
        }, $timeout);

        unset($this->listeners[$channel]);

        if ($result->status !== pq\Result::COMMAND_OK) {
            throw new Exception\FailureException("Unable to unlisten, status={$result->statusMessage}");
        }

        return new CommandResult($result);
    }

    private function coroExec(?\Closure $closure, float $timeout = 0, bool $flush = true, ...$params): pq\Result
    {
        // throw connection exception
        if ($this->closed && $this->connectionException) {
            throw $this->connectionException;
        }

        // flush results from previous operations
        if ($flush) {
            while ($this->pq->getResult()) {
            }
        }

        $this->queryContext = $queryContext = new QueryContext();
        $queryContext->cid = Coroutine::getCid();

        if ($closure) {
            $closure($params);
        }

        $this->enablePollCallback();
//        $this->enablePollAndAwaitCallbacks();
        if (!$this->pq->flush()) {
            $this->enableAwaitCallback();
        }

        $timerId = null;
        if ($timeout > 0) {
            $timerId = Timer::after((int)($timeout * 1000),
                static function (QueryContext $context, float $timeout, pq\Connection $pq) {
                    $context->timedOut = $timeout;

                    $cancel = new pq\Cancel($pq);
                    $cancel->cancel();
                }, $queryContext, $timeout, $this->pq);
        }

        Coroutine::yield();

        if ($timerId) {
            Timer::clear($timerId);
        }

        $result = $queryContext->getResult();
        $this->queryContext = null;

        return $result;
    }

    private function fetch(): ?\pq\Result
    {
        if ($this->closed) {
            throw new Exception\ConnectionException("Connection closed by client");
        }

        if (!$this->pq->busy) { // Results buffered.
            $result = $this->pq->getResult();
        } else {
            $result = $this->coroExec(
                static function () {},
                0,
                false
            );
        }

        if (!$result) {
            throw new Exception\ConnectionException("Connection closed");
        }

        switch ($result->status) {
            case pq\Result::TUPLES_OK: // End of result set.
                return null;

            case pq\Result::SINGLE_TUPLE:
                return $result;

            default:
                throw new Exception\FailureException($result->errorMessage);
        }
    }

    private function enablePollCallback(): void
    {
        $hasRead = Event::isset($this->pq->socket, SWOOLE_EVENT_READ);
        if ($hasRead) {
            return;
        }

        // TODO: Check bug in the swoole with events corruption
        if (!Event::add(
            $this->pq->socket,
            $this->pollClosure,
            null,
            SWOOLE_EVENT_READ
        )) {
            throw new Exception\FailureException('Unable to add postgres event');
        }
    }

    private function disablePollCallback(): void
    {
        $hasWrite = Event::isset($this->pq->socket, SWOOLE_EVENT_WRITE);

        Event::del($this->pq->socket);

        if ($hasWrite) {
            $this->enableAwaitCallback();
        }
    }

    private function enableAwaitCallback(): void
    {
        $hasWrite = Event::isset($this->pq->socket, SWOOLE_EVENT_WRITE);
        if ($hasWrite) {
            return;
        }

        // TODO: Check bug in the swoole with events corruption
        if (!Event::add(
            $this->pq->socket,
            null,
            $this->awaitClosure,
            SWOOLE_EVENT_WRITE
        )) {
            throw new Exception\FailureException('Unable to add postgres event');
        }
    }

    private function disableAwaitCallback(): void
    {
        $hasRead = Event::isset($this->pq->socket, SWOOLE_EVENT_READ);

        Event::del($this->pq->socket);

        if ($hasRead) {
            $this->enablePollCallback();
        }
    }

    private function pollCallback(): void
    {
        if ($this->closed) {
            $exception = new Exception\ConnectionException("Connection closed");
            $this->connectionException = $exception;

            if (null !== $this->queryContext) {
                $this->queryContext->resumeWithUsefulError(
                    Exception\ConnectionException::class,
                    'Connection closed'
                );
//                $this->queryContext->resumeWithError($exception);
            }

            return;
        }

        if ($this->pq->poll() === PqConnection::POLLING_FAILED) {
            $exception = new Exception\ConnectionException($this->pq->errorMessage);
            $this->connectionException = $exception;

            $this->free();

            if (null !== $this->queryContext) {
                $this->queryContext->resumeWithUsefulError(
                    Exception\ConnectionException::class,
                    $this->pq->errorMessage
                );
//                $this->queryContext->resumeWithError($exception);
            }

            return;
        }

        if ($this->queryContext === null) {
            return; // No active query, only notification listeners.
        }

        if ($this->pq->busy) {
            return; // Not finished receiving data, poll again.
        }

        $result = $this->pq->getResult();
        if (null === $result) {
            $exception = new Exception\ConnectionException('Connection closed');
            $this->connectionException = $exception;
            $this->queryContext->resumeWithUsefulError(
                Exception\ConnectionException::class,
                'Connection closed'
            );
//            $this->queryContext->resumeWithError($exception);
        } else {
            $this->queryContext->resumeWithResult($result);
        }

        // disable read event if there is not more subscribers
        if (!$this->closed && null === $this->queryContext && empty($this->listeners)) {
            $this->disablePollCallback();
        }
    }

    private function awaitCallback(): void
    {
        if ($this->closed) {
            $exception = new Exception\ConnectionException("Connection closed");
            $this->connectionException = $exception;

            if (null !== $this->queryContext) {
                $this->queryContext->resumeWithUsefulError(
                    Exception\ConnectionException::class,
                    'Connection closed'
                );
//                $this->queryContext->resumeWithError($exception);
            }

            return;
        }

        try {
            if (!$this->pq->flush()) {
                return; // Not finished sending data, continue polling for writability.
            }
        } catch (pq\Exception $exception) {
            $exception = new Exception\ConnectionException("Flushing the connection failed", 0, $exception);
            $this->connectionException = $exception;

            $this->free();

            if (null !== $this->queryContext) {
                $this->queryContext->resumeWithUsefulError(
                    Exception\ConnectionException::class,
                    "Flushing the connection failed",
                    0,
                    $exception
                );
//                $this->queryContext->resumeWithError($exception);
            }
        }

        // disable write event
        if (!$this->closed) {
            $this->disableAwaitCallback();
        }
    }
}