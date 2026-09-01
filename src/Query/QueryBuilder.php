<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Query;

use ksfraser\CommonDb\Contract\DbConnectionInterface;

/**
 * Fluent, parameterized SELECT query builder.
 *
 * Builds a SELECT statement with named (:name) placeholders, producing a SQL
 * string and a params array consumable by any DbConnectionInterface (PDO or FA).
 *
 * Generic version of the legacy ksf_modules_common MODEL clause builders
 * (buildSelect/buildFrom/buildWhere/buildJoin/buildGroupBy/buildHaving/
 * buildOrderBy/buildLimit), rewritten to be transport-agnostic and to use
 * bound parameters rather than interpolated literals.
 *
 * @package ksfraser\CommonDb
 * @since   1.0.0
 */
class QueryBuilder
{
    /** @var array<int, string> */
    private $select = ['*'];

    /** @var string|null */
    private $from;

    /** @var array<int, string> */
    private $where = [];

    /** @var array<int, string> */
    private $joins = [];

    /** @var array<int, string> */
    private $groupBy = [];

    /** @var array<int, string> */
    private $having = [];

    /** @var array<int, string> */
    private $orderBy = [];

    /** @var int|null */
    private $limit;

    /** @var int|null */
    private $offset;

    /** @var array<string, mixed> */
    private $params = [];

    /**
     * @param string|array<int,string> $columns
     * @return static
     */
    public function select($columns = ['*']): self
    {
        $this->select = is_array($columns) ? $columns : [$columns];

        return $this;
    }

    /**
     * Add a SELECT column.
     *
     * @param string $column
     * @return static
     */
    public function addSelect(string $column): self
    {
        $this->select[] = $column;

        return $this;
    }

    /**
     * @param string $table
     * @return static
     */
    public function from(string $table): self
    {
        $this->from = $table;

        return $this;
    }

    /**
     * Add a WHERE condition. Use named placeholders, e.g. "stock_id = :id".
     * Each call is AND-ed with prior conditions.
     *
     * @param string $condition
     * @param array<string,mixed> $params Parameters for this condition
     * @return static
     */
    public function where(string $condition, array $params = []): self
    {
        $this->where[] = $condition;
        $this->mergeParams($params);

        return $this;
    }

    /**
     * Add a JOIN clause.
     *
     * @param string $clause e.g. "INNER JOIN foo ON foo.id = bar.foo_id"
     * @return static
     */
    public function join(string $clause): self
    {
        $this->joins[] = $clause;

        return $this;
    }

    /**
     * @param string $column
     * @return static
     */
    public function groupBy(string $column): self
    {
        $this->groupBy[] = $column;

        return $this;
    }

    /**
     * Add a HAVING condition.
     *
     * @param string $condition
     * @param array<string,mixed> $params
     * @return static
     */
    public function having(string $condition, array $params = []): self
    {
        $this->having[] = $condition;
        $this->mergeParams($params);

        return $this;
    }

    /**
     * @param string $column e.g. "created_at DESC"
     * @return static
     */
    public function orderBy(string $column): self
    {
        $this->orderBy[] = $column;

        return $this;
    }

    /**
     * @param int $limit
     * @param int|null $offset
     * @return static
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit  = $limit;
        $this->offset = $offset;

        return $this;
    }

    /**
     * The SQL statement with named (:name) placeholders.
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->select)
            . ' FROM ' . $this->from;

        foreach ($this->joins as $join) {
            $sql .= ' ' . $join;
        }

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if (!empty($this->having)) {
            $sql .= ' HAVING ' . implode(' AND ', $this->having);
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        return $sql;
    }

    /**
     * The bound parameter values keyed by placeholder name.
     *
     * @return array<string,mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Convenience: run the query through a DbConnectionInterface in one call.
     *
     * @param DbConnectionInterface|null $db
     * @return array<int, array>
     */
    public function fetch(?DbConnectionInterface $db): array
    {
        return $db ? $db->fetchAll($this->toSql(), $this->getParams()) : [];
    }

    /**
     * @param array<string,mixed> $params
     * @return void
     */
    private function mergeParams(array $params): void
    {
        foreach ($params as $key => $value) {
            $this->params[$key] = $value;
        }
    }
}
