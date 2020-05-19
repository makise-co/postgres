<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use Swoole\Coroutine;
use Swoole\Timer;
use Throwable;

class CoroTestCase extends TestCase
{
    public function run(TestResult $result = null): TestResult
    {
        $res = null;
        $ex = null;

        Coroutine::set(['log_level' => SWOOLE_LOG_INFO]);

        Coroutine\run(
            function () use (&$res, &$ex, $result) {
                try {
                    $res = parent::run($result);
                } catch (Throwable $e) {
                    $ex = $e;
                }

                Timer::clearAll();
            }
        );

        if (null !== $ex) {
            throw $ex;
        }

        return $res;
    }
}
