<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Read-only wrapper around a database connection.
 *
 * Every SQL write in Laravel's query builder goes through one of the six
 * write methods on ConnectionInterface. Blocking them here blocks every
 * write path at once, including builder methods added in future releases.
 *
 * Reads, transactions and all other connection methods pass through to
 * the wrapped connection unchanged.
 */
final class ReadOnlyConnection implements ConnectionInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Get the wrapped connection.
     */
    public function getWrappedConnection(): Connection
    {
        return $this->connection;
    }

    // =========================================================================
    // WRITES - ALL THROW
    // =========================================================================

    /**
     * @param array<int|string, mixed> $bindings
     * @throws ImmutableModelViolationException
     */
    public function insert($query, $bindings = []): never
    {
        throw ImmutableModelViolationException::writeAttempt('insert', $query);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @throws ImmutableModelViolationException
     */
    public function update($query, $bindings = []): never
    {
        throw ImmutableModelViolationException::writeAttempt('update', $query);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @throws ImmutableModelViolationException
     */
    public function delete($query, $bindings = []): never
    {
        throw ImmutableModelViolationException::writeAttempt('delete', $query);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @throws ImmutableModelViolationException
     */
    public function statement($query, $bindings = []): never
    {
        throw ImmutableModelViolationException::writeAttempt('statement', $query);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @throws ImmutableModelViolationException
     */
    public function affectingStatement($query, $bindings = []): never
    {
        throw ImmutableModelViolationException::writeAttempt('affectingStatement', $query);
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function unprepared($query): never
    {
        throw ImmutableModelViolationException::writeAttempt('unprepared', $query);
    }

    // =========================================================================
    // READS AND EVERYTHING ELSE - DELEGATED
    // =========================================================================

    /**
     * Begin a fluent query against a database table, bound to this read-only connection.
     *
     * @param \Closure|\Illuminate\Database\Query\Builder|string $table
     * @param string|null $as
     * @return QueryBuilder
     */
    public function table($table, $as = null)
    {
        return $this->query()->from($table, $as);
    }

    /**
     * Get a new query builder instance bound to this read-only connection.
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder(
            $this,
            $this->connection->getQueryGrammar(),
            $this->connection->getPostProcessor()
        );
    }

    public function raw($value)
    {
        return $this->connection->raw($value);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
        return $this->connection->selectOne($query, $bindings, $useReadPdo);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function scalar($query, $bindings = [], $useReadPdo = true)
    {
        return $this->connection->scalar($query, $bindings, $useReadPdo);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int, object>
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->connection->select($query, $bindings, $useReadPdo);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        return $this->connection->cursor($query, $bindings, $useReadPdo);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int|string, mixed>
     */
    public function prepareBindings(array $bindings)
    {
        return $this->connection->prepareBindings($bindings);
    }

    /**
     * Run a transaction on the wrapped connection.
     *
     * The callback receives this read-only connection, not the wrapped one,
     * so writes inside the transaction still throw.
     *
     * @template TReturn
     *
     * @param Closure(static): TReturn $callback
     * @param int $attempts
     * @return TReturn
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        return $this->connection->transaction(fn () => $callback($this), $attempts);
    }

    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }

    public function commit()
    {
        $this->connection->commit();
    }

    public function rollBack()
    {
        $this->connection->rollBack();
    }

    public function transactionLevel()
    {
        return $this->connection->transactionLevel();
    }

    /**
     * Run the callback in "pretend" mode on the wrapped connection.
     *
     * The callback receives this read-only connection, not the wrapped one.
     *
     * @param Closure(static): mixed $callback
     * @return array<int, array<string, mixed>>
     */
    public function pretend(Closure $callback)
    {
        return $this->connection->pretend(fn () => $callback($this));
    }

    public function getDatabaseName()
    {
        return $this->connection->getDatabaseName();
    }

    /**
     * Forward all other calls (getQueryGrammar, getDriverName, getName, ...)
     * to the wrapped connection.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connection->{$method}(...$parameters);
    }
}
