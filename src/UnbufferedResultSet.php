<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use Closure;
use pq\Result;
use stdClass;
use Throwable;

final class UnbufferedResultSet extends ResultSet
{
    private int $numCols;

    private bool $allRowsAreFetched = false;

    private bool $firstRowFetched = false;

    private Closure $fetchFunction;

    private Result $result;

    /**
     * @param Closure $fetch Function to fetch next result row.
     * @param Result $result PostgreSQL result object.
     */
    public function __construct(Closure $fetch, Result $result)
    {
        $this->fetchFunction = $fetch;
        $this->numCols = $result->numCols;
        $this->result = $result;
    }

    public function __destruct()
    {
        if ($this->allRowsAreFetched) {
            return;
        }

        // flush everything
        // ignore errors
        try {
            while (null !== ($this->fetchFunction)()) {
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(int $fetchStyle = self::DEFAULT_FETCH_STYLE)
    {
        $result = $this->retrieveNextResult();
        // end of results
        if (null === $result) {
            return null;
        }

        $result->autoConvert = $this->autoConvert;

        return $result->fetchRow($fetchStyle);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAssoc(): ?array
    {
        return $this->fetch(Result::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchObject(): ?stdClass
    {
        return $this->fetch(Result::FETCH_OBJECT);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchArray(): ?array
    {
        return $this->fetch(Result::FETCH_ARRAY);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchCol($col, &$ref): ?bool
    {
        $result = $this->retrieveNextResult();
        if (null === $result) {
            return null;
        }

        $result->autoConvert = $this->autoConvert;

        return $result->fetchCol($ref, $col);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(int $fetchStyle = self::DEFAULT_FETCH_STYLE): array
    {
        $result = [];

        while ($row = $this->fetch($fetchStyle)) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllCols($col): array
    {
        $result = [];

        $value = null;
        while ($this->fetchCol($col, $value)) {
            $result[] = $value;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchBound(array $map = []): ?array
    {
        $result = $this->retrieveNextResult();
        if (null === $result) {
            return null;
        }

        /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
        foreach ($map as $col => &$property) {
            $result->bind($col, $property);
        }

        return $result->fetchBound();
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldCount(): int
    {
        return $this->numCols;
    }

    private function retrieveNextResult(): ?Result
    {
        if ($this->allRowsAreFetched) {
            return null;
        }

        // in the unbuffered result set first row comes in the initial command
        if (!$this->firstRowFetched) {
            $result = $this->result;
            $this->firstRowFetched = true;
        } else {
            $result = ($this->fetchFunction)();
        }

        // no more results remain
        if (null === $result) {
            $this->allRowsAreFetched = true;
        }

        return $result;
    }
}
