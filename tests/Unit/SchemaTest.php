<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Database\ORM\Schema;

class SchemaTest extends TestCase
{
    public function testGetPrefix(): void
    {
        $prefix = Schema::getPrefix();
        $this->assertStringContainsString('zaplane_', $prefix);
    }

    public function testGetTable(): void
    {
        $table = Schema::getTable('workflows');
        $this->assertStringEndsWith('zaplane_workflows', $table);
    }

    public function testGetTablePreservesPrefix(): void
    {
        $table1 = Schema::getTable('test');
        $table2 = Schema::getTable('another');

        $this->assertNotEquals($table1, $table2);
        $this->assertStringContainsString('zaplane_test', $table1);
        $this->assertStringContainsString('zaplane_another', $table2);
    }
}
