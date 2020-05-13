<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use function addcslashes;
use function implode;

class ConnectConfig
{
    private string $host;
    private int $port;
    private string $user;
    private ?string $password;
    private ?string $database;
    private array $options;
    private float $connectTimeout;
    private bool $unbuffered;

    private string $dsn = '';

    /**
     * ConnectConfig constructor.
     *
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string|null $password
     * @param string|null $database
     * @param string[] $options
     * @param float $connectTimeout
     * @param bool $unbuffered should Postgres client work in unbuffered mode?
     *
     */
    public function __construct(
        string $host,
        int $port,
        string $user,
        ?string $password = null,
        ?string $database = null,
        array $options = [],
        float $connectTimeout = 2,
        bool $unbuffered = true
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->options = $options;
        $this->connectTimeout = $connectTimeout;
        $this->unbuffered = $unbuffered;
    }

    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function getUnbuffered(): bool
    {
        return $this->unbuffered;
    }

    public function __toString(): string
    {
        if ('' !== $this->dsn) {
            return $this->dsn;
        }

        $parts = [
            'host=' . $this->host,
            'port=' . $this->port,
            'user=' . $this->escapeValue($this->user),
        ];

        if ($this->password) {
            $parts[] = 'password=' . $this->escapeValue($this->password);
        }

        if ($this->database) {
            $parts[] = 'dbname=' . $this->escapeValue($this->database);
        }

        foreach ($this->options as $option => $value) {
            $parts[] = "{$option}={$this->escapeValue($value)}";
        }

        return $this->dsn = implode(' ', $parts);
    }

    protected function escapeValue(string $value): string
    {
        if ('' === $value) {
            return $value;
        }

        $escaped = addcslashes($value, "'\\");

        return "'{$escaped}'";
    }
}