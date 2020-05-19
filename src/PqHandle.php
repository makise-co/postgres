<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use Closure;
use Error;
use InvalidArgumentException;
use MakiseCo\Postgres\Sql\ExecutorInterface;
use MakiseCo\Postgres\Sql\QuoterInterface;
use MakiseCo\Postgres\Sql\ReceiverInterface;
use MakiseCo\Postgres\Sql\TransactionInterface;
use pq;
use pq\Connection as PqConnection;
use pq\Result;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Timer;
use Throwable;

class PqHandle implements ExecutorInterface, ReceiverInterface, QuoterInterface
{
    /**
     * @var PqConnection|null holds an active pq\Connection instance
     */
    private ?PqConnection $pq;

    /**
     * @var BackgroundContext holds an error that can happen in background context
     */
    private BackgroundContext $bgContext;

    /**
     * @var QueryContext holds query executing result
     */
    private QueryContext $queryContext;

    /**
     * @var Statement[] prepared statements
     */
    private array $statements = [];

    /**
     * @var Coroutine\Channel[]|null[] channels which are used to deliver notifications to listeners
     */
    private array $listenerChans = [];

    public function __construct(pq\Connection $pq)
    {
        $this->pq = $pq;
        $this->queryContext = new QueryContext();
        $this->bgContext = new BackgroundContext();

        if (!Event::add(
            $this->pq->socket,
            Closure::fromCallable([$this, 'pollCallback']),
            Closure::fromCallable([$this, 'awaitCallback']),
            SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE
        )) {
            throw new Exception\FailureException('Unable to add postgres event');
        }

        // disable await callback
        Event::set($this->pq->socket, null, null, SWOOLE_EVENT_READ);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function disconnect(): void
    {
        $this->free(true);
    }

    public function getPq(): ?pq\Connection
    {
        return $this->pq;
    }

    /**
     * @param bool $graceful try to close connection gracefully?
     */
    private function free(bool $graceful): void
    {
        if (!$this->pq) {
            return;
        }

        if ($graceful) {
            // close statements
            try {
                foreach ($this->statements as $statement) {
                    $statement->close();
                }
            } catch (Throwable $e) {
                // ignore possible connection errors
            }

            // drop poll/await events
            Event::del($this->pq->socket);

            $this->pq = null;
            $this->statements = [];
        } else {
            // drop poll/await events
            Event::del($this->pq->socket);

            $this->pq = null;
            $this->statements = [];

            // close statements
            foreach ($this->statements as $statement) {
                $statement->close();
            }
        }

        // close listeners
        foreach ($this->listenerChans as $listenerChan) {
            if (null === $listenerChan) {
                continue;
            }

            // push connection error if exists
            if ($this->bgContext->hasError()) {
                // notify listener that the connection error has occurred
                Coroutine::create(
                    static function (BackgroundContext $ctx, Coroutine\Channel $chan) {
                        $chan->push(
                            new $ctx->errorClass(...$ctx->errorParameters),
                            0.001
                        );

                        $chan->close();
                    },
                    $this->bgContext,
                    $listenerChan
                );
            } else {
                $listenerChan->close();
            }
        }

        // destruct listeners
        $this->listenerChans = [];
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->pq !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, float $timeout = 0)
    {
        $result = $this->coroExec(
            function () use ($sql) {
                $this->pq->execAsync($sql);
            },
            $timeout
        );

        switch ($result->status) {
            case Result::EMPTY_QUERY:
                throw new Exception\QueryError("Empty query string");

            case Result::COMMAND_OK:
                return new CommandResult($result);

            case Result::TUPLES_OK:
                return new BufferedResultSet($result);

            case Result::SINGLE_TUPLE:
                return new UnbufferedResultSet(Closure::fromCallable([$this, 'fetch']), $result);

            case Result::NONFATAL_ERROR:
            case Result::FATAL_ERROR:
                throw new Exception\QueryExecutionError($result->errorMessage, $sql, $result->diag);

            case Result::BAD_RESPONSE:
                throw new Exception\FailureException($result->errorMessage);

            default:
                throw new Exception\FailureException("Unknown result status {$result->status}");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = [], array $types = [], float $timeout = 0)
    {
        $sql = Internal\parseNamedParams($sql, $names);
        $params = Internal\replaceNamedParams($params, $names);

        $result = $this->coroExec(
            function () use ($sql, $params, $types) {
                $this->pq->execParamsAsync($sql, $params, $types);
            },
            $timeout
        );

        switch ($result->status) {
            case Result::EMPTY_QUERY:
                throw new Exception\QueryError("Empty query string");

            case Result::COMMAND_OK:
                return new CommandResult($result);

            case Result::TUPLES_OK:
                return new BufferedResultSet($result);

            case Result::SINGLE_TUPLE:
                return new UnbufferedResultSet(Closure::fromCallable([$this, 'fetch']), $result);

            case Result::NONFATAL_ERROR:
            case Result::FATAL_ERROR:
                throw new Exception\QueryExecutionError($result->errorMessage, $sql, $result->diag);

            case Result::BAD_RESPONSE:
                throw new Exception\FailureException($result->errorMessage);

            default:
                throw new Exception\FailureException("Unknown result status {$result->status}");
        }
    }

    /**
     * {@inheritDoc}
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

        $result = $this->coroExec(
            function () use ($name, $modifiedSql, $types, &$pqStatement) {
                $pqStatement = $this->pq->prepareAsync($name, $modifiedSql, $types);
            },
            $timeout
        );

        switch ($result->status) {
            case Result::EMPTY_QUERY:
                throw new Exception\QueryError("Empty query string");

            case Result::COMMAND_OK:
                if (!$pqStatement instanceof pq\Statement) {
                    throw new Exception\FailureException("prepareAsync returned not a pq\\Statement object");
                }
                // success
                break;

            case Result::NONFATAL_ERROR:
            case Result::FATAL_ERROR:
                throw new Exception\QueryExecutionError($result->errorMessage, $sql, $result->diag);

            case Result::BAD_RESPONSE:
                throw new Exception\FailureException($result->errorMessage);

            default:
                throw new Exception\FailureException("Unknown result status {$result->status}");
        }

        $statement = new Statement($this, $pqStatement, $name, $modifiedSql, $names);
        $this->statements[$name] = $statement;

        return $statement;
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): Transaction
    {
        // TODO: Add support for READ ONLY transactions
        // TODO: Add support for DEFERRABLE transactions
        // Link: https://www.postgresql.org/docs/9.1/sql-set-transaction.html
        // Link: https://mdref.m6w6.name/pq/Connection/startTransaction

        switch ($isolation) {
            case Transaction::ISOLATION_UNCOMMITTED:
                $this->query("BEGIN TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
                break;

            case Transaction::ISOLATION_COMMITTED:
                $this->query("BEGIN TRANSACTION ISOLATION LEVEL READ COMMITTED");
                break;

            case Transaction::ISOLATION_REPEATABLE:
                $this->query("BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                break;

            case Transaction::ISOLATION_SERIALIZABLE:
                $this->query("BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE");
                break;

            default:
                throw new InvalidArgumentException("Invalid transaction type");
        }

        return new Transaction($this, $isolation);
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

        // remove statement link
        unset($this->statements[$name]);

        if (!$this->isConnected()) {
            return;
        }

        // deallocate statements only when there is connection problems
        $this->coroExec(
            static function () use ($statement) {
                $statement->getPqStatement()->deallocateAsync();
            },
            $timeout
        );
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

        $result = $this->coroExec(
            static function () use ($statement, $params) {
                $statement->getPqStatement()->execAsync($params);
            },
            $timeout
        );

        switch ($result->status) {
            case Result::EMPTY_QUERY:
                throw new Exception\QueryError("Empty query string");

            case Result::COMMAND_OK:
                return new CommandResult($result);

            case Result::TUPLES_OK:
                return new BufferedResultSet($result);

            case Result::SINGLE_TUPLE:
                return new UnbufferedResultSet(Closure::fromCallable([$this, 'fetch']), $result);

            case Result::NONFATAL_ERROR:
            case Result::FATAL_ERROR:
                throw new Exception\QueryExecutionError($result->errorMessage, $statement->getQuery(), $result->diag);

            case Result::BAD_RESPONSE:
                throw new Exception\FailureException($result->errorMessage);

            default:
                throw new Exception\FailureException("Unknown result status {$result->status}");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function listen(string $channel, ?Closure $callable, float $timeout = 0): ?Listener
    {
        if (array_key_exists($channel, $this->listenerChans)) {
            throw new Exception\FailureException("Listener on {$channel} already exists");
        }

        $emitter = null;

        if (null !== $callable) {
            $result = $this->coroExec(
                function () use ($channel, $callable) {
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
                },
                $timeout
            );
        } else {
            $emitter = new Coroutine\Channel(1);

            $result = $this->coroExec(
                function () use ($channel, $emitter) {
                    $this->pq->listenAsync(
                        $channel,
                        static function (string $channel, string $message, int $pid) use ($emitter) {
                            $notification = new Notification();
                            $notification->channel = $channel;
                            $notification->payload = $message;
                            $notification->pid = $pid;

                            Coroutine::create(
                                static function (Coroutine\Channel $emitter, Notification $notification) {
                                    $emitter->push($notification);
                                },
                                $emitter,
                                $notification
                            );
                        }
                    );
                },
                $timeout
            );
        }

        if ($result->status !== Result::COMMAND_OK) {
            throw new Exception\FailureException("Unable to listen, status={$result->statusMessage}");
        }

        $this->listenerChans[$channel] = $emitter;
        if (null !== $emitter) {
            return new Listener(
                $channel,
                $emitter,
                function () use ($channel) {
                    if ($this->pq) {
                        $this->unlisten($channel);
                    }
                }
            );
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function notify(string $channel, string $payload, float $timeout = 0): CommandResult
    {
        $result = $this->coroExec(
            function () use ($channel, $payload) {
                $this->pq->notifyAsync($channel, $payload);
            },
            $timeout
        );

        if ($result->status !== Result::TUPLES_OK) {
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
        $result = $this->coroExec(
            function () use ($channel) {
                $this->pq->unlistenAsync($channel);
            },
            $timeout
        );

        $listener = $this->listenerChans[$channel] ?? null;
        // close channel if exists
        if (null !== $listener) {
            $listener->close();
        }
        unset($this->listenerChans[$channel]);

        if ($result->status !== Result::COMMAND_OK) {
            throw new Exception\FailureException("Unable to unlisten, status={$result->statusMessage}");
        }

        return new CommandResult($result);
    }

    /**
     * {@inheritDoc}
     */
    public function quoteString(string $data): string
    {
        if (!$this->pq) {
            throw new Error("The connection to the database has been closed");
        }

        return $this->pq->quote($data);
    }

    /**
     * {@inheritDoc}
     */
    public function quoteName(string $name): string
    {
        if (!$this->pq) {
            throw new Error("The connection to the database has been closed");
        }

        return $this->pq->quoteName($name);
    }

    /**
     * This method is used to execute every database operation
     *
     * @param Closure|null $closure
     * @param float $timeout
     * @param mixed ...$params
     *
     * @return Result
     *
     * @throws Exception\ConnectionException
     * @throws Exception\ConcurrencyException
     * @throws Exception\FailureException
     * @throws Throwable
     */
    private function coroExec(?Closure $closure, float $timeout = 0, ...$params): Result
    {
        if (!$this->isConnected()) {
            $this->bgContext->throwError();

            throw new Exception\ConnectionException("Connection is closed");
        }

        $this->queryContext->take();

//        if ($this->pq->busy) {
//            throw new Exception\FailureException("Please fetch results from previous operation");
//        }

        if ($closure) {
            try {
                $closure($params);
            } catch (pq\Exception\RuntimeException $e) {
                throw new Exception\ConcurrencyException($e);
            }
        }

        try {
            if (!$this->pq->flush()) {
                Event::set($this->pq->socket, null, null, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
            }
        } catch (pq\Exception $e) {
            $this->free(false);

            throw new Exception\ConnectionException("Flushing the connection failed", 0, $e);
        }

        $timerId = null;
        if ($timeout > 0) {
            $timerId = Timer::after(
                (int)($timeout * 1000),
                static function (QueryContext $context, float $timeout, pq\Connection $pq) {
                    $context->timedOut = $timeout;

                    $cancel = new pq\Cancel($pq);
                    $cancel->cancel();
                },
                $this->queryContext,
                $timeout,
                $this->pq
            );
        }

        // block coroutine until a results will be fetched or an error will be occurred
        Coroutine::yield();

        if ($timerId) {
            Timer::clear($timerId);
        }

        return $this->queryContext->free();
    }

    /**
     * Used to fetch Unbuffered results from database
     *
     * @return Result|null
     *
     * @throws Exception\ConcurrencyException
     * @throws Exception\ConnectionException
     * @throws Exception\FailureException
     */
    private function fetch(): ?Result
    {
        if (!$this->isConnected()) {
            $this->bgContext->throwError();

            throw new Exception\ConnectionException("Connection is closed");
        }

        if (!$this->pq->busy) { // Results buffered.
            $result = $this->pq->getResult();
        } else { // Results unbuffered
            $result = $this->coroExec(null);
        }

        if (!$result) {
            throw new Exception\ConnectionException("Connection is closed");
        }

        switch ($result->status) {
            case Result::TUPLES_OK: // End of result set.
                return null;

            case Result::SINGLE_TUPLE:
                return $result;

            default:
                throw new Exception\FailureException($result->errorMessage);
        }
    }

    private function pollCallback(): void
    {
        if ($this->pq === null) {
            return;
        }

        if ($this->pq->poll() === PqConnection::POLLING_FAILED) {
            $errMsg = $this->pq->errorMessage;

            // set bg context before connection gets closed
            $this->bgContext->setError(Exception\ConnectionException::class, $errMsg);

            // mark connection as dead
            $this->free(false);

            if ($this->queryContext->busy) {
                $this->queryContext->resumeWithError(Exception\ConnectionException::class, $errMsg);
            }

            return;
        }

        if (!$this->queryContext->busy) {
            return; // No active query, only notification listeners.
        }

        if ($this->pq->busy) {
            return; // Not finished receiving data, poll again.
        }

        $result = $this->pq->getResult();
        if (null === $result) {
            if ($this->pq->status === PqConnection::BAD) {
                $errMsg = $this->pq->errorMessage;

                // set bg context before connection gets closed
                $this->bgContext->setError(Exception\ConnectionException::class, $errMsg);

                // mark connection as dead
                $this->free(false);

                $this->queryContext->resumeWithError(Exception\ConnectionException::class, $errMsg);
            } else {
                $this->queryContext->resumeWithError(
                    Exception\FailureException::class,
                    'Bad result returned from postgres'
                );
            }
        } else {
            $this->queryContext->resumeWithResult($result);
        }
    }

    private function awaitCallback(): void
    {
        if ($this->pq === null) {
            return;
        }

        try {
            if (!$this->pq->flush()) {
                return; // Not finished sending data, continue polling for writability.
            }
        } catch (pq\Exception $exception) {
            // set bg context before connection gets closed
            $this->bgContext->setError(
                Exception\ConnectionException::class,
                "Flushing the connection failed",
                0,
                $exception
            );

            // mark connection as dead
            $this->free(false);

            if ($this->queryContext->busy) {
                $this->queryContext->resumeWithError(
                    Exception\ConnectionException::class,
                    "Flushing the connection failed",
                    0,
                    $exception
                );
            }

            return;
        }

        // disable write event
        Event::set($this->pq->socket, null, null, SWOOLE_EVENT_READ);
    }
}
