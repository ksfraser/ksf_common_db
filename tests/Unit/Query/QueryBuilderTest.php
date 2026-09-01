<?php

declare(strict_types=1);

namespace ksfraser\CommonDb\Tests\Unit\Query;

use ksfraser\CommonDb\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ksfraser\CommonDb\Query\QueryBuilder
 */
class QueryBuilderTest extends TestCase
{
    public function testBuildsSimpleSelect(): void
    {
        $qb = (new QueryBuilder())
            ->select(['stock_id', 'description'])
            ->from('stock_master');

        $this->assertSame(
            'SELECT stock_id, description FROM stock_master',
            $qb->toSql()
        );
        $this->assertSame([], $qb->getParams());
    }

    public function testWhereWithNamedParams(): void
    {
        $qb = (new QueryBuilder())
            ->select('*')
            ->from('stock_master')
            ->where('stock_id = :id', ['id' => 'P-001'])
            ->where('qty >= :min', ['min' => 5])
            ->orderBy('stock_id DESC')
            ->limit(10, 2);

        $this->assertSame(
            'SELECT * FROM stock_master WHERE stock_id = :id AND qty >= :min ORDER BY stock_id DESC LIMIT 10 OFFSET 2',
            $qb->toSql()
        );
        $this->assertSame(['id' => 'P-001', 'min' => 5], $qb->getParams());
    }

    public function testJoinGroupByHaving(): void
    {
        $qb = (new QueryBuilder())
            ->select('p.stock_id, COUNT(*) AS cnt')
            ->from('stock_master p')
            ->join('INNER JOIN product_attribute_assignments a ON a.stock_id = p.stock_id')
            ->where('p.inactive = :inactive', ['inactive' => 0])
            ->groupBy('p.stock_id')
            ->having('COUNT(*) >= :min', ['min' => 2]);

        $this->assertSame(
            'SELECT p.stock_id, COUNT(*) AS cnt FROM stock_master p'
            . ' INNER JOIN product_attribute_assignments a ON a.stock_id = p.stock_id'
            . ' WHERE p.inactive = :inactive'
            . ' GROUP BY p.stock_id'
            . ' HAVING COUNT(*) >= :min',
            $qb->toSql()
        );
        $this->assertSame(['inactive' => 0, 'min' => 2], $qb->getParams());
    }
}
