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
use MakiseCo\Postgres\Contracts\Transaction;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\Transaction as SqlTransaction;
use MakiseCo\SqlCommon\Exception\TransactionError;
use MakiseCo\SqlCommon\PooledTransaction as SqlPooledTransaction;

class PooledTransaction extends SqlPooledTransaction implements Transaction
{
    private SqlTransaction $transaction;

    public function __construct(ConnectionTransaction $transaction, Closure $release)
    {
        $this->transaction = $transaction;

        parent::__construct($transaction, $release);
    }

    /**
     * {@inheritDoc}
     */
    public function notify(string $channel, string $payload = ''): CommandResult
    {
        if (!$this->isActive()) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->notify($channel, $payload);
    }

    /**
     * @inheritDoc
     */
    public function quoteString(string $data): string
    {
        return $this->transaction->quoteString($data);
    }

    /**
     * @inheritDoc
     */
    public function quoteName(string $name): string
    {
        return $this->transaction->quoteName($name);
    }
}
