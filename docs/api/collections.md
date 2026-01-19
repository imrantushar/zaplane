# Collections API Reference

Complete reference for the Zaplane Collection class.

---

## Overview

Collections provide a fluent, convenient wrapper for working with arrays of data. They are returned by all ORM queries and offer powerful methods for filtering, mapping, and transforming data.

**Namespace:** `Zaplane\Framework\Database\ORM\Collection`

---

## Creating Collections

### From Query Results

```php
use Zaplane\Models\Workflow;

// Queries return collections
$workflows = Workflow::all();
$active = Workflow::where('status', 'active')->get();
```

### Manual Creation

```php
use Zaplane\Framework\Database\ORM\Collection;

// From array
$collection = new Collection([1, 2, 3, 4, 5]);

// From models
$collection = new Collection([
    $workflow1,
    $workflow2,
    $workflow3
]);
```

---

## Basic Methods

### `all(): array`

Get all items as array.

```php
$workflows = Workflow::all();
$array = $workflows->all();
```

### `count(): int`

Get number of items.

```php
$workflows = Workflow::all();
echo "Total: " . $workflows->count();
```

### `isEmpty(): bool`

Check if collection is empty.

```php
$workflows = Workflow::where('status', 'archived')->get();

if ($workflows->isEmpty()) {
    echo "No archived workflows";
}
```

### `isNotEmpty(): bool`

Check if collection is not empty.

```php
if ($workflows->isNotEmpty()) {
    echo "Has workflows";
}
```

### `first(): mixed`

Get first item.

```php
$workflows = Workflow::all();
$first = $workflows->first();

if ($first) {
    echo $first->title;
}
```

### `last(): mixed`

Get last item.

```php
$workflows = Workflow::all();
$last = $workflows->last();
```

---

## Filtering

### `filter(callable $callback): Collection`

Filter items using callback.

```php
$workflows = Workflow::all();

// Get only active workflows
$active = $workflows->filter(function($workflow) {
    return $workflow->status === 'active';
});

// Filter by multiple conditions
$recent = $workflows->filter(function($workflow) {
    return $workflow->status === 'active'
        && $workflow->created_at > date('Y-m-d', strtotime('-7 days'));
});
```

### `where(string $key, $operator, $value = null): Collection`

Filter by key-value.

```php
$workflows = Workflow::all();

// Two parameter form (assumes '=' operator)
$active = $workflows->where('status', 'active');

// Three parameter form
$recent = $workflows->where('created_at', '>', '2024-01-01');
$popular = $workflows->where('runs_count', '>=', 10);
```

### `whereIn(string $key, array $values): Collection`

Filter where key in array.

```php
$workflows = Workflow::all();

$filtered = $workflows->whereIn('status', ['active', 'paused']);
```

### `whereNotIn(string $key, array $values): Collection`

Filter where key not in array.

```php
$workflows = Workflow::all();

$filtered = $workflows->whereNotIn('status', ['deleted', 'archived']);
```

### `reject(callable $callback): Collection`

Reject items that match callback (opposite of filter).

```php
$workflows = Workflow::all();

// Get all except drafts
$notDrafts = $workflows->reject(function($workflow) {
    return $workflow->status === 'draft';
});
```

---

## Mapping & Transformation

### `map(callable $callback): Collection`

Transform each item.

```php
$workflows = Workflow::all();

// Get array of titles
$titles = $workflows->map(function($workflow) {
    return $workflow->title;
});

// Transform to arrays
$arrays = $workflows->map(function($workflow) {
    return $workflow->toArray();
});

// Add computed properties
$enhanced = $workflows->map(function($workflow) {
    $workflow->is_recent = $workflow->created_at > date('Y-m-d', strtotime('-7 days'));
    return $workflow;
});
```

### `pluck(string $key, ?string $keyBy = null): Collection`

Extract values for a given key.

```php
$workflows = Workflow::all();

// Get array of titles
$titles = $workflows->pluck('title');
// ['Workflow 1', 'Workflow 2', 'Workflow 3']

// Get titles keyed by ID
$titlesByID = $workflows->pluck('title', 'id');
// [1 => 'Workflow 1', 2 => 'Workflow 2', 3 => 'Workflow 3']

// Get nested values
$statuses = $workflows->pluck('status');
```

### `values(): Collection`

Reset keys to sequential integers.

```php
$collection = new Collection([
    10 => 'a',
    20 => 'b',
    30 => 'c'
]);

$values = $collection->values();
// [0 => 'a', 1 => 'b', 2 => 'c']
```

### `keys(): Collection`

Get collection keys.

```php
$collection = new Collection([
    'name' => 'John',
    'email' => 'john@example.com',
    'age' => 30
]);

$keys = $collection->keys();
// ['name', 'email', 'age']
```

---

## Sorting

### `sort(callable $callback = null): Collection`

Sort collection.

```php
$workflows = Workflow::all();

// Sort by title
$sorted = $workflows->sort(function($a, $b) {
    return strcmp($a->title, $b->title);
});

// Sort by created_at descending
$sorted = $workflows->sort(function($a, $b) {
    return $b->created_at <=> $a->created_at;
});
```

### `sortBy(string $key, int $options = SORT_REGULAR, bool $descending = false): Collection`

Sort by key.

```php
$workflows = Workflow::all();

// Sort by title ascending
$sorted = $workflows->sortBy('title');

// Sort by created_at descending
$sorted = $workflows->sortBy('created_at', SORT_REGULAR, true);
```

### `sortByDesc(string $key, int $options = SORT_REGULAR): Collection`

Sort by key descending.

```php
$workflows = Workflow::all();

$sorted = $workflows->sortByDesc('created_at');
```

### `reverse(): Collection`

Reverse collection order.

```php
$workflows = Workflow::all();

$reversed = $workflows->reverse();
```

---

## Grouping

### `groupBy(string|callable $groupBy): Collection`

Group items by key or callback.

```php
$workflows = Workflow::all();

// Group by status
$byStatus = $workflows->groupBy('status');
// [
//   'active' => Collection[...],
//   'paused' => Collection[...],
//   'draft' => Collection[...]
// ]

// Group by custom logic
$byMonth = $workflows->groupBy(function($workflow) {
    return date('Y-m', strtotime($workflow->created_at));
});
// [
//   '2024-01' => Collection[...],
//   '2024-02' => Collection[...],
// ]
```

### `countBy(string|callable $countBy): Collection`

Count items by key or callback.

```php
$workflows = Workflow::all();

// Count by status
$counts = $workflows->countBy('status');
// ['active' => 10, 'paused' => 5, 'draft' => 3]

// Count by custom logic
$counts = $workflows->countBy(function($workflow) {
    return $workflow->user_id;
});
// [1 => 5, 2 => 8, 3 => 2]
```

---

## Aggregates

### `sum(string|callable $callback = null): float`

Sum values.

```php
$runs = Run::all();

// Sum attempts
$total = $runs->sum('attempts');

// Sum with callback
$total = $runs->sum(function($run) {
    return $run->attempts * 2;
});
```

### `avg(string|callable $callback = null): float`

Average values.

```php
$runs = Run::all();

$avgAttempts = $runs->avg('attempts');
```

### `max(string|callable $callback = null): mixed`

Maximum value.

```php
$runs = Run::all();

$maxAttempts = $runs->max('attempts');
```

### `min(string|callable $callback = null): mixed`

Minimum value.

```php
$runs = Run::all();

$minAttempts = $runs->min('attempts');
```

---

## Chunking & Pagination

### `chunk(int $size): Collection`

Break into chunks.

```php
$workflows = Workflow::all();

$chunks = $workflows->chunk(10);

foreach ($chunks as $chunk) {
    // Process chunk of 10 workflows
    processChunk($chunk);
}
```

### `forPage(int $page, int $perPage): Collection`

Get items for a page.

```php
$workflows = Workflow::all();

// Get page 2, 20 items per page
$page2 = $workflows->forPage(2, 20);
```

### `take(int $limit): Collection`

Take first N items.

```php
$workflows = Workflow::all();

$first10 = $workflows->take(10);
```

### `skip(int $count): Collection`

Skip first N items.

```php
$workflows = Workflow::all();

$afterFirst10 = $workflows->skip(10);
```

### `slice(int $offset, ?int $length = null): Collection`

Get slice of collection.

```php
$workflows = Workflow::all();

// Get items 10-20
$slice = $workflows->slice(10, 10);

// Get all items after 10
$slice = $workflows->slice(10);
```

---

## Combining Collections

### `merge(array|Collection $items): Collection`

Merge with another collection/array.

```php
$active = Workflow::where('status', 'active')->get();
$paused = Workflow::where('status', 'paused')->get();

$combined = $active->merge($paused);
```

### `concat(array|Collection $items): Collection`

Concatenate values.

```php
$collection1 = new Collection([1, 2, 3]);
$collection2 = new Collection([4, 5, 6]);

$combined = $collection1->concat($collection2);
// [1, 2, 3, 4, 5, 6]
```

### `union(array|Collection $items): Collection`

Union with another collection (preserves keys).

```php
$collection1 = new Collection(['a' => 1, 'b' => 2]);
$collection2 = new Collection(['b' => 3, 'c' => 4]);

$union = $collection1->union($collection2);
// ['a' => 1, 'b' => 2, 'c' => 4]
```

---

## Unique & Duplicates

### `unique(string|callable $key = null): Collection`

Get unique items.

```php
$workflows = Workflow::all();

// Unique by status
$uniqueStatuses = $workflows->unique('status');

// Unique by callback
$unique = $workflows->unique(function($workflow) {
    return $workflow->user_id;
});
```

### `duplicates(string|callable $key = null): Collection`

Get duplicate items.

```php
$workflows = Workflow::all();

$duplicateStatuses = $workflows->duplicates('status');
```

---

## Searching

### `contains(string|callable $key, $operator = null, $value = null): bool`

Check if collection contains item.

```php
$workflows = Workflow::all();

// Check if contains value
if ($workflows->contains('status', 'active')) {
    echo "Has active workflows";
}

// Check with callback
if ($workflows->contains(function($workflow) {
    return $workflow->runs_count > 100;
})) {
    echo "Has popular workflows";
}
```

### `find(mixed $key, $default = null): mixed`

Find item by key.

```php
$workflows = Workflow::all()->keyBy('id');

$workflow = $workflows->find(123);
```

### `search(string|callable $value): mixed`

Search for value, return key.

```php
$collection = new Collection(['a', 'b', 'c']);

$key = $collection->search('b');
// 1
```

---

## Reducing

### `reduce(callable $callback, $initial = null): mixed`

Reduce collection to single value.

```php
$runs = Run::all();

// Sum all attempts
$totalAttempts = $runs->reduce(function($carry, $run) {
    return $carry + $run->attempts;
}, 0);

// Build string
$summary = $workflows->reduce(function($carry, $workflow) {
    return $carry . $workflow->title . ', ';
}, 'Workflows: ');
```

---

## Conversion

### `toArray(): array`

Convert to array.

```php
$workflows = Workflow::all();

$array = $workflows->toArray();
```

### `toJson(int $options = 0): string`

Convert to JSON.

```php
$workflows = Workflow::all();

$json = $workflows->toJson(JSON_PRETTY_PRINT);
```

---

## Array Access

Collections implement `ArrayAccess`, allowing array-like access:

```php
$workflows = Workflow::all();

// Access by index
echo $workflows[0]->title;

// Check existence
if (isset($workflows[0])) { }

// Set item
$workflows[0] = $newWorkflow;

// Unset item
unset($workflows[0]);
```

---

## Iteration

Collections are iterable:

```php
$workflows = Workflow::all();

// Foreach
foreach ($workflows as $workflow) {
    echo $workflow->title;
}

// With keys
foreach ($workflows as $key => $workflow) {
    echo "{$key}: {$workflow->title}";
}
```

---

## Method Chaining Examples

### Filter, Map, and Sort

```php
$workflows = Workflow::all();

$result = $workflows
    ->where('status', 'active')
    ->filter(function($w) { return $w->runs_count > 10; })
    ->map(function($w) {
        return [
            'id' => $w->id,
            'title' => $w->title,
            'popularity' => $w->runs_count
        ];
    })
    ->sortByDesc('popularity')
    ->take(10);
```

### Group and Count

```php
$runs = Run::all();

$stats = $runs
    ->groupBy('status')
    ->map(function($group) {
        return $group->count();
    });
// ['running' => 5, 'completed' => 100, 'failed' => 3]
```

### Pluck and Unique

```php
$workflows = Workflow::all();

$users = $workflows
    ->pluck('user_id')
    ->unique()
    ->values();
```

---

## Advanced Examples

### Dashboard Statistics

```php
$workflows = Workflow::all();

$stats = [
    'total' => $workflows->count(),
    'by_status' => $workflows->countBy('status'),
    'by_user' => $workflows->countBy('user_id'),
    'recent' => $workflows
        ->filter(fn($w) => $w->created_at > date('Y-m-d', strtotime('-7 days')))
        ->count(),
    'popular' => $workflows
        ->sortByDesc('runs_count')
        ->take(5)
        ->pluck('title', 'id'),
];
```

### Data Export

```php
$workflows = Workflow::all();

$csv = $workflows
    ->map(function($workflow) {
        return [
            'ID' => $workflow->id,
            'Title' => $workflow->title,
            'Status' => $workflow->status,
            'Created' => $workflow->created_at,
            'Runs' => $workflow->runs_count
        ];
    })
    ->toArray();

// Export to CSV
$fp = fopen('workflows.csv', 'w');
fputcsv($fp, array_keys($csv[0]));
foreach ($csv as $row) {
    fputcsv($fp, $row);
}
fclose($fp);
```

### Nested Grouping

```php
$runs = Run::all();

$grouped = $runs
    ->groupBy(function($run) {
        return date('Y-m', strtotime($run->started_at));
    })
    ->map(function($monthRuns) {
        return $monthRuns->groupBy('status');
    });

// [
//   '2024-01' => [
//     'completed' => Collection[...],
//     'failed' => Collection[...]
//   ],
//   '2024-02' => [...]
// ]
```

### Pagination with Metadata

```php
$workflows = Workflow::where('status', 'active')->get();

$page = 2;
$perPage = 20;

$paginated = [
    'data' => $workflows->forPage($page, $perPage)->values(),
    'meta' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $workflows->count(),
        'last_page' => ceil($workflows->count() / $perPage),
        'from' => ($page - 1) * $perPage + 1,
        'to' => min($page * $perPage, $workflows->count()),
    ]
];
```

---

## Performance Tips

1. **Chain methods efficiently**
   ```php
   // Good - filters before mapping
   $result = $workflows
       ->where('status', 'active')
       ->map(fn($w) => $w->toArray());

   // Avoid - maps everything first
   $result = $workflows
       ->map(fn($w) => $w->toArray())
       ->where('status', 'active');
   ```

2. **Use pluck() for simple extractions**
   ```php
   // Good - single method
   $ids = $workflows->pluck('id');

   // Avoid - unnecessary map
   $ids = $workflows->map(fn($w) => $w->id);
   ```

3. **Filter at database level when possible**
   ```php
   // Good - filters in database
   $active = Workflow::where('status', 'active')->get();

   // Avoid - loads all then filters
   $active = Workflow::all()->where('status', 'active');
   ```

4. **Use chunk() for large datasets**
   ```php
   $workflows = Workflow::all();

   $workflows->chunk(100)->each(function($chunk) {
       // Process 100 at a time
       processChunk($chunk);
   });
   ```

---

## Next Steps

- [Query Builder API](query-builder.md) - Building database queries
- [Models API](models.md) - Working with models
- [Working with Models Guide](../guides/working-with-models.md) - Practical examples

---

**Questions?** [Open an issue](https://github.com/yourusername/zaplane/issues) or [start a discussion](https://github.com/yourusername/zaplane/discussions).
