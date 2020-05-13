<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Exception;

use Throwable;

class ConcurrencyException extends FailureException
{
    public function __construct(Throwable $previous = null)
    {
        parent::__construct('Postgres client cannot be used to execute concurrent queries', 0, $previous);
    }
}