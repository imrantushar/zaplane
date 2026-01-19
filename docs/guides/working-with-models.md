# Working with Models

Practical guide for using the Zaplane ORM to work with database models.

---

## Overview

Zaplane's ORM provides an elegant, Laravel-inspired interface for database operations. This guide covers common patterns and real-world examples.

---

## Basic CRUD Operations

### Creating Records

```php
use Zaplane\Models\Workflow;

// Simple create
$workflow = Workflow::create([
    'user_id' => get_current_user_id(),
    'title' => 'My First Workflow',
    'name' => 'my-first-workflow',
    'status' => 'draft'
]);

// Create and modify
$workflow = new Workflow();
$workflow->user_id = get_current_user_id();
$workflow->title = 'My Workflow';
$workflow->name = 'my-workflow';
$workflow->status = 'draft';
$workflow->save();

// Timestamps are added automatically
echo $workflow->created_at; // 2024-01-15 10:30:00
echo $workflow->updated_at; // 2024-01-15 10:30:00
```

### Reading Records

```php
// Find by ID
$workflow = Workflow::find(123);

if ($workflow) {
    echo $workflow->title;
}

// Find or fail
try {
    $workflow = Workflow::findOrFail(123);
} catch (\Zaplane\Framework\Database\DatabaseException $e) {
    wp_die('Workflow not found');
}

// Get all
$allWorkflows = Workflow::all();

// Query with conditions
$active = Workflow::where('status', 'active')->get();

// Get first matching
$workflow = Workflow::where('name', 'my-workflow')->first();
```

### Updating Records

```php
// Update instance
$workflow = Workflow::find(123);
$workflow->title = 'Updated Title';
$workflow->status = 'active';
$workflow->save();

// Bulk update
$affected = Workflow::query()
    ->where('status', 'draft')
    ->where('created_at', '<', date('Y-m-d', strtotime('-30 days')))
    ->update(['status' => 'archived']);

echo "Archived {$affected} old drafts";
```

### Deleting Records

```php
// Delete instance
$workflow = Workflow::find(123);
$workflow->delete();

// Bulk delete
$deleted = Workflow::query()
    ->where('status', 'archived')
    ->where('created_at', '<', date('Y-m-d', strtotime('-1 year')))
    ->delete();

echo "Deleted {$deleted} old workflows";
```

---

## Querying

### Simple Where Clauses

```php
use Zaplane\Models\Workflow;

// Single condition
$active = Workflow::where('status', 'active')->get();

// Multiple conditions (AND)
$workflows = Workflow::query()
    ->where('user_id', get_current_user_id())
    ->where('status', 'active')
    ->get();

// OR conditions
$workflows = Workflow::query()
    ->where('status', 'active')
    ->orWhere('status', 'paused')
    ->get();
```

### Advanced Where Clauses

```php
// WHERE IN
$workflows = Workflow::whereIn('status', ['active', 'paused'])->get();

// WHERE NOT IN
$workflows = Workflow::whereNotIn('status', ['deleted', 'archived'])->get();

// WHERE NULL
$workflows = Workflow::whereNull('deleted_at')->get();

// WHERE NOT NULL
$workflows = Workflow::whereNotNull('title')->get();

// Comparison operators
$recent = Workflow::where('created_at', '>', date('Y-m-d', strtotime('-7 days')))->get();
$popular = Workflow::where('runs_count', '>=', 100)->get();

// LIKE searches
$search = 'notification';
$workflows = Workflow::where('title', 'LIKE', "%{$search}%")->get();
```

### Ordering

```php
// Order by single column
$workflows = Workflow::orderBy('created_at', 'DESC')->get();

// Order by multiple columns
$workflows = Workflow::query()
    ->orderBy('status', 'ASC')
    ->orderBy('created_at', 'DESC')
    ->get();

// Latest/Oldest shortcuts
$latest = Workflow::latest()->get(); // ORDER BY created_at DESC
$oldest = Workflow::oldest()->get(); // ORDER BY created_at ASC

// Custom column
$runs = Run::latest('started_at')->get();
```

### Limiting Results

```php
// Get first 10
$workflows = Workflow::limit(10)->get();

// Skip and take (pagination)
$page = 2;
$perPage = 20;
$workflows = Workflow::query()
    ->skip(($page - 1) * $perPage)
    ->take($perPage)
    ->get();

// Get first result
$workflow = Workflow::where('status', 'active')->first();
```

---

## Working with Collections

### Filtering Collections

```php
$workflows = Workflow::all();

// Filter active
$active = $workflows->where('status', 'active');

// Filter with callback
$recent = $workflows->filter(function($workflow) {
    return strtotime($workflow->created_at) > strtotime('-7 days');
});

// Chain filters
$result = $workflows
    ->where('status', 'active')
    ->filter(function($w) { return $w->runs_count > 10; });
```

### Mapping Collections

```php
$workflows = Workflow::all();

// Get titles
$titles = $workflows->pluck('title');
// ['Workflow 1', 'Workflow 2', ...]

// Get titles with IDs
$titlesById = $workflows->pluck('title', 'id');
// [1 => 'Workflow 1', 2 => 'Workflow 2', ...]

// Transform to arrays
$arrays = $workflows->map(function($workflow) {
    return [
        'id' => $workflow->id,
        'title' => $workflow->title,
        'is_active' => $workflow->status === 'active'
    ];
});
```

### Grouping Collections

```php
$workflows = Workflow::all();

// Group by status
$byStatus = $workflows->groupBy('status');
// [
//   'active' => Collection[...],
//   'paused' => Collection[...],
//   'draft' => Collection[...]
// ]

// Count by status
$counts = $workflows->countBy('status');
// ['active' => 10, 'paused' => 5, 'draft' => 3]

// Group by custom logic
$byMonth = $workflows->groupBy(function($workflow) {
    return date('Y-m', strtotime($workflow->created_at));
});
```

---

## Relationships

### One-to-Many

```php
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

// Get workflow's versions
$workflow = Workflow::find(1);
$versions = WorkflowVersion::where('workflow_id', $workflow->id)->get();

// Or add helper method to Workflow model
class Workflow extends Model
{
    public function versions()
    {
        return WorkflowVersion::where('workflow_id', $this->id)->get();
    }

    public function activeVersion()
    {
        return WorkflowVersion::query()
            ->where('workflow_id', $this->id)
            ->where('is_active', true)
            ->first();
    }
}

// Usage
$workflow = Workflow::find(1);
$versions = $workflow->versions();
$active = $workflow->activeVersion();
```

### Many-to-One

```php
use Zaplane\Models\WorkflowVersion;
use Zaplane\Models\Workflow;

// Get version's workflow
$version = WorkflowVersion::find(1);
$workflow = Workflow::find($version->workflow_id);

// Or add helper method to WorkflowVersion model
class WorkflowVersion extends Model
{
    public function workflow()
    {
        return Workflow::find($this->workflow_id);
    }
}

// Usage
$version = WorkflowVersion::find(1);
$workflow = $version->workflow();
```

### Complex Relationships

```php
class Workflow extends Model
{
    // Get all runs for this workflow
    public function runs()
    {
        $versions = $this->versions();
        $hashes = $versions->pluck('graph_hash');

        return Run::whereIn('workflow_version_hash', $hashes)->get();
    }

    // Get recent runs
    public function recentRuns(int $limit = 10)
    {
        $versions = $this->versions();
        $hashes = $versions->pluck('graph_hash');

        return Run::query()
            ->whereIn('workflow_version_hash', $hashes)
            ->orderBy('started_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    // Get run statistics
    public function getStats()
    {
        $runs = $this->runs();

        return [
            'total_runs' => $runs->count(),
            'completed' => $runs->where('status', 'completed')->count(),
            'failed' => $runs->where('status', 'failed')->count(),
            'running' => $runs->where('status', 'running')->count(),
            'avg_attempts' => $runs->avg('attempts'),
        ];
    }
}

// Usage
$workflow = Workflow::find(1);
$stats = $workflow->getStats();
/*
[
    'total_runs' => 150,
    'completed' => 140,
    'failed' => 8,
    'running' => 2,
    'avg_attempts' => 1.2
]
*/
```

---

## Real-World Examples

### User Dashboard

```php
function get_user_workflow_dashboard(int $userId)
{
    $workflows = Workflow::where('user_id', $userId)->get();

    return [
        'total' => $workflows->count(),
        'by_status' => $workflows->countBy('status'),
        'recent' => $workflows
            ->sortByDesc('updated_at')
            ->take(5)
            ->map(function($w) {
                return [
                    'id' => $w->id,
                    'title' => $w->title,
                    'status' => $w->status,
                    'updated' => human_time_diff(strtotime($w->updated_at))
                ];
            }),
        'most_active' => $workflows
            ->sortByDesc('runs_count')
            ->take(5)
            ->pluck('title', 'id')
    ];
}
```

### Workflow Search

```php
function search_workflows(string $query, array $filters = [])
{
    $builder = Workflow::query()
        ->where('user_id', get_current_user_id());

    // Search query
    if (!empty($query)) {
        $builder->where(function($q) use ($query) {
            $q->where('title', 'LIKE', "%{$query}%")
              ->orWhere('name', 'LIKE', "%{$query}%");
        });
    }

    // Status filter
    if (!empty($filters['status'])) {
        $builder->whereIn('status', (array)$filters['status']);
    }

    // Date range filter
    if (!empty($filters['from_date'])) {
        $builder->where('created_at', '>=', $filters['from_date']);
    }
    if (!empty($filters['to_date'])) {
        $builder->where('created_at', '<=', $filters['to_date']);
    }

    // Sorting
    $sortBy = $filters['sort_by'] ?? 'created_at';
    $sortDir = $filters['sort_dir'] ?? 'DESC';
    $builder->orderBy($sortBy, $sortDir);

    // Pagination
    $page = $filters['page'] ?? 1;
    $perPage = $filters['per_page'] ?? 20;

    $workflows = $builder->get();
    $total = $workflows->count();

    return [
        'data' => $workflows
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->values(),
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ]
    ];
}

// Usage
$results = search_workflows('notification', [
    'status' => ['active', 'paused'],
    'from_date' => '2024-01-01',
    'sort_by' => 'updated_at',
    'page' => 2
]);
```

### Bulk Status Update

```php
function bulk_update_workflow_status(array $workflowIds, string $newStatus)
{
    // Validate status
    $validStatuses = ['active', 'paused', 'draft', 'archived'];
    if (!in_array($newStatus, $validStatuses)) {
        return [
            'success' => false,
            'error' => 'Invalid status'
        ];
    }

    // Get workflows
    $workflows = Workflow::query()
        ->whereIn('id', $workflowIds)
        ->where('user_id', get_current_user_id()) // Security check
        ->get();

    if ($workflows->isEmpty()) {
        return [
            'success' => false,
            'error' => 'No workflows found'
        ];
    }

    // Update each workflow
    $updated = 0;
    foreach ($workflows as $workflow) {
        $workflow->status = $newStatus;
        if ($workflow->save()) {
            $updated++;
        }
    }

    return [
        'success' => true,
        'updated' => $updated,
        'total' => count($workflowIds)
    ];
}

// Usage
$result = bulk_update_workflow_status([1, 2, 3, 4], 'paused');
```

### Activity Log

```php
function get_workflow_activity_log(int $workflowId, int $days = 30)
{
    $workflow = Workflow::findOrFail($workflowId);
    $versions = WorkflowVersion::where('workflow_id', $workflowId)->get();
    $hashes = $versions->pluck('graph_hash');

    $runs = Run::query()
        ->whereIn('workflow_version_hash', $hashes)
        ->where('started_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
        ->orderBy('started_at', 'DESC')
        ->get();

    return [
        'workflow' => [
            'id' => $workflow->id,
            'title' => $workflow->title,
            'status' => $workflow->status
        ],
        'summary' => [
            'total_runs' => $runs->count(),
            'completed' => $runs->where('status', 'completed')->count(),
            'failed' => $runs->where('status', 'failed')->count(),
            'running' => $runs->where('status', 'running')->count(),
            'success_rate' => $runs->isNotEmpty()
                ? round(($runs->where('status', 'completed')->count() / $runs->count()) * 100, 2)
                : 0
        ],
        'runs' => $runs->map(function($run) {
            return [
                'id' => $run->id,
                'status' => $run->status,
                'started' => $run->started_at,
                'finished' => $run->finished_at,
                'duration' => $run->finished_at
                    ? strtotime($run->finished_at) - strtotime($run->started_at)
                    : null,
                'attempts' => $run->attempts,
                'error' => $run->last_error
            ];
        })
    ];
}
```

### Statistics Report

```php
function generate_workflow_statistics()
{
    $userId = get_current_user_id();

    $workflows = Workflow::where('user_id', $userId)->get();
    $connections = Connection::where('user_id', $userId)->get();

    // Get all runs for user's workflows
    $allVersions = WorkflowVersion::query()
        ->whereIn('workflow_id', $workflows->pluck('id'))
        ->get();
    $allHashes = $allVersions->pluck('graph_hash');
    $allRuns = Run::whereIn('workflow_version_hash', $allHashes)->get();

    return [
        'workflows' => [
            'total' => $workflows->count(),
            'active' => $workflows->where('status', 'active')->count(),
            'paused' => $workflows->where('status', 'paused')->count(),
            'draft' => $workflows->where('status', 'draft')->count(),
        ],
        'runs' => [
            'total' => $allRuns->count(),
            'completed' => $allRuns->where('status', 'completed')->count(),
            'failed' => $allRuns->where('status', 'failed')->count(),
            'running' => $allRuns->where('status', 'running')->count(),
            'success_rate' => $allRuns->isNotEmpty()
                ? round(($allRuns->where('status', 'completed')->count() / $allRuns->count()) * 100, 2)
                : 0,
            'avg_attempts' => $allRuns->avg('attempts'),
        ],
        'connections' => [
            'total' => $connections->count(),
            'active' => $connections->where('is_active', true)->count(),
            'by_integration' => $connections->countBy('integration_slug'),
        ],
        'recent_activity' => $allRuns
            ->sortByDesc('started_at')
            ->take(10)
            ->map(function($run) use ($allVersions, $workflows) {
                $version = $allVersions->firstWhere('graph_hash', $run->workflow_version_hash);
                $workflow = $workflows->firstWhere('id', $version->workflow_id ?? null);

                return [
                    'workflow_title' => $workflow->title ?? 'Unknown',
                    'status' => $run->status,
                    'started' => human_time_diff(strtotime($run->started_at)) . ' ago',
                ];
            })
    ];
}
```

---

## Performance Optimization

### Eager Loading Prevention

Since we don't have automatic eager loading, be careful with N+1 queries:

```php
// BAD - N+1 query problem
$workflows = Workflow::all();
foreach ($workflows as $workflow) {
    // Each iteration queries the database
    $versions = WorkflowVersion::where('workflow_id', $workflow->id)->get();
}

// GOOD - Load once
$workflows = Workflow::all();
$workflowIds = $workflows->pluck('id');
$allVersions = WorkflowVersion::whereIn('workflow_id', $workflowIds)->get();
$versionsByWorkflow = $allVersions->groupBy('workflow_id');

foreach ($workflows as $workflow) {
    $versions = $versionsByWorkflow[$workflow->id] ?? collect([]);
}
```

### Query at Database Level

```php
// BAD - Load all then filter
$workflows = Workflow::all()->where('status', 'active');

// GOOD - Filter at database level
$workflows = Workflow::where('status', 'active')->get();
```

### Use exists() Instead of count()

```php
// BAD - Counts all rows
if (Workflow::where('status', 'active')->count() > 0) { }

// GOOD - Stops at first match
if (Workflow::where('status', 'active')->exists()) { }
```

---

## Next Steps

- [Models API](../api/models.md) - Complete model API reference
- [Query Builder API](../api/query-builder.md) - Advanced querying
- [Collections API](../api/collections.md) - Collection methods
- [Creating Integrations](creating-integrations.md) - Build custom integrations

---

**Questions?** [Open an issue](https://github.com/yourusername/zaplane/issues) or [start a discussion](https://github.com/yourusername/zaplane/discussions).
