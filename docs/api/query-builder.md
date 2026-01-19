# Query Builder API Reference

Complete reference for the Zaplane ORM Query Builder.

---

## Overview

The Query Builder provides a fluent interface for building database queries. It supports all standard SQL operations with a chainable, expressive syntax.

**Namespace:** `Zaplane\Framework\Database\ORM\QueryBuilder`

---

## Creating Query Builders

### From Models

```php
use Zaplane\Models\Workflow;

// Get query builder instance
$query = Workflow::query();

// Shorthand for simple where
$query = Workflow::where('status', 'active');
```

### Direct Instantiation

```php
use Zaplane\Framework\Database\ORM\QueryBuilder;

$query = new QueryBuilder();
$query->from('zaplane_workflows');
```

---

## Select Queries

### `select(...$columns): self`

Specify columns to select.

```php
// Select specific columns
$query->select('id', 'title', 'status');

// Select with aliases
$query->select('id', 'title as name');

// Select all (default)
$query->select('*');
```

### `distinct(): self`

Add DISTINCT clause.

```php
$statuses = Workflow::query()
    ->distinct()
    ->select('status')
    ->get();
```

---

## Where Clauses

### `where(string $column, $operator, $value = null): self`

Basic where clause.

```php
// Two parameter form (assumes '=' operator)
$query->where('status', 'active');

// Three parameter form
$query->where('created_at', '>', '2024-01-01');
$query->where('attempts', '<=', 3);
$query->where('title', 'LIKE', '%workflow%');
```

**Supported operators:**
- `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`
- `LIKE`, `NOT LIKE`
- `IN`, `NOT IN`
- `BETWEEN`, `NOT BETWEEN`
- `IS NULL`, `IS NOT NULL`

### `orWhere(string $column, $operator, $value = null): self`

OR where clause.

```php
$query->where('status', 'active')
      ->orWhere('status', 'paused');
```

### `whereIn(string $column, array $values): self`

WHERE IN clause.

```php
$query->whereIn('status', ['active', 'paused', 'draft']);
```

### `whereNotIn(string $column, array $values): self`

WHERE NOT IN clause.

```php
$query->whereNotIn('id', [1, 2, 3]);
```

### `whereBetween(string $column, $min, $max): self`

WHERE BETWEEN clause.

```php
$query->whereBetween('created_at', '2024-01-01', '2024-12-31');
```

### `whereNull(string $column): self`

WHERE IS NULL clause.

```php
$query->whereNull('deleted_at');
```

### `whereNotNull(string $column): self`

WHERE IS NOT NULL clause.

```php
$query->whereNotNull('finished_at');
```

### Advanced Where Examples

```php
// Multiple conditions (AND)
$query->where('user_id', 1)
      ->where('status', 'active')
      ->where('created_at', '>', '2024-01-01');

// Mixed AND/OR
$query->where('user_id', 1)
      ->where(function($q) {
          $q->where('status', 'active')
            ->orWhere('status', 'paused');
      });

// Complex conditions
$workflows = Workflow::query()
    ->where('user_id', get_current_user_id())
    ->whereIn('status', ['active', 'paused'])
    ->whereNotNull('title')
    ->where('created_at', '>', date('Y-m-d', strtotime('-30 days')))
    ->get();
```

---

## Joins

### `join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self`

Add a JOIN clause.

```php
// Inner join
$query->join('zaplane_workflow_versions', 'workflows.id', '=', 'workflow_versions.workflow_id');

// Left join
$query->join('zaplane_runs', 'workflows.id', '=', 'runs.workflow_id', 'LEFT');

// Example with model
$workflows = Workflow::query()
    ->join('zaplane_workflow_versions as v', 'workflows.id', '=', 'v.workflow_id')
    ->where('v.is_active', true)
    ->select('workflows.*', 'v.graph_json')
    ->get();
```

### `leftJoin(string $table, string $first, string $operator, string $second): self`

Shorthand for LEFT JOIN.

```php
$query->leftJoin('zaplane_runs', 'workflows.id', '=', 'runs.workflow_id');
```

---

## Ordering

### `orderBy(string $column, string $direction = 'ASC'): self`

Order results.

```php
$query->orderBy('created_at', 'DESC');
$query->orderBy('title', 'ASC');

// Multiple orders
$query->orderBy('status', 'ASC')
      ->orderBy('created_at', 'DESC');
```

### `orderByDesc(string $column): self`

Shorthand for descending order.

```php
$query->orderByDesc('created_at');
```

### `latest(?string $column = null): self`

Order by timestamp column descending.

```php
// Uses model's created_at column
$query->latest();

// Custom column
$query->latest('updated_at');
```

### `oldest(?string $column = null): self`

Order by timestamp column ascending.

```php
// Uses model's created_at column
$query->oldest();

// Custom column
$query->oldest('started_at');
```

---

## Limit & Offset

### `limit(int $limit): self`

Limit number of results.

```php
$query->limit(10);
```

### `offset(int $offset): self`

Skip results.

```php
$query->offset(20);
```

### `take(int $count): self`

Alias for limit.

```php
$query->take(10);
```

### `skip(int $count): self`

Alias for offset.

```php
$query->skip(20);
```

### Pagination Example

```php
$page = 2;
$perPage = 20;

$workflows = Workflow::query()
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit($perPage)
    ->offset(($page - 1) * $perPage)
    ->get();
```

---

## Aggregates

### `count(string $column = '*'): int`

Count records.

```php
$total = Workflow::query()->count();
$active = Workflow::where('status', 'active')->count();
```

### `max(string $column): mixed`

Get maximum value.

```php
$maxAttempts = Run::query()->max('attempts');
```

### `min(string $column): mixed`

Get minimum value.

```php
$minAttempts = Run::query()->min('attempts');
```

### `avg(string $column): float`

Get average value.

```php
$avgAttempts = Run::query()->avg('attempts');
```

### `sum(string $column): float`

Get sum of values.

```php
$totalAttempts = Run::query()->sum('attempts');
```

---

## Executing Queries

### `get(): Collection`

Execute and get all results.

```php
$workflows = Workflow::query()
    ->where('status', 'active')
    ->get();

// Returns Collection instance
foreach ($workflows as $workflow) {
    echo $workflow->title;
}
```

### `first(): ?Model`

Get first result or null.

```php
$workflow = Workflow::query()
    ->where('id', 123)
    ->first();

if ($workflow) {
    echo $workflow->title;
}
```

### `firstOrFail(): Model`

Get first result or throw exception.

```php
use Zaplane\Framework\Database\DatabaseException;

try {
    $workflow = Workflow::query()
        ->where('id', 123)
        ->firstOrFail();
} catch (DatabaseException $e) {
    // Handle not found
}
```

### `exists(): bool`

Check if records exist.

```php
if (Workflow::where('status', 'active')->exists()) {
    echo "Has active workflows";
}
```

### `doesntExist(): bool`

Check if no records exist.

```php
if (Run::where('status', 'running')->doesntExist()) {
    echo "No running workflows";
}
```

---

## Updating

### `update(array $values): int`

Update records.

```php
// Update matching records
$affected = Workflow::query()
    ->where('status', 'draft')
    ->update(['status' => 'active']);

echo "Updated {$affected} workflows";
```

---

## Deleting

### `delete(): int`

Delete records.

```php
// Delete matching records
$deleted = Workflow::query()
    ->where('status', 'draft')
    ->where('created_at', '<', date('Y-m-d', strtotime('-30 days')))
    ->delete();

echo "Deleted {$deleted} old drafts";
```

---

## Raw Queries

### `toSql(): string`

Get SQL query string.

```php
$query = Workflow::query()
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC');

echo $query->toSql();
// SELECT * FROM zaplane_workflows WHERE status = ? ORDER BY created_at DESC
```

### `getBindings(): array`

Get query parameter bindings.

```php
$bindings = $query->getBindings();
// ['active']
```

---

## Query Caching

The Query Builder automatically caches query results based on SQL and bindings.

```php
// First call - executes query
$workflows = Workflow::where('status', 'active')->get();

// Second call - returns cached result
$workflows = Workflow::where('status', 'active')->get();
```

**Cache Details:**
- Cache key: MD5 hash of SQL + bindings
- Max cache size: 100 entries
- Eviction: LRU (Least Recently Used)
- Scope: Per-request (not persistent)

To clear query cache:

```php
QueryBuilder::clearCache();
```

---

## Advanced Examples

### Complex Query with Joins

```php
$activeWorkflowsWithRuns = Workflow::query()
    ->join('zaplane_workflow_versions as v', 'workflows.id', '=', 'v.workflow_id')
    ->leftJoin('zaplane_runs as r', 'v.graph_hash', '=', 'r.workflow_version_hash')
    ->where('workflows.status', 'active')
    ->where('v.is_active', true)
    ->whereNotNull('r.id')
    ->select('workflows.*', 'COUNT(r.id) as run_count')
    ->groupBy('workflows.id')
    ->having('run_count', '>', 0)
    ->orderBy('run_count', 'DESC')
    ->get();
```

### Search Query

```php
$search = 'notification';

$workflows = Workflow::query()
    ->where(function($q) use ($search) {
        $q->where('title', 'LIKE', "%{$search}%")
          ->orWhere('name', 'LIKE', "%{$search}%");
    })
    ->where('status', '!=', 'deleted')
    ->orderBy('updated_at', 'DESC')
    ->limit(20)
    ->get();
```

### Statistics Query

```php
$stats = [
    'total' => Workflow::count(),
    'active' => Workflow::where('status', 'active')->count(),
    'paused' => Workflow::where('status', 'paused')->count(),
    'draft' => Workflow::where('status', 'draft')->count(),
    'total_runs' => Run::count(),
    'running' => Run::where('status', 'running')->count(),
    'completed' => Run::where('status', 'completed')->count(),
    'failed' => Run::where('status', 'failed')->count(),
    'avg_attempts' => Run::avg('attempts'),
];
```

### Batch Processing

```php
$perPage = 100;
$offset = 0;

do {
    $workflows = Workflow::query()
        ->where('status', 'active')
        ->limit($perPage)
        ->offset($offset)
        ->get();

    foreach ($workflows as $workflow) {
        // Process workflow
        processWorkflow($workflow);
    }

    $offset += $perPage;

} while ($workflows->count() === $perPage);
```

---

## Performance Tips

1. **Use select() to limit columns**
   ```php
   // Good - only fetch needed columns
   $workflows = Workflow::query()
       ->select('id', 'title', 'status')
       ->get();

   // Avoid - fetches all columns
   $workflows = Workflow::all();
   ```

2. **Use exists() instead of count()**
   ```php
   // Good - stops at first match
   if (Workflow::where('status', 'active')->exists()) { }

   // Avoid - counts all rows
   if (Workflow::where('status', 'active')->count() > 0) { }
   ```

3. **Leverage query caching**
   ```php
   // Repeated queries are cached automatically
   $active = Workflow::where('status', 'active')->get();
   $active = Workflow::where('status', 'active')->get(); // From cache
   ```

4. **Use whereIn() for multiple values**
   ```php
   // Good - single query
   $workflows = Workflow::whereIn('id', [1, 2, 3])->get();

   // Avoid - multiple queries
   $workflows = [];
   foreach ([1, 2, 3] as $id) {
       $workflows[] = Workflow::find($id);
   }
   ```

5. **Add indexes to frequently queried columns**
   ```php
   // In migration
   $table->index('status');
   $table->index('user_id');
   $table->index(['status', 'created_at']);
   ```

---

## Next Steps

- [Collections API](collections.md) - Working with query results
- [Models API](models.md) - Model methods and relationships
- [Working with Models Guide](../guides/working-with-models.md) - Practical examples

---

**Questions?** [Open an issue](https://github.com/yourusername/zaplane/issues) or [start a discussion](https://github.com/yourusername/zaplane/discussions).
