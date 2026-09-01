<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Dictionary;

/**
 * Data dictionary definition for a single table.
 *
 * Describes a table's schema (columns, primary key, indexes, engine, charset)
 * independent of how it is persisted. A repository/DTO uses a TableDefinition
 * to know its fields, to CREATE/ALTER the table, and to build CRUD queries.
 *
 * This is the generically-useful core of the legacy ksf_modules_common MODEL
 * data dictionary, stripped of FA/transport concerns.
 *
 * @package ksfraser\CommonDb
 * @since   1.0.0
 */
class TableDefinition
{
    /** @var string */
    private $name;

    /** @var string|null */
    private $primaryKey;

    /** @var string */
    private $engine;

    /** @var string */
    private $charset;

    /** @var array<int, array<string, mixed>> each: name,type,null,auto_increment,default */
    private $columns = [];

    /** @var array<int, array<string, mixed>> each: keyname,type,columns */
    private $indexes = [];

    /**
     * @param string      $name       Logical (unprefixed) table name
     * @param string|null $primaryKey Primary key column name
     * @param string      $engine     MySQL engine (e.g. InnoDB)
     * @param string      $charset    MySQL charset (e.g. utf8mb4)
     */
    public function __construct(string $name, ?string $primaryKey = null, string $engine = 'InnoDB', string $charset = 'utf8mb4')
    {
        $this->name       = $name;
        $this->primaryKey = $primaryKey;
        $this->engine     = $engine;
        $this->charset    = $charset;
    }

    /**
     * Add a column definition.
     *
     * @param string      $name
     * @param string      $type          e.g. "int(11)", "varchar(32)", "timestamp"
     * @param string|null $null          "NOT NULL" or null for nullable
     * @param bool        $autoIncrement
     * @param mixed|null  $default
     * @return $this
     */
    public function column(string $name, string $type, ?string $null = null, bool $autoIncrement = false, $default = null): self
    {
        $this->columns[] = [
            'name'           => $name,
            'type'           => $type,
            'null'           => $null,
            'auto_increment' => $autoIncrement,
            'default'        => $default,
        ];

        return $this;
    }

    /**
     * Add an index / unique key definition.
     *
     * @param string $keyname
     * @param string $type    "unique" or "index"
     * @param string $columns Comma-separated column list
     * @return $this
     */
    public function index(string $keyname, string $type, string $columns): self
    {
        $this->indexes[] = [
            'keyname' => $keyname,
            'type'    => $type,
            'columns' => $columns,
        ];

        return $this;
    }

    /**
     * Column names (in definition order).
     *
     * @return array<int, string>
     */
    public function columns(): array
    {
        return array_column($this->columns, 'name');
    }

    /**
     * Whether a column with the given name is defined.
     *
     * @param string $name
     * @return bool
     */
    public function hasColumn(string $name): bool
    {
        return in_array($name, $this->columns(), true);
    }

    /**
     * Columns writable on insert/update (i.e. not the auto-increment primary key).
     *
     * @return array<int, string> Column names
     */
    public function writableColumns(): array
    {
        return array_values(array_filter(
            array_column($this->columns, 'name'),
            function (string $name): bool {
                $col = $this->columnDef($name);
                return !($col['auto_increment'] ?? false);
            }
        ));
    }

    /**
     * Get the definition array for a named column.
     *
     * @param string $name
     * @return array<string, mixed>|null
     */
    private function columnDef(string $name): ?array
    {
        foreach ($this->columns as $col) {
            if (($col['name'] ?? null) === $name) {
                return $col;
            }
        }

        return null;
    }

    /**
     * The logical (unprefixed) table name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * The primary key column name.
     *
     * @return string|null
     */
    public function primaryKey(): ?string
    {
        return $this->primaryKey;
    }

    /**
     * Render a CREATE TABLE (IF NOT EXISTS) statement.
     *
     * The returned SQL has no prefix; the adapter's prefixing is responsible
     * for the physical name. Use with executeUpdate().
     *
     * @return string
     */
    public function createSql(): string
    {
        $lines = [];
        foreach ($this->columns as $col) {
            $line = "`" . $col['name'] . "` " . $col['type'];
            if (!empty($col['null'])) {
                $line .= ' ' . $col['null'];
            }
            if (!empty($col['auto_increment'])) {
                $line .= ' AUTO_INCREMENT';
            }
            if ($col['default'] !== null) {
                $line .= ' DEFAULT ' . $col['default'];
            }
            $lines[] = $line;
        }

        if ($this->primaryKey !== null) {
            $lines[] = 'PRIMARY KEY (`' . $this->primaryKey . '`)';
        }

        foreach ($this->indexes as $idx) {
            $keyword = strtolower($idx['type'] ?? '') === 'unique' ? 'UNIQUE KEY' : 'KEY';
            $lines[] = $keyword . ' `' . $idx['keyname'] . '` (' . $idx['columns'] . ')';
        }

        return 'CREATE TABLE IF NOT EXISTS `' . $this->name . '` (' . "\n"
            . implode(",\n", $lines) . "\n"
            . ') ENGINE=' . $this->engine
            . ' DEFAULT CHARSET=' . $this->charset;
    }

    /**
     * Render an INSERT statement (with :name placeholders) for all writable columns.
     *
     * @return string
     */
    public function insertSql(): string
    {
        $cols = $this->writableColumns();
        $cols = array_map(function ($c) { return '`' . $c . '`'; }, $cols);
        $params = array_map(function ($c) { return ':' . str_replace('`', '', $c); }, $cols);

        return 'INSERT INTO `' . $this->name . '` (' . implode(', ', $cols) . ')'
            . ' VALUES (' . implode(', ', $params) . ')';
    }

    /**
     * Render an UPDATE statement keyed by primary key (with :name placeholders).
     *
     * @param array<int, string> $columns Columns to update (default: all writable)
     * @return string
     */
    public function updateSql(array $columns = []): string
    {
        $setCols = $columns ?: $this->writableColumns();
        $setCols = array_diff($setCols, [$this->primaryKey]);

        $assignments = array_map(
            function ($c) {
                return '`' . $c . '` = :' . $c;
            },
            $setCols
        );

        return 'UPDATE `' . $this->name . '` SET ' . implode(', ', $assignments)
            . ' WHERE `' . $this->primaryKey . '` = :' . $this->primaryKey;
    }

    /**
     * Render a DELETE statement keyed by primary key.
     *
     * @return string
     */
    public function deleteSql(): string
    {
        return 'DELETE FROM `' . $this->name . '` WHERE `' . $this->primaryKey . '` = :' . $this->primaryKey;
    }
}
