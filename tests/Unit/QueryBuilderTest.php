<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Database\ORM\QueryBuilder;
use Zaplane\Framework\Database\ORM\RawExpression;

class QueryBuilderTest extends TestCase
{
    protected function getBuilder(string $table = 'wp_zaplane_test'): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    public function testBasicSelect(): void
    {
        $builder = $this->getBuilder();
        $sql = $builder->toSql();

        $this->assertStringContainsString('SELECT *', $sql);
        $this->assertStringContainsString('FROM wp_zaplane_test', $sql);
    }

    public function testSelectColumns(): void
    {
        $builder = $this->getBuilder()->select('id', 'name', 'email');
        $sql = $builder->toSql();

        $this->assertStringContainsString('SELECT id, name, email', $sql);
    }

    public function testSelectRaw(): void
    {
        $builder = $this->getBuilder()->selectRaw('COUNT(*) as total');
        $sql = $builder->toSql();

        $this->assertStringContainsString('SELECT COUNT(*) as total', $sql);
    }

    public function testWhereClause(): void
    {
        $builder = $this->getBuilder()->where('status', 'active');
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE status = %s', $sql);
        $this->assertEquals(['active'], $builder->getBindings());
    }

    public function testWhereWithOperator(): void
    {
        $builder = $this->getBuilder()->where('age', '>=', 18);
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE age >= %s', $sql);
        $this->assertEquals([18], $builder->getBindings());
    }

    public function testMultipleWhereClauses(): void
    {
        $builder = $this->getBuilder()
            ->where('status', 'active')
            ->where('role', 'admin');
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE status = %s AND role = %s', $sql);
        $this->assertEquals(['active', 'admin'], $builder->getBindings());
    }

    public function testOrWhere(): void
    {
        $builder = $this->getBuilder()
            ->where('status', 'active')
            ->orWhere('status', 'pending');
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE status = %s OR status = %s', $sql);
    }

    public function testWhereIn(): void
    {
        $builder = $this->getBuilder()->whereIn('id', [1, 2, 3]);
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE id IN (%s, %s, %s)', $sql);
        $this->assertEquals([1, 2, 3], $builder->getBindings());
    }

    public function testWhereNotIn(): void
    {
        $builder = $this->getBuilder()->whereNotIn('id', [1, 2]);
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE id NOT IN (%s, %s)', $sql);
    }

    public function testWhereNull(): void
    {
        $builder = $this->getBuilder()->whereNull('deleted_at');
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE deleted_at IS NULL', $sql);
    }

    public function testWhereNotNull(): void
    {
        $builder = $this->getBuilder()->whereNotNull('email');
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE email IS NOT NULL', $sql);
    }

    public function testWhereBetween(): void
    {
        $builder = $this->getBuilder()->whereBetween('age', [18, 65]);
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE age BETWEEN %s AND %s', $sql);
        $this->assertEquals([18, 65], $builder->getBindings());
    }

    public function testWhereRaw(): void
    {
        $builder = $this->getBuilder()->whereRaw('status = "active" OR role = "admin"');
        $sql = $builder->toSql();

        $this->assertStringContainsString('WHERE status = "active" OR role = "admin"', $sql);
    }

    public function testOrderBy(): void
    {
        $builder = $this->getBuilder()->orderBy('created_at', 'desc');
        $sql = $builder->toSql();

        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function testMultipleOrderBy(): void
    {
        $builder = $this->getBuilder()
            ->orderBy('status', 'asc')
            ->orderByDesc('created_at');
        $sql = $builder->toSql();

        $this->assertStringContainsString('ORDER BY status ASC, created_at DESC', $sql);
    }

    public function testLatest(): void
    {
        $builder = $this->getBuilder()->latest();
        $sql = $builder->toSql();

        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function testOldest(): void
    {
        $builder = $this->getBuilder()->oldest('updated_at');
        $sql = $builder->toSql();

        $this->assertStringContainsString('ORDER BY updated_at ASC', $sql);
    }

    public function testLimit(): void
    {
        $builder = $this->getBuilder()->limit(10);
        $sql = $builder->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testOffset(): void
    {
        $builder = $this->getBuilder()->limit(10)->offset(20);
        $sql = $builder->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    public function testForPage(): void
    {
        $builder = $this->getBuilder()->forPage(3, 15);
        $sql = $builder->toSql();

        $this->assertStringContainsString('LIMIT 15', $sql);
        $this->assertStringContainsString('OFFSET 30', $sql);
    }

    public function testJoin(): void
    {
        $builder = $this->getBuilder()
            ->join('users', 'posts.user_id', '=', 'users.id');
        $sql = $builder->toSql();

        $this->assertStringContainsString('INNER JOIN users ON posts.user_id = users.id', $sql);
    }

    public function testLeftJoin(): void
    {
        $builder = $this->getBuilder()
            ->leftJoin('comments', 'posts.id', '=', 'comments.post_id');
        $sql = $builder->toSql();

        $this->assertStringContainsString('LEFT JOIN comments ON posts.id = comments.post_id', $sql);
    }

    public function testGroupBy(): void
    {
        $builder = $this->getBuilder()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status');
        $sql = $builder->toSql();

        $this->assertStringContainsString('GROUP BY status', $sql);
    }

    public function testHaving(): void
    {
        $builder = $this->getBuilder()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->having('total', '>', 5);
        $sql = $builder->toSql();

        $this->assertStringContainsString('HAVING total > %s', $sql);
    }

    public function testComplexQuery(): void
    {
        $builder = $this->getBuilder()
            ->select('id', 'title', 'status')
            ->where('status', 'active')
            ->where('user_id', 1)
            ->orderByDesc('created_at')
            ->limit(10)
            ->offset(0);

        $sql = $builder->toSql();

        $this->assertStringContainsString('SELECT id, title, status', $sql);
        $this->assertStringContainsString('WHERE status = %s AND user_id = %s', $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testRawExpression(): void
    {
        $raw = new RawExpression('NOW()');
        $this->assertEquals('NOW()', $raw->getValue());
        $this->assertEquals('NOW()', (string) $raw);
    }

    public function testClone(): void
    {
        $builder = $this->getBuilder()->where('status', 'active');
        $clone = $builder->clone();

        $clone->where('role', 'admin');

        $this->assertCount(1, $builder->getBindings());
        $this->assertCount(2, $clone->getBindings());
    }

    public function testTake(): void
    {
        $builder = $this->getBuilder()->take(5);
        $sql = $builder->toSql();

        $this->assertStringContainsString('LIMIT 5', $sql);
    }

    public function testSkip(): void
    {
        $builder = $this->getBuilder()->skip(10)->take(5);
        $sql = $builder->toSql();

        $this->assertStringContainsString('OFFSET 10', $sql);
    }
}
