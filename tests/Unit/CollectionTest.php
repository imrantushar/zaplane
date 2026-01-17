<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Database\ORM\Collection;

class CollectionTest extends TestCase
{
    public function testMake(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $this->assertCount(3, $collection);
    }

    public function testAll(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testToArray(): void
    {
        $collection = Collection::make(['a' => 1, 'b' => 2]);
        $this->assertEquals(['a' => 1, 'b' => 2], $collection->toArray());
    }

    public function testToJson(): void
    {
        $collection = Collection::make(['name' => 'test']);
        $this->assertEquals('{"name":"test"}', $collection->toJson());
    }

    public function testCount(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $this->assertEquals(5, $collection->count());
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue(Collection::make([])->isEmpty());
        $this->assertFalse(Collection::make([1])->isEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $this->assertTrue(Collection::make([1])->isNotEmpty());
        $this->assertFalse(Collection::make([])->isNotEmpty());
    }

    public function testFirst(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $this->assertEquals(1, $collection->first());
    }

    public function testFirstWithCallback(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $result = $collection->first(fn($item) => $item > 3);
        $this->assertEquals(4, $result);
    }

    public function testLast(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $this->assertEquals(3, $collection->last());
    }

    public function testMap(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $mapped = $collection->map(fn($item) => $item * 2);
        $this->assertEquals([2, 4, 6], $mapped->all());
    }

    public function testFilter(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $filtered = $collection->filter(fn($item) => $item > 2);
        $this->assertEquals([3, 4, 5], array_values($filtered->all()));
    }

    public function testReject(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $rejected = $collection->reject(fn($item) => $item > 3);
        $this->assertEquals([1, 2, 3], array_values($rejected->all()));
    }

    public function testWhere(): void
    {
        $collection = Collection::make([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 30],
        ]);

        $filtered = $collection->where('age', 30);
        $this->assertCount(2, $filtered);
    }

    public function testWhereWithOperator(): void
    {
        $collection = Collection::make([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ]);

        $filtered = $collection->where('age', '>', 27);
        $this->assertCount(2, $filtered);
    }

    public function testWhereIn(): void
    {
        $collection = Collection::make([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
            ['id' => 3, 'name' => 'Bob'],
        ]);

        $filtered = $collection->whereIn('id', [1, 3]);
        $this->assertCount(2, $filtered);
    }

    public function testPluck(): void
    {
        $collection = Collection::make([
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        $names = $collection->pluck('name');
        $this->assertEquals(['John', 'Jane'], $names->all());
    }

    public function testPluckWithKey(): void
    {
        $collection = Collection::make([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ]);

        $names = $collection->pluck('name', 'id');
        $this->assertEquals([1 => 'John', 2 => 'Jane'], $names->all());
    }

    public function testSortBy(): void
    {
        $collection = Collection::make([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ]);

        $sorted = $collection->sortBy('age');
        $ages = $sorted->pluck('age')->all();
        $this->assertEquals([25, 30, 35], $ages);
    }

    public function testSortByDesc(): void
    {
        $collection = Collection::make([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ]);

        $sorted = $collection->sortByDesc('age');
        $ages = $sorted->pluck('age')->all();
        $this->assertEquals([35, 30, 25], $ages);
    }

    public function testGroupBy(): void
    {
        $collection = Collection::make([
            ['name' => 'John', 'department' => 'Sales'],
            ['name' => 'Jane', 'department' => 'Marketing'],
            ['name' => 'Bob', 'department' => 'Sales'],
        ]);

        $grouped = $collection->groupBy('department');
        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped->get('Sales'));
        $this->assertCount(1, $grouped->get('Marketing'));
    }

    public function testKeyBy(): void
    {
        $collection = Collection::make([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ]);

        $keyed = $collection->keyBy('id');
        $this->assertEquals('John', $keyed->get(1)['name']);
        $this->assertEquals('Jane', $keyed->get(2)['name']);
    }

    public function testUnique(): void
    {
        $collection = Collection::make([1, 1, 2, 2, 3, 3]);
        $unique = $collection->unique();
        $this->assertEquals([1, 2, 3], array_values($unique->all()));
    }

    public function testUniqueByKey(): void
    {
        $collection = Collection::make([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
            ['id' => 1, 'name' => 'John Doe'],
        ]);

        $unique = $collection->unique('id');
        $this->assertCount(2, $unique);
    }

    public function testTake(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $this->assertEquals([1, 2, 3], $collection->take(3)->all());
    }

    public function testSkip(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $this->assertEquals([3, 4, 5], $collection->skip(2)->all());
    }

    public function testChunk(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $chunks = $collection->chunk(2);
        $this->assertCount(3, $chunks);
    }

    public function testMerge(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $merged = $collection->merge([4, 5, 6]);
        $this->assertEquals([1, 2, 3, 4, 5, 6], $merged->all());
    }

    public function testSum(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $this->assertEquals(15, $collection->sum());
    }

    public function testSumWithKey(): void
    {
        $collection = Collection::make([
            ['price' => 10],
            ['price' => 20],
            ['price' => 30],
        ]);
        $this->assertEquals(60, $collection->sum('price'));
    }

    public function testAvg(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $this->assertEquals(3, $collection->avg());
    }

    public function testMin(): void
    {
        $collection = Collection::make([3, 1, 5, 2, 4]);
        $this->assertEquals(1, $collection->min());
    }

    public function testMax(): void
    {
        $collection = Collection::make([3, 1, 5, 2, 4]);
        $this->assertEquals(5, $collection->max());
    }

    public function testContains(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $this->assertTrue($collection->contains(3));
        $this->assertFalse($collection->contains(10));
    }

    public function testContainsWithCallback(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $this->assertTrue($collection->contains(fn($item) => $item > 4));
        $this->assertFalse($collection->contains(fn($item) => $item > 10));
    }

    public function testEach(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $result = [];
        $collection->each(function ($item) use (&$result) {
            $result[] = $item * 2;
        });
        $this->assertEquals([2, 4, 6], $result);
    }

    public function testReduce(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $result = $collection->reduce(fn($carry, $item) => $carry + $item, 0);
        $this->assertEquals(15, $result);
    }

    public function testImplode(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $this->assertEquals('1, 2, 3', $collection->implode(', '));
    }

    public function testReverse(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $this->assertEquals([3, 2, 1], array_values($collection->reverse()->all()));
    }

    public function testArrayAccess(): void
    {
        $collection = Collection::make(['a' => 1, 'b' => 2]);
        $this->assertEquals(1, $collection['a']);
        $this->assertTrue(isset($collection['b']));
        $this->assertFalse(isset($collection['c']));
    }

    public function testIterable(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $result = [];
        foreach ($collection as $item) {
            $result[] = $item;
        }
        $this->assertEquals([1, 2, 3], $result);
    }

    public function testPush(): void
    {
        $collection = Collection::make([1, 2]);
        $collection->push(3, 4);
        $this->assertEquals([1, 2, 3, 4], $collection->all());
    }

    public function testPop(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $this->assertEquals(3, $collection->pop());
        $this->assertEquals([1, 2], $collection->all());
    }

    public function testWhen(): void
    {
        $collection = Collection::make([1, 2, 3]);

        $result = $collection->when(true, fn($c) => $c->push(4));
        $this->assertCount(4, $result);

        $collection2 = Collection::make([1, 2, 3]);
        $result2 = $collection2->when(false, fn($c) => $c->push(4));
        $this->assertCount(3, $result2);
    }
}
