<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Tests\Unit\Adapter;

use ksfraser\CommonDb\Adapter\FaDbAdapter;
use ksfraser\CommonDb\Tests\Support\FaState;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ksfraser\CommonDb\Adapter\FaDbAdapter
 */
class FaDbAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        FaState::reset();
    }

    public function testPrefixesBareTableNames(): void
    {
        $adapter = new FaDbAdapter('0_');
        FaState::$queryResults['__default__'] = [];

        $adapter->fetchAll('SELECT * FROM stock_master');

        $this->assertSame(
            'SELECT * FROM 0_stock_master',
            FaState::$lastQuery[0]
        );
    }

    public function testDoesNotDoublePrefixTables(): void
    {
        $adapter = new FaDbAdapter('0_');
        FaState::$queryResults['__default__'] = [];

        $adapter->fetchAll('SELECT * FROM 0_stock_master');

        $this->assertSame(
            'SELECT * FROM 0_stock_master',
            FaState::$lastQuery[0]
        );
    }

    public function testPositionalParamsAreEscapedAndInlined(): void
    {
        $adapter = new FaDbAdapter();
        FaState::$queryResults['__default__'] = [];

        $adapter->fetchAll(
            'SELECT * FROM stock_master WHERE stock_id = ? AND qty > ?',
            ['ABC\'123', 5]
        );

        $this->assertSame(
            "SELECT * FROM stock_master WHERE stock_id = 'ABC\\'123' AND qty > 5",
            FaState::$lastQuery[0]
        );
    }

    public function testNamedParamsAreInlined(): void
    {
        $adapter = new FaDbAdapter();
        FaState::$queryResults['__default__'] = [];

        $adapter->fetchAll(
            'SELECT * FROM stock_master WHERE stock_id = :id',
            ['id' => 'P-001']
        );

        $this->assertStringContainsString(
            "stock_id = 'P-001'",
            FaState::$lastQuery[0]
        );
    }

    public function testFetchAssocReturnsRow(): void
    {
        $adapter = new FaDbAdapter();
        FaState::$queryResults['__default__'] = [
            ['stock_id' => 'P-001', 'description' => 'Widget'],
        ];

        $row = $adapter->fetchAssoc('SELECT * FROM stock_master');

        $this->assertSame(['stock_id' => 'P-001', 'description' => 'Widget'], $row);
    }

    public function testFetchAssocReturnsNullWhenEmpty(): void
    {
        $adapter = new FaDbAdapter();
        FaState::$queryResults['__default__'] = [];

        $this->assertNull($adapter->fetchAssoc('SELECT * FROM stock_master'));
    }

    public function testFetchAllReturnsRows(): void
    {
        $adapter = new FaDbAdapter();
        FaState::$queryResults['__default__'] = [
            ['stock_id' => 'A'],
            ['stock_id' => 'B'],
        ];

        $rows = $adapter->fetchAll('SELECT * FROM stock_master');

        $this->assertCount(2, $rows);
        $this->assertSame([['stock_id' => 'A'], ['stock_id' => 'B']], $rows);
    }

    public function testFetchScalarReturnsFirstColumn(): void
    {
        $adapter = new FaDbAdapter();
        FaState::$queryResults['__default__'] = [
            ['count' => '3'],
        ];

        $this->assertSame('3', $adapter->fetchScalar('SELECT COUNT(*) as count FROM stock_master'));
    }

    public function testFetchScalarReturnsDefaultWhenEmpty(): void
    {
        $adapter = new FaDbAdapter();
        FaState::$queryResults['__default__'] = [];

        $this->assertSame(0, $adapter->fetchScalar('SELECT COUNT(*) FROM stock_master', [], 0));
    }

    public function testExecuteUpdateReturnsAffectedRows(): void
    {
        $adapter = new FaDbAdapter();
        FaState::$affectedRows = 4;
        FaState::$queryResults['__default__'] = [];

        $affected = $adapter->executeUpdate(
            'UPDATE stock_master SET inactive = 0 WHERE stock_id = ?',
            ['P-001']
        );

        $this->assertSame(4, $affected);
        $this->assertStringContainsString("stock_id = 'P-001'", FaState::$lastQuery[0]);
    }

    public function testLastInsertIdDelegatesToDbInsertId(): void
    {
        $adapter = new FaDbAdapter();
        FaState::$insertId = 42;

        $this->assertSame(42, $adapter->lastInsertId());
    }

    public function testQuoteNullIntFloatBool(): void
    {
        $adapter = new FaDbAdapter();

        $this->assertSame('NULL', $adapter->quote(null));
        $this->assertSame('7', $adapter->quote(7));
        $this->assertSame('1.5', $adapter->quote(1.5));
        $this->assertSame('1', $adapter->quote(true));
        $this->assertSame('0', $adapter->quote(false));
    }
}
