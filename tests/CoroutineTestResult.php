<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use PHPUnit\Framework\TestResult;
use Throwable;

final class CoroutineTestResult
{
    public ?TestResult $result = null;
    public ?Throwable $ex = null;
}
