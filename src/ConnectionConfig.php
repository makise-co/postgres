<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use InvalidArgumentException;
use MakiseCo\Connection\ConnectionConfig as BaseConnectionConfig;

use function addcslashes;
use function array_key_exists;
use function implode;
use function in_array;

class ConnectionConfig extends BaseConnectionConfig
{
    public const SSL_MODES = [
        'disable',
        'allow',
        'prefer',
        'require',
        'verify-ca',
        'verify-full',
    ];

    public const GSS_MODES = [
        'disable',
        'prefer',
        'require',
    ];

    public const REPLICATION_MODES = [
        'true',
        'on',
        'yes',
        '1',
        'database',
        'false',
        'false',
        'off',
        'no',
        '0',
    ];

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
        bool $unbuffered = false
    ) {
        $this->validateOptions($options);

        parent::__construct($host, $port, $user, $password, $database);

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
            'host=' . $this->getHost(),
            'port=' . $this->getPort(),
            'user=' . $this->escapeValue($this->getUser()),
        ];

        $password = $this->getPassword();
        if ($password) {
            $parts[] = 'password=' . $this->escapeValue($password);
        }

        $database = $this->getDatabase();
        if ($database) {
            $parts[] = 'dbname=' . $this->escapeValue($database);
        }

        foreach ($this->options as $option => $value) {
            if ($option === 'options') {
                continue;
            }

            $parts[] = "{$option}={$this->escapeValue($value)}";
        }

        $commands = [];

        foreach ((array)($this->options['options'] ?? []) as $optionKey => $optionValue) {
            $commands[] = "{$optionKey}={$optionValue}";
        }

        if ([] !== $commands) {
            $value = '-c' . implode(' -c', $commands);

            $parts[] = "options={$this->escapeValue($value)}";
        }

        return $this->dsn = implode(' ', $parts);
    }

    /**
     * @return string[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @inheritDoc
     */
    public function getConnectionString(): string
    {
        return $this->__toString();
    }

    protected function escapeValue(string $value): string
    {
        if ('' === $value) {
            return $value;
        }

        $escaped = addcslashes($value, "'\\");

        return "'{$escaped}'";
    }

    private function validateOptions(array $options): void
    {
        if (array_key_exists('sslmode', $options)
            && !in_array($options['sslmode'], self::SSL_MODES, true)) {
            throw new InvalidArgumentException(
                'Invalid SSL mode, must be one of: ' . implode(', ', self::SSL_MODES)
            );
        }

        if (array_key_exists('gssencmode', $options)
            && !in_array($options['gssencmode'], self::GSS_MODES, true)) {
            throw new InvalidArgumentException(
                'Invalid GSS enc mode, must be one of: ' . implode(', ', self::GSS_MODES)
            );
        }

        if (array_key_exists('replication', $options)
            && !in_array($options['replication'], self::REPLICATION_MODES, true)) {
            throw new InvalidArgumentException(
                'Invalid replication mode, must be one of: '
                . implode(', ', self::REPLICATION_MODES)
            );
        }
    }
}
