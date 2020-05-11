<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use Swoole\Coroutine;

class QueryContext
{
    public int $cid = 0;
    public \pq\Result $result;

    /**
     * @var \Throwable|null exception from poll/await callbacks
     */
    public ?\Throwable $error = null;

    /**
     * @var float where bigger than 0 it is timeout error
     */
    public float $timedOut = 0;

    /**
     * @var string|null exception class, used to provide useful stack trace
     */
    public ?string $errorClass = null;
    public array $errorParameters = [];

    public function getResult(): \pq\Result
    {
        $this->throwError();

        return $this->result;
    }

    public function throwError(): void
    {
        if ($this->errorClass) {
            throw new $this->errorClass(...$this->errorParameters);
        }

        if (null !== $this->error) {
            throw $this->error;
        }
    }

    public function resumeWithTimeout(float $timeout): void
    {
        $this->timedOut = $timeout;

        $this->resume();
    }

    public function resumeWithUsefulError(string $class, ...$parameters): void
    {
        $this->errorClass = $class;
        $this->errorParameters = $parameters;

        $this->resume();
    }

    public function resumeWithError(\Throwable $e): void
    {
        $this->error = $e;

        $this->resume();
    }

    public function resumeWithResult(\pq\Result $result): void
    {
        $this->result = $result;

        $this->resume();
    }

    public function resume(): void
    {
        Coroutine::resume($this->cid);
    }
}