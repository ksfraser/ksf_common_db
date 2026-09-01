<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Tests\Unit\Dictionary;

use ksfraser\CommonDb\Dictionary\TableDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ksfraser\CommonDb\Dictionary\TableDefinition
 */
class TableDefinitionTest extends TestCase
{
    public function testCreateSqlRendersSchema(): void
    {
        $def = (new TableDefinition('product_hierarchy', 'id'))
            ->column('id', 'int(11)', 'NOT NULL', true)
            ->column('child_stock_id', 'varchar(32)', 'NOT NULL')
            ->column('parent_stock_id', 'varchar(32)', 'NOT NULL')
            ->column('updated_ts', 'timestamp', 'NOT NULL', false, 'CURRENT_TIMESTAMP')
            ->index('uq_child', 'unique', 'child_stock_id')
            ->index('idx_parent', 'index', 'parent_stock_id');

        $sql = $def->createSql();

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `product_hierarchy`', $sql);
        $this->assertStringContainsString('`id` int(11) NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
        $this->assertStringContainsString('UNIQUE KEY `uq_child` (child_stock_id)', $sql);
        $this->assertStringContainsString('KEY `idx_parent` (parent_stock_id)', $sql);
        $this->assertStringContainsString('DEFAULT CURRENT_TIMESTAMP', $sql);
    }

    public function testWritableColumnsExcludesAutoIncrementId(): void
    {
        $def = (new TableDefinition('t', 'id'))
            ->column('id', 'int(11)', 'NOT NULL', true)
            ->column('name', 'varchar(32)')
            ->column('qty', 'int(11)');

        $this->assertSame(['name', 'qty'], $def->writableColumns());
    }

    public function testInsertSqlUsesNamedPlaceholders(): void
    {
        $def = (new TableDefinition('t', 'id'))
            ->column('id', 'int(11)', 'NOT NULL', true)
            ->column('name', 'varchar(32)');

        $this->assertSame(
            'INSERT INTO `t` (`name`) VALUES (:name)',
            $def->insertSql()
        );
    }

    public function testUpdateSqlKeysByPrimaryKey(): void
    {
        $def = (new TableDefinition('t', 'id'))
            ->column('id', 'int(11)', 'NOT NULL', true)
            ->column('name', 'varchar(32)')
            ->column('qty', 'int(11)');

        $sql = $def->updateSql(['name', 'qty']);

        $this->assertStringContainsString('UPDATE `t` SET', $sql);
        $this->assertStringContainsString('`name` = :name', $sql);
        $this->assertStringContainsString('`qty` = :qty', $sql);
        $this->assertStringContainsString('WHERE `id` = :id', $sql);
        $this->assertStringNotContainsString('`id` = :id` =', $sql);
    }

    public function testDeleteSql(): void
    {
        $def = (new TableDefinition('t', 'id'))
            ->column('id', 'int(11)', 'NOT NULL', true);

        $this->assertSame(
            'DELETE FROM `t` WHERE `id` = :id',
            $def->deleteSql()
        );
    }
}
