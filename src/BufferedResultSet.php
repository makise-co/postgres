<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use pq;

final class BufferedResultSet extends ResultSet
{
    /** @var \pq\Result */
    private pq\Result $result;

    /** @var int */
    private int $position = 0;

    /** @var mixed Last row emitted. */
    private $currentRow;

    /**
     * @param pq\Result $result PostgreSQL result object.
     */
    public function __construct(pq\Result $result)
    {
        $this->result = $result;
        $this->result->autoConvert = pq\Result::CONV_SCALAR | pq\Result::CONV_ARRAY;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrent(): array
    {
        if ($this->currentRow !== null) {
            return $this->currentRow;
        }

        if ($this->position > $this->result->numRows) {
            throw new \Error("No more rows remain in the result set");
        }

        return $this->currentRow = $this->result->fetchRow(pq\Result::FETCH_ASSOC);
    }

    public function getNumRows(): int
    {
        return $this->result->numRows;
    }

    public function getFieldCount(): int
    {
        return $this->result->numCols;
    }

    public function current()
    {
        return $this->getCurrent();
    }

    public function next(): void
    {
        $this->position++;
        $this->currentRow = null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return $this->position < $this->result->numRows;
    }

    public function rewind(): void
    {
        $this->position = 0;
        $this->currentRow = null;
    }
}
