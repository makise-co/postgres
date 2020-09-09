<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Tests;

abstract class AbstractConnectionTest extends AbstractLinkTest
{
    public function testIsAlive(): void
    {
        self::assertTrue($this->connection->isAlive());
    }
}
