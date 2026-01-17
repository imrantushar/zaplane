<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\QueryBuilder;

class TestModel extends Model
{
    protected static string $table = 'test_models';

    protected static array $fillable = [
        'name',
        'email',
        'status',
        'metadata',
    ];

    protected static array $casts = [
        'id' => 'integer',
        'metadata' => 'json',
        'is_active' => 'boolean',
    ];

    protected static array $hidden = [
        'password',
    ];
}

class ModelTest extends TestCase
{
    public function testGetTable(): void
    {
        $table = TestModel::getTable();
        $this->assertStringContainsString('zaplane_test_models', $table);
    }

    public function testQuery(): void
    {
        $query = TestModel::query();
        $this->assertInstanceOf(QueryBuilder::class, $query);
    }

    public function testFill(): void
    {
        $model = new TestModel();
        $model->fill([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertEquals('John', $model->name);
        $this->assertEquals('john@example.com', $model->email);
    }

    public function testFillableGuard(): void
    {
        $model = new TestModel();
        $model->fill([
            'id' => 999,
            'name' => 'John',
        ]);

        $this->assertNull($model->id);
        $this->assertEquals('John', $model->name);
    }

    public function testForceFill(): void
    {
        $model = new TestModel();
        $model->forceFill([
            'id' => 999,
            'name' => 'John',
        ]);

        $this->assertEquals(999, $model->id);
        $this->assertEquals('John', $model->name);
    }

    public function testAttributeAccess(): void
    {
        $model = new TestModel(['name' => 'John']);

        $this->assertEquals('John', $model->name);
        $this->assertEquals('John', $model->getAttribute('name'));

        $model->email = 'john@example.com';
        $this->assertEquals('john@example.com', $model->email);
    }

    public function testIsset(): void
    {
        $model = new TestModel(['name' => 'John']);

        $this->assertTrue(isset($model->name));
        $this->assertFalse(isset($model->email));
    }

    public function testUnset(): void
    {
        $model = new TestModel(['name' => 'John']);

        unset($model->name);

        $this->assertFalse(isset($model->name));
    }

    public function testCastInteger(): void
    {
        $model = TestModel::hydrate(['id' => '123', 'name' => 'Test']);

        $this->assertIsInt($model->id);
        $this->assertEquals(123, $model->id);
    }

    public function testCastBoolean(): void
    {
        $model = TestModel::hydrate(['is_active' => 1]);

        $this->assertIsBool($model->is_active);
        $this->assertTrue($model->is_active);
    }

    public function testCastJson(): void
    {
        $model = TestModel::hydrate(['metadata' => '{"key":"value"}']);

        $this->assertIsArray($model->metadata);
        $this->assertEquals(['key' => 'value'], $model->metadata);
    }

    public function testCastNullValue(): void
    {
        $model = TestModel::hydrate(['metadata' => null]);

        $this->assertNull($model->metadata);
    }

    public function testToArray(): void
    {
        $model = new TestModel([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);
        $model->forceFill(['password' => 'secret']);

        $array = $model->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayNotHasKey('password', $array);
    }

    public function testToJson(): void
    {
        $model = new TestModel([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $json = $model->toJson();

        $this->assertJson($json);
        $this->assertStringContainsString('John', $json);
    }

    public function testJsonSerialize(): void
    {
        $model = new TestModel(['name' => 'John']);

        $serialized = json_encode($model);

        $this->assertJson($serialized);
        $this->assertStringContainsString('John', $serialized);
    }

    public function testHydrate(): void
    {
        $model = TestModel::hydrate([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertTrue($model->exists());
        $this->assertEquals(1, $model->id);
        $this->assertEquals('John', $model->name);
    }

    public function testGetKey(): void
    {
        $model = TestModel::hydrate(['id' => 42, 'name' => 'Test']);

        $this->assertEquals(42, $model->getKey());
    }

    public function testGetDirty(): void
    {
        $model = TestModel::hydrate([
            'id' => 1,
            'name' => 'John',
        ]);

        $model->name = 'Jane';
        $model->email = 'jane@example.com';

        $dirty = $model->getDirty();

        $this->assertArrayHasKey('name', $dirty);
        $this->assertArrayHasKey('email', $dirty);
        $this->assertEquals('Jane', $dirty['name']);
    }

    public function testIsDirty(): void
    {
        $model = TestModel::hydrate(['id' => 1, 'name' => 'John']);

        $this->assertFalse($model->isDirty());

        $model->name = 'Jane';

        $this->assertTrue($model->isDirty());
        $this->assertTrue($model->isDirty('name'));
        $this->assertFalse($model->isDirty('email'));
    }

    public function testIsClean(): void
    {
        $model = TestModel::hydrate(['id' => 1, 'name' => 'John']);

        $this->assertTrue($model->isClean());
        $this->assertTrue($model->isClean('name'));

        $model->name = 'Jane';

        $this->assertFalse($model->isClean());
        $this->assertFalse($model->isClean('name'));
    }

    public function testGetOriginal(): void
    {
        $model = TestModel::hydrate(['id' => 1, 'name' => 'John']);

        $model->name = 'Jane';

        $this->assertEquals('John', $model->getOriginal('name'));
        $this->assertEquals('Jane', $model->name);
    }

    public function testExists(): void
    {
        $newModel = new TestModel(['name' => 'John']);
        $existingModel = TestModel::hydrate(['id' => 1, 'name' => 'John']);

        $this->assertFalse($newModel->exists());
        $this->assertTrue($existingModel->exists());
    }

    public function testReplicate(): void
    {
        $model = TestModel::hydrate([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $replica = $model->replicate();

        $this->assertNull($replica->id);
        $this->assertEquals('John', $replica->name);
        $this->assertFalse($replica->exists());
    }

    public function testReplicateExcept(): void
    {
        $model = TestModel::hydrate([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $replica = $model->replicate(['email']);

        $this->assertNull($replica->id);
        $this->assertEquals('John', $replica->name);
        $this->assertNull($replica->email);
    }

    public function testWhereProxy(): void
    {
        $query = TestModel::where('status', 'active');

        $this->assertInstanceOf(QueryBuilder::class, $query);
    }

    public function testWhereInProxy(): void
    {
        $query = TestModel::whereIn('id', [1, 2, 3]);

        $this->assertInstanceOf(QueryBuilder::class, $query);
    }

    public function testOrderByProxy(): void
    {
        $query = TestModel::orderBy('name', 'asc');

        $this->assertInstanceOf(QueryBuilder::class, $query);
    }

    public function testLatestProxy(): void
    {
        $query = TestModel::latest();

        $this->assertInstanceOf(QueryBuilder::class, $query);
    }
}
