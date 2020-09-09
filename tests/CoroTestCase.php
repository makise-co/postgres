<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Timer;
use Throwable;

use function Swoole\Coroutine\run;

abstract class CoroTestCase extends TestCase
{
    public function run(TestResult $result = null): TestResult
    {
        $coroResult = new CoroutineTestResult();

        run(
            Closure::fromCallable([$this, 'execCoro']),
            $result,
            $coroResult
        );

        if (null !== $coroResult->ex) {
            throw $coroResult->ex;
        }

        return $coroResult->result;
    }

    private function execCoro(?TestResult $result, CoroutineTestResult $coroTestResult): void
    {
        Coroutine::defer(
            static function () {
                // do not block command coroutine exit if programmer have forgotten to release event loop
                if (Coroutine::stats()['event_num'] > 0) {
                    // force exit event loop
                    Event::exit();
                }

                Timer::clearAll();
            }
        );

        try {
            $coroTestResult->result = parent::run($result);
        } catch (Throwable $e) {
            $coroTestResult->ex = $e;
        }
    }
}
