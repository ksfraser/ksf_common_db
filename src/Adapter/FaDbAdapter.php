<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Adapter;

use ksfraser\CommonDb\Contract\DbConnectionInterface;

/**
 * FrontAccounting database adapter.
 *
 * Implements DbConnectionInterface on top of FA's procedural db_* / mysqli
 * API. FA has NO prepared statements, so ? and :name placeholders are resolved
 * to safely escaped literals using mysqli_real_escape_string() before the SQL
 * is passed to db_query().
 *
 * HARD RULE: This adapter MUST use native db_* calls at runtime (db_query,
 * db_fetch_assoc, db_num_rows, db_num_affected_rows, db_insert_id, db_error,
 * db_begin_transaction / db_commit_transaction / db_rollback_transaction).
 * It never uses a PDO handle nor raw mysqli_* connect/query.
 *
 * @package ksfraser\CommonDb
 * @since   1.0.0
 */
class FaDbAdapter implements DbConnectionInterface
{
    /** @var string FA table prefix (value of TB_PREF, e.g. "0_") */
    private $tablePrefix;

    /**
     * @param string $tablePrefix FA table prefix (value of the TB_PREF constant)
     */
    public function __construct(string $tablePrefix = '')
    {
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * Prefix table names with the configured prefix.
     *
     * Only bare table names in table position are prefixed: tokens following
     * FROM, JOIN, INTO, UPDATE, TABLE. Already-prefixed names (e.g.
     * 0_stock_master) are skipped so they are not double-prefixed. SQL keywords
     * and column identifiers are left untouched.
     *
     * @param string $sql
     * @return string
     */
    private function prefixTables(string $sql): string
    {
        if ($this->tablePrefix === '') {
            return $sql;
        }

        return preg_replace_callback(
            '/(\b(?:FROM|JOIN|INTO|UPDATE|TABLE)\s+)(`?)([a-zA-Z_][a-zA-Z0-9_]*)(`?)(\s+[a-zA-Z_][a-zA-Z0-9_]*)?/i',
            function (array $m): string {
                $keyword = $m[1];
                $pre     = $m[2];   // leading backtick if any
                $name    = $m[3];
                $post    = $m[4];   // trailing backtick if any
                $rest    = $m[5] ?? ''; // alias

                // Backticked tokens are always table names; prefix them.
                if ($pre === '`') {
                    return $keyword . $pre . $this->tablePrefix . $name . $post . $rest;
                }

                // Bare tokens: skip already-prefixed names.
                if (strpos($name, $this->tablePrefix) === 0) {
                    return $m[0];
                }

                return $keyword . $pre . $this->tablePrefix . $name . $post . $rest;
            },
            $sql
        );
    }

    /**
     * Resolve ? and :name placeholders to safely escaped literal values.
     *
     * @param string $sql
     * @param array  $params
     * @return string
     */
    private function bindParams(string $sql, array $params): string
    {
        if (empty($params)) {
            return $sql;
        }

        $isAssoc = array_keys($params) !== range(0, count($params) - 1);

        if ($isAssoc) {
            // Named placeholders: :name
            foreach ($params as $name => $value) {
                $sql = str_replace(':' . $name, $this->quote($value), $sql);
            }
            return $sql;
        }

        // Positional placeholders: ?
        $parts = explode('?', $sql);
        if (count($parts) !== count($params) + 1) {
            return $sql;
        }

        $result = $parts[0];
        foreach ($params as $i => $value) {
            $result .= $this->quote($value) . $parts[$i + 1];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        global $db;
        $escaped = isset($db)
            ? mysqli_real_escape_string($db, (string) $value)
            : addslashes((string) $value);

        return "'" . $escaped . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssoc(string $sql, array $params = []): ?array
    {
        $sql    = $this->prefixTables($sql);
        $sql    = $this->bindParams($sql, $params);
        $result = db_query($sql);

        if (!$result) {
            return null;
        }

        $row = db_fetch_assoc($result);

        return $row !== false && $row !== null ? (array) $row : null;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $sql    = $this->prefixTables($sql);
        $sql    = $this->bindParams($sql, $params);
        $result = db_query($sql);

        if (!$result) {
            return [];
        }

        $rows = [];
        while (($row = db_fetch_assoc($result)) !== false && $row !== null) {
            $rows[] = (array) $row;
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchScalar(string $sql, array $params = [], $default = null)
    {
        $row = $this->fetchAssoc($sql, $params);
        if ($row === null) {
            return $default;
        }

        $first = reset($row);
        if ($first === false || $first === null) {
            return $default;
        }

        return $first;
    }

    /**
     * {@inheritdoc}
     */
    public function executeUpdate(string $sql, array $params = []): int
    {
        $sql = $this->prefixTables($sql);
        $sql = $this->bindParams($sql, $params);
        db_query($sql);

        return (int) db_num_affected_rows();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(): int
    {
        return (int) db_insert_id();
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): void
    {
        if (function_exists('db_begin_transaction')) {
            db_begin_transaction();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        if (function_exists('db_commit_transaction')) {
            db_commit_transaction();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack(): void
    {
        if (function_exists('db_rollback_transaction')) {
            db_rollback_transaction();
        }
    }
}
