<?php

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

class CoroTestCase extends TestCase
{
    protected function runTest()
    {
        $res = null;
        $ex = null;

        Coroutine::set(['log_level' => SWOOLE_LOG_INFO]);

        Coroutine\run(function () use (&$res, &$ex) {
            try {
                $res = parent::runTest();
            } catch (\Throwable $e) {
                $ex = $e;
            }
        });

        if (null !== $ex) {
            throw $ex;
        }

        return $res;
    }
}