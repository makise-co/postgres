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

use function array_merge;
use function implode;
use function property_exists;

class ConnectionConfigBuilder
{
    private ?string $host = '127.0.0.1';
    private int $port = 5432;
    private ?string $user = 'postgres';
    private ?string $password = null;
    private ?string $database = null;

    private ?string $applicationName = null;
    private ?string $timezone = null;
    private ?string $encoding = null;
    private array $searchPath = [];
    private float $connectTimeout = 0;
    private bool $unbuffered = false;
    private array $options = [];

    /**
     * @param string $host
     * @return self
     */
    public function withHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    /**
     * @param int $port
     * @return self
     */
    public function withPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    /**
     * @param string $user
     * @return self
     */
    public function withUser(string $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @param string|null $password
     * @return self
     */
    public function withPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @param string|null $database
     * @return self
     */
    public function withDatabase(?string $database): self
    {
        $this->database = $database;
        return $this;
    }

    /**
     * @param string|null $timezone
     * @return self
     */
    public function withTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * @param string|null $encoding
     * @return self
     */
    public function withEncoding(?string $encoding): self
    {
        $this->encoding = $encoding;
        return $this;
    }

    /**
     * @param string[] $schemas
     * @return self
     */
    public function withSearchPath(array $schemas): self
    {
        $this->searchPath = $schemas;
        return $this;
    }

    /**
     * @param float $connectTimeout
     * @return self
     */
    public function withConnectTimeout(float $connectTimeout): self
    {
        $this->connectTimeout = $connectTimeout;
        return $this;
    }

    /**
     * @param bool $unbuffered
     * @return self
     */
    public function withUnbuffered(bool $unbuffered): self
    {
        $this->unbuffered = $unbuffered;
        return $this;
    }

    /**
     * @param string|null $applicationName
     * @return self
     */
    public function withApplicationName(?string $applicationName): self
    {
        $this->applicationName = $applicationName;
        return $this;
    }

    /**
     * @see https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNECT-OPTIONS
     *
     * @param string $option
     * @param string $value
     *
     * @return self
     */
    public function withOption(string $option, string $value): self
    {
        $this->options[$option] = $value;
        return $this;
    }

    /**
     * @param string $option
     * @return $this
     */
    public function withoutOption(string $option): self
    {
        unset($this->options[$option]);
        return $this;
    }

    /**
     * Apply options array at once
     *
     * @param string[]|int[] $params
     * @return $this
     * @example [
     *  'host' => '127.0.0.1',
     *  'port' => 5432,
     *  'user' => 'makise',
     *  propertyName => propertyValue,
     * ]
     *
     */
    public function fromArray(array $params): self
    {
        foreach ($params as $key => $value) {
            if ($key === 'application_name') {
                $key = 'applicationName';
            } elseif ($key === 'connect_timeout') {
                $key = 'connectTimeout';
            } elseif ($key === 'dbname') {
                $key = 'database';
            } elseif ($key === 'username') {
                $key = 'user';
            } elseif ($key === 'charset') {
                $key = 'encoding';
            } elseif ($key === 'schema' || $key === 'search_path') {
                $key = 'searchPath';
                $value = (array)$value;
            } elseif (!property_exists($this, $key)) {
                throw new InvalidArgumentException("Option {$key} does not exists");
            }

            $this->{$key} = $value;
        }

        return $this;
    }

    /**
     * Build connect config
     *
     * @return ConnectionConfig
     */
    public function build(): ConnectionConfig
    {
        $options = [];
        $commands = [];

        if ($this->applicationName) {
            $options['application_name'] = $this->applicationName;
        }

        if ($this->encoding) {
            $options['client_encoding'] = $this->encoding;
        }

        if ($this->searchPath) {
            $searchPath = implode(',', $this->searchPath);
            $commands[] = "search_path={$searchPath}";
        }

        if ($this->timezone) {
            $commands[] = "timezone={$this->timezone}";
        }

        if ([] !== $commands) {
            $options['options'] = '-c' . implode(' -c', $commands);
        }

        $options = array_merge($this->options, $options);

        return new ConnectionConfig(
            $this->host,
            $this->port,
            $this->user,
            $this->password,
            $this->database,
            $options,
            $this->connectTimeout,
            $this->unbuffered
        );
    }
}
