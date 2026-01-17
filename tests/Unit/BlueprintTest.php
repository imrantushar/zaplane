<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Database\ORM\Blueprint;
use Zaplane\Database\ORM\ColumnDefinition;

class BlueprintTest extends TestCase
{
    protected function getBlueprint(string $table = 'wp_zaplane_test'): Blueprint
    {
        return new Blueprint($table);
    }

    public function testId(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->id();

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('id bigint UNSIGNED AUTO_INCREMENT NOT NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY (id)', $sql);
    }

    public function testBigInteger(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->bigInteger('user_id');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('user_id bigint NOT NULL', $sql);
    }

    public function testUnsignedBigInteger(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->unsignedBigInteger('user_id');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('user_id bigint UNSIGNED NOT NULL', $sql);
    }

    public function testString(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->string('name');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('name varchar(255) NOT NULL', $sql);
    }

    public function testStringWithLength(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->string('title', 100);

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('title varchar(100) NOT NULL', $sql);
    }

    public function testChar(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->char('hash', 64);

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('hash char(64) NOT NULL', $sql);
    }

    public function testText(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->text('description');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('description text NOT NULL', $sql);
    }

    public function testLongText(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->longText('content');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('content longtext NOT NULL', $sql);
    }

    public function testInteger(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->integer('count');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('count int NOT NULL', $sql);
    }

    public function testTinyInteger(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->tinyInteger('priority');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('priority tinyint NOT NULL', $sql);
    }

    public function testBoolean(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->boolean('is_active');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('is_active tinyint(1) NOT NULL', $sql);
    }

    public function testDatetime(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->datetime('published_at');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('published_at datetime NOT NULL', $sql);
    }

    public function testEnum(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->enum('status', ['draft', 'active', 'archived']);

        $sql = $blueprint->toSql();

        $this->assertStringContainsString("status enum('draft','active','archived') NOT NULL", $sql);
    }

    public function testDecimal(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->decimal('price', 10, 2);

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('price decimal(10,2) NOT NULL', $sql);
    }

    public function testNullable(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->string('nickname')->nullable();

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('nickname varchar(255) NULL DEFAULT NULL', $sql);
    }

    public function testDefault(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->string('status')->default('pending');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString("status varchar(255) NOT NULL DEFAULT 'pending'", $sql);
    }

    public function testDefaultBoolean(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->boolean('is_active')->default(true);

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('is_active tinyint(1) NOT NULL DEFAULT 1', $sql);
    }

    public function testTimestamps(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->timestamps();

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('created_at datetime NULL DEFAULT CURRENT_TIMESTAMP', $sql);
        $this->assertStringContainsString('updated_at datetime NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $sql);
    }

    public function testSoftDeletes(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->softDeletes();

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('deleted_at datetime NULL DEFAULT NULL', $sql);
    }

    public function testIndex(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->string('email');
        $blueprint->index('email');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('KEY wp_zaplane_test_email_index (email)', $sql);
    }

    public function testUniqueIndex(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->string('email');
        $blueprint->unique('email');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('UNIQUE KEY wp_zaplane_test_email_unique (email)', $sql);
    }

    public function testCompositeIndex(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->unsignedBigInteger('user_id');
        $blueprint->string('status');
        $blueprint->index(['user_id', 'status']);

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('KEY wp_zaplane_test_user_id_status_index (user_id, status)', $sql);
    }

    public function testForeignId(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->foreignId('user_id');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('user_id bigint UNSIGNED NOT NULL', $sql);
    }

    public function testFullTableCreation(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->id();
        $blueprint->unsignedBigInteger('user_id');
        $blueprint->string('title');
        $blueprint->text('content')->nullable();
        $blueprint->enum('status', ['draft', 'published'])->default('draft');
        $blueprint->timestamps();
        $blueprint->index('user_id');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS wp_zaplane_test', $sql);
        $this->assertStringContainsString('id bigint UNSIGNED AUTO_INCREMENT NOT NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY (id)', $sql);
        $this->assertStringContainsString('KEY wp_zaplane_test_user_id_index (user_id)', $sql);
    }

    public function testGetTable(): void
    {
        $blueprint = $this->getBlueprint('wp_zaplane_posts');
        $this->assertEquals('wp_zaplane_posts', $blueprint->getTable());
    }

    public function testGetColumns(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->id();
        $blueprint->string('name');

        $columns = $blueprint->getColumns();

        $this->assertCount(2, $columns);
        $this->assertInstanceOf(ColumnDefinition::class, $columns[0]);
    }

    public function testGetIndexes(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->string('email');
        $blueprint->index('email');
        $blueprint->unique('email', 'email_unique');

        $indexes = $blueprint->getIndexes();

        $this->assertCount(2, $indexes);
    }

    public function testColumnUnique(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->string('email')->unique();

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('email varchar(255) NOT NULL UNIQUE', $sql);
    }

    public function testJson(): void
    {
        $blueprint = $this->getBlueprint();
        $blueprint->json('metadata');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('metadata longtext NOT NULL', $sql);
    }
}
