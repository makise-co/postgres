<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Driver\Pq;

use Closure;
use Generator;
use MakiseCo\SqlCommon\Contracts\ResultSet;
use pq;
use stdClass;
use Throwable;

final class PqUnbufferedResultSet implements ResultSet
{
    private int $numCols;

    /**
     * Unbuffered results holder
     */
    private Generator $generator;

    private bool $destroyed = false;

    /**
     * @param Closure $fetch Function to fetch next result row.
     * @param pq\Result $result PostgreSQL result object.
     * @param Closure $release Function to fetch next result row.
     */
    public function __construct(Closure $fetch, pq\Result $result, Closure $release)
    {
        $this->numCols = $result->numCols;

        $destroyed = &$this->destroyed;

        $this->generator = (static function () use (&$destroyed, $result, $fetch, $release): Generator {
            try {
                do {
                    $result->autoConvert = pq\Result::CONV_SCALAR | pq\Result::CONV_ARRAY;
                    $next = $fetch();
                    yield $result;
                    $result = $next;
                } while ($result instanceof pq\Result);
            } finally {
                $destroyed = true;
                $release();
            }
        })();
    }

    public function __destruct()
    {
        if ($this->destroyed) {
            return;
        }

        try {
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            foreach ($this->generator as $value) {
            }
        } catch (Throwable $e) {
            // Ignore iterator failure when destroying.
        }
    }

    /**
     * @return int Number of fields (columns) in each result set.
     */
    public function getFieldCount(): int
    {
        return $this->numCols;
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(int $fetchStyle = self::FETCH_ASSOC)
    {
        $result = $this->takeResult();
        if ($result === null) {
            return null;
        }

        return $result->fetchRow($fetchStyle);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAssoc(): ?array
    {
        return $this->fetch(self::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchObject(): ?stdClass
    {
        return $this->fetch(self::FETCH_OBJECT);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchArray(): ?array
    {
        return $this->fetch(self::FETCH_ARRAY);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchColumn($col, &$ref): ?bool
    {
        $res = $this->takeResult();
        if ($res === null) {
            return null;
        }

        return $res->fetchCol($ref, $col);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(int $fetchStyle = self::FETCH_ASSOC): array
    {
        $items = [];

        while (null !== ($res = $this->fetch())) {
            $items[] = $res;
        }

        return $items;
    }

    /**
     * {@inheritDoc}
     */
    public function isUnbuffered(): bool
    {
        return true;
    }

    /**
     * @return pq\Result
     */
    private function takeResult(): ?pq\Result
    {
        $curr = $this->generator->current();
        $this->generator->next();

        return $curr;
    }
}
