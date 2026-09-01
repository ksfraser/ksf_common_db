<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Tests\Support;

/**
 * In-memory state + fake result for the FA db_* function stubs.
 *
 * @package ksfraser\CommonDb\Tests
 */
final class FaState
{
    /** @var array<string, string> */
    public static $lastQuery = [];

    /** @var array<string, array<int, array>> keyed by exact SQL */
    public static $queryResults = [];

    /** @var int */
    public static $affectedRows = 0;

    /** @var int */
    public static $insertId = 0;

    /** @var bool */
    public static $queryReturnsNull = false;

    public static function reset(): void
    {
        self::$lastQuery = [];
        self::$queryResults = [];
        self::$affectedRows = 0;
        self::$insertId = 0;
        self::$queryReturnsNull = false;
    }
}

/**
 * Simple in-memory result cursor consumed by db_fetch_assoc/db_fetch_row.
 *
 * @package ksfraser\CommonDb\Tests
 */
final class FakeResult
{
    /** @var array<int, array> */
    private $rows;

    /** @var int */
    private $cursor = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function next()
    {
        if ($this->cursor >= count($this->rows)) {
            return false;
        }

        return $this->rows[$this->cursor++];
    }

    public function countRows(): int
    {
        return count($this->rows);
    }
}
