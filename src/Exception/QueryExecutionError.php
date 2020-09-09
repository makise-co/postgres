<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Exception;

use MakiseCo\SqlCommon\Exception\QueryError;

class QueryExecutionError extends QueryError
{
    /** @var array<string, mixed>|mixed[] */
    private array $diagnostics;

    /**
     * QueryExecutionError constructor.
     *
     * @param string $message
     * @param int $code
     * @param array<string, mixed> $diagnostics
     * @param \Throwable|null $previous
     * @param string $query
     */
    public function __construct(
        string $message,
        int $code,
        array $diagnostics,
        \Throwable $previous = null,
        string $query = ''
    ) {
        parent::__construct($message, $query, $previous);

        $this->code = $code;
        $this->diagnostics = $diagnostics;
    }

    /**
     * @return array<string, mixed>|mixed[]
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }
}
