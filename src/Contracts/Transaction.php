<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres\Contracts;

use MakiseCo\SqlCommon\Contracts\Transaction as SqlTransaction;

interface Transaction extends Executor, Quoter, SqlTransaction
{
}
