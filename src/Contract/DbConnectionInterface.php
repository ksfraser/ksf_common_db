<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Contract;

/**
 * PDO-shaped, transport-agnostic database connection contract.
 *
 * This is the interface DAO/Repository classes code against. It is shaped like
 * PDO's operations so the standalone adapter (PdoDbAdapter) is a near 1:1
 * wrapper, while the FrontAccounting adapter (FaDbAdapter) translates the same
 * operations to FA's procedural db_* / mysqli calls (which has NO prepared
 * statements, so params are escaped and inlined).
 *
 * HARD RULE: Inside FrontAccounting, the FaDbAdapter MUST translate every
 * operation to native db_* calls. PDO is only for the standalone/portable
 * side (tests, CLI, non-FA embedding). DI selects the adapter; FA code never
 * touches PDO directly.
 *
 * Parameter binding: both implementations accept SQL with positional (?) or
 * named (:name) placeholders. PdoDbAdapter uses native prepared statements;
 * FaDbAdapter resolves placeholders to escaped literals.
 *
 * @package ksfraser\CommonDb
 * @since   1.0.0
 */
interface DbConnectionInterface
{
    /**
     * Fetch a single row as an associative (column => value) array.
     *
     * @param string $sql    SQL with ? or :name placeholders
     * @param array  $params Parameter values
     * @return array|null Row data, or null if no row matched
     */
    public function fetchAssoc(string $sql, array $params = []): ?array;

    /**
     * Fetch all rows as an array of associative arrays.
     *
     * @param string $sql    SQL with ? or :name placeholders
     * @param array  $params Parameter values
     * @return array<int, array>
     */
    public function fetchAll(string $sql, array $params = []): array;

    /**
     * Fetch a single scalar value (e.g. COUNT(*), MAX(id), a single column).
     *
     * @param string $sql       SQL with ? or :name placeholders
     * @param array  $params    Parameter values
     * @param mixed  $default   Value returned when no row matches
     * @return mixed
     */
    public function fetchScalar(string $sql, array $params = [], $default = null);

    /**
     * Execute an INSERT, UPDATE, DELETE (or DDL) statement.
     *
     * @param string $sql    SQL with ? or :name placeholders
     * @param array  $params Parameter values
     * @return int Number of affected rows
     */
    public function executeUpdate(string $sql, array $params = []): int;

    /**
     * Return the last auto-increment ID from an INSERT.
     *
     * @return int
     */
    public function lastInsertId(): int;

    /**
     * Quote/escape a literal value for safe inline interpolation.
     *
     * Only needed by the FA adapter's non-prepared path and by dynamic
     * identifiers; prefer parameter binding elsewhere.
     *
     * @param mixed $value
     * @return string
     */
    public function quote($value): string;

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit the active transaction.
     */
    public function commit(): void;

    /**
     * Roll back the active transaction.
     */
    public function rollBack(): void;
}
