<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Exception;

class ConnectionTimeoutException extends \Error
{
    private float $timeout;

    public function __construct(float $timeout)
    {
        parent::__construct('Connection timeout', 0, null);

        $this->timeout = $timeout;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }
}