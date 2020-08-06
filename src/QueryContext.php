<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use pq\Result;
use Swoole\Coroutine;

class QueryContext
{
    /**
     * @var bool is query context already taken?
     */
    public bool $busy = false;

    /**
     * @var int Coroutine ID that taken ownership on query context
     */
    public int $cid = 0;

    /**
     * @var Result|null result returned from postgres
     */
    public ?Result $result = null;

    /**
     * @var float where bigger than 0 it is timeout error
     */
    public float $timedOut = 0;

    /**
     * @var string|null exception class, used to provide useful stack trace
     */
    public ?string $errorClass = null;

    /**
     * @var array parameters passed to exception constructor
     */
    public array $errorParameters = [];

    /**
     * Take query context ownership
     *
     * @throws Exception\FailureException when trying to make concurrent queries
     */
    public function take(): void
    {
        if ($this->busy) {
            throw new Exception\ConcurrencyException();
        }

        $this->cid = Coroutine::getCid();
        $this->busy = true;
        $this->result = null;
        $this->timedOut = 0;
    }

    /**
     * Free query context for next operations
     *
     * @return Result query execution result on success
     *
     * @throws Exception\FailureException
     * @throws Exception\ConnectionException
     */
    public function free(): Result
    {
        $this->busy = false;

        $this->throwError();

        if (null === $this->result) {
            throw new Exception\FailureException('Query context cannot be freed without pq\\Result');
        }

        $result = $this->result;

        // do not store query result
        $this->result = null;

        return $result;
    }

    public function throwError(): void
    {
        if ($this->errorClass) {
            $exception = new $this->errorClass(...$this->errorParameters);

            $this->errorClass = null;
            $this->errorParameters = [];

            throw $exception;
        }
    }

    public function resumeWithError(string $class, ...$parameters): void
    {
        $this->errorClass = $class;
        $this->errorParameters = $parameters;

        $this->resume();
    }

    public function resumeWithResult(Result $result): void
    {
        $this->result = $result;

        $this->resume();
    }

    public function resume(): void
    {
        Coroutine::resume($this->cid);
    }
}
