<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Tests\Unit\Adapter;

use ksfraser\CommonDb\Adapter\PdoDbAdapter;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ksfraser\CommonDb\Adapter\PdoDbAdapter
 */
class PdoDbAdapterTest extends TestCase
{
    /** @var PDO */
    private $pdo;

    /** @var PdoDbAdapter */
    private $adapter;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension not available');
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE stock_master (stock_id TEXT PRIMARY KEY, description TEXT, qty INTEGER)');

        $this->adapter = new PdoDbAdapter($this->pdo);
    }

    public function testExecuteUpdateAndFetchAllWithPreparedStatement(): void
    {
        $this->adapter->executeUpdate(
            'INSERT INTO stock_master (stock_id, description, qty) VALUES (?, ?, ?)',
            ['P-001', "O'Brien Widget", 10]
        );
        $this->adapter->executeUpdate(
            'INSERT INTO stock_master (stock_id, description, qty) VALUES (?, ?, ?)',
            ['P-002', 'Gadget', 3]
        );

        $rows = $this->adapter->fetchAll(
            'SELECT stock_id, description FROM stock_master WHERE qty > :min ORDER BY stock_id',
            ['min' => 2]
        );

        $this->assertCount(2, $rows);
        $this->assertSame("O'Brien Widget", $rows[0]['description']);
    }

    public function testFetchAssocReturnsNullWhenEmpty(): void
    {
        $this->assertNull(
            $this->adapter->fetchAssoc('SELECT * FROM stock_master WHERE stock_id = ?', ['NOPE'])
        );
    }

    public function testFetchScalar(): void
    {
        $this->adapter->executeUpdate(
            'INSERT INTO stock_master (stock_id, description, qty) VALUES (?, ?, ?)',
            ['A', 'Alpha', 5]
        );

        $this->assertSame(5, (int) $this->adapter->fetchScalar('SELECT MAX(qty) FROM stock_master'));
        $this->assertSame(0, $this->adapter->fetchScalar('SELECT MAX(qty) FROM stock_master WHERE qty > ?', [99], 0));
    }

    public function testLastInsertId(): void
    {
        $this->adapter->executeUpdate(
            'INSERT INTO stock_master (stock_id, description, qty) VALUES (?, ?, ?)',
            ['Z', 'Zulu', 1]
        );

        // Non-integer PK in SQLite; verify it returns without throwing.
        $this->assertGreaterThanOrEqual(0, $this->adapter->lastInsertId());
    }

    public function testTransactionsCommit(): void
    {
        $this->adapter->beginTransaction();
        $this->adapter->executeUpdate(
            'INSERT INTO stock_master (stock_id, description, qty) VALUES (?, ?, ?)',
            ['T1', 'Tx', 1]
        );
        $this->adapter->commit();

        $this->assertCount(1, $this->adapter->fetchAll('SELECT * FROM stock_master'));
    }

    public function testTransactionsRollback(): void
    {
        $this->adapter->beginTransaction();
        $this->adapter->executeUpdate(
            'INSERT INTO stock_master (stock_id, description, qty) VALUES (?, ?, ?)',
            ['T1', 'Tx', 1]
        );
        $this->adapter->rollBack();

        $this->assertCount(0, $this->adapter->fetchAll('SELECT * FROM stock_master'));
    }
}
