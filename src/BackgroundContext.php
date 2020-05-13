<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

class BackgroundContext
{
    /**
     * @var string|null exception class, used to provide useful stack trace
     */
    public ?string $errorClass = null;

    /**
     * @var array parameters passed to exception constructor
     */
    public array $errorParameters = [];

    public function setError(string $class, ...$parameters): void
    {
        $this->errorClass = $class;
        $this->errorParameters = $parameters;
    }

    public function hasError(): bool
    {
        return null !== $this->errorClass;
    }

    /**
     * @throws Exception\ConnectionException when has error
     */
    public function throwError(): void
    {
        if (null !== $this->errorClass) {
            throw new $this->errorClass(...$this->errorParameters);
        }
    }
}