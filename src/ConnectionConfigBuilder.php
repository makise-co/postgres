<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Postgres;

use function array_key_exists;
use function implode;

class ConnectionConfigBuilder
{
    private ?string $host = '127.0.0.1';
    private int $port = 5432;
    private ?string $user = 'postgres';
    private ?string $password = null;
    private ?string $database = null;

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
     * @param string|null $sslMode
     * @return self
     */
    public function withSslMode(?string $sslMode): self
    {
        if ($sslMode) {
            $this->options['sslmode'] = $sslMode;
        } else {
            unset($this->options['sslmode']);
        }

        return $this;
    }

    /**
     * @param string|null $replication
     * @return self
     */
    public function withReplication(?string $replication): self
    {
        if ($replication) {
            $this->options['replication'] = $replication;
        } else {
            unset($this->options['replication']);
        }

        return $this;
    }

    /**
     * @param string|null $gssEncMode
     * @return self
     */
    public function withGssEncMode(?string $gssEncMode): self
    {
        if ($gssEncMode) {
            $this->options['gssencmode'] = $gssEncMode;
        } else {
            unset($this->options['gssencmode']);
        }

        return $this;
    }

    /**
     * @param string|null $timezone
     * @return self
     */
    public function withTimezone(?string $timezone): self
    {
        if ($timezone) {
            $this->options['options']['timezone'] = $timezone;
        } else {
            unset($this->options['options']['timezone']);
        }

        return $this;
    }

    /**
     * @param string|null $encoding
     * @return self
     */
    public function withEncoding(?string $encoding): self
    {
        if ($encoding) {
            $this->options['client_encoding'] = $encoding;
        } else {
            unset($this->options['client_encoding']);
        }

        return $this;
    }

    /**
     * @param string[] $schemas
     * @return self
     */
    public function withSearchPath(array $schemas): self
    {
        if ($schemas !== []) {
            $this->options['options']['search_path'] = implode(',', $schemas);
        } else {
            unset($this->options['options']);
        }

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
        if ($applicationName) {
            return $this->withOption('application_name', $applicationName);
        }

        return $this->withoutOption('application_name');
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
     *  'optionName' => 'optionValue,
     * ]
     *
     */
    public function fromArray(array $params): self
    {
        /** @var mixed $value */
        $value = null;

        if ($this->getAliasedValue('host', $params, $value)) {
            $this->withHost($value);
        }

        if ($this->getAliasedValue('port', $params, $value)) {
            $this->withPort((int)$params['port']);
        }

        if ($this->getAliasedValue('user', $params, $value)) {
            $this->withUser($value);
        }

        if ($this->getAliasedValue('password', $params, $value)) {
            $this->withPassword($value);
        }

        if ($this->getAliasedValue('database', $params, $value)) {
            $this->withDatabase($value);
        }

        if ($this->getAliasedValue('replication', $params, $value)) {
            $this->withReplication($value);
        }

        if ($this->getAliasedValue('sslmode', $params, $value)) {
            $this->withSslMode($value);
        }

        if ($this->getAliasedValue('gssencmode', $params, $value)) {
            $this->withGssEncMode($value);
        }

        $this->applyOption('sslcert', $params);
        $this->applyOption('sslkey', $params);
        $this->applyOption('sslrootcert', $params);
        $this->applyOption('sslcrl', $params);
        $this->applyOption('requirepeer', $params);
        $this->applyOption('krbsrvname', $params);
        $this->applyOption('gsslib', $params);
        $this->applyOption('target_session_attrs', $params);

        if ($this->getAliasedValue('client_encoding', $params, $value)) {
            $this->withEncoding($value);
        }

        if ($this->getAliasedValue('application_name', $params, $value)) {
            $this->withApplicationName($value);
        }

        if ($this->getAliasedValue('search_path', $params, $value)) {
            $this->withSearchPath((array)$value);
        }

        if ($this->getAliasedValue('timezone', $params, $value)) {
            $this->withTimezone($value);
        }

        if ($this->getAliasedValue('connect_timeout', $params, $value)) {
            $this->withConnectTimeout($value);
        }

        if ($this->getAliasedValue('unbuffered', $params, $value)) {
            $this->withUnbuffered($value);
        }

        return $this;
    }

    public function fromConfig(ConnectionConfig $config): self
    {
        $this->withHost($config->getHost());
        $this->withPort($config->getPort());
        $this->withUser($config->getUser());
        $this->withPassword($config->getPassword());
        $this->withDatabase($config->getDatabase());

        foreach ($config->getOptions() as $optionName => $optionValue) {
            $this->withOption($optionName, $optionValue);
        }

        $this->withUnbuffered($config->getUnbuffered());
        $this->withConnectTimeout($config->getConnectTimeout());

        return $this;
    }

    /**
     * Build connect config
     *
     * @return ConnectionConfig
     */
    public function build(): ConnectionConfig
    {
        return new ConnectionConfig(
            $this->host,
            $this->port,
            $this->user,
            $this->password,
            $this->database,
            $this->options,
            $this->connectTimeout,
            $this->unbuffered
        );
    }

    private function applyOption(string $key, array $params): void
    {
        if ($this->getAliasedValue($key, $params, $value)) {
            $this->withOption($key, $value);
        } else {
            $this->withoutOption($key);
        }
    }

    private function getAliasedValue(string $key, array $params, &$value): bool
    {
        if (array_key_exists($key, $params)) {
            $value = $params[$key];

            return true;
        }

        foreach (self::ALIASES[$key] ?? [] as $alias) {
            if (array_key_exists($alias, $params)) {
                $value = $params[$alias];

                return true;
            }
        }

        return false;
    }

    private const ALIASES = [
        'user' => ['username'],
        'dbname' => ['database'],
        'client_encoding' => ['charset', 'encoding'],
        'search_path' => ['schema'],
        'connect_timeout' => ['timeout'],
    ];
}
