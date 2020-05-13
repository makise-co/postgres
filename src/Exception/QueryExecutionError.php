<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Exception;

class QueryExecutionError extends QueryError
{
    private string $query;
    private array $diagnostics;

    public function __construct(string $message, string $query, array $diagnostics)
    {
        parent::__construct($message, 0, null);

        $this->query = $query;
        $this->diagnostics = $diagnostics;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }
}