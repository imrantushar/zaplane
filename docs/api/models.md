# Models API Reference

Complete reference for all Zaplane data models.

---

## Base Model

All models extend `Zaplane\Framework\Database\ORM\Model`.

### Properties

```php
// Table name
protected static string $table = '';

// Primary key
protected static string $primaryKey = 'id';

// Fillable columns
protected static array $fillable = [];

// Guarded columns
protected static array $guarded = ['id'];

// Hidden columns (excluded from JSON)
protected static array $hidden = [];

// Type casting
protected static array $casts = [];

// Enable timestamps
protected static bool $timestamps = true;

// Created at column name
protected static string $createdAt = 'created_at';

// Updated at column name
protected static string $updatedAt = 'updated_at';
```

### Static Methods

#### `all(): Collection`
Get all records.

```php
$workflows = Workflow::all();
```

#### `find(int $id): ?self`
Find by primary key.

```php
$workflow = Workflow::find(123);
```

#### `findOrFail(int $id): self`
Find or throw exception.

```php
try {
    $workflow = Workflow::findOrFail(123);
} catch (DatabaseException $e) {
    // Handle not found
}
```

#### `create(array $attributes): self`
Create new record.

```php
$workflow = Workflow::create([
    'user_id' => 1,
    'title' => 'My Workflow',
    'status' => 'active'
]);
```

#### `query(): QueryBuilder`
Get query builder instance.

```php
$query = Workflow::query()
    ->where('status', 'active')
    ->orderBy('created_at', 'desc');
```

#### `where(string $column, $value): QueryBuilder`
Shorthand for `query()->where()`.

```php
$active = Workflow::where('status', 'active')->get();
```

### Instance Methods

#### `save(): bool`
Save model to database.

```php
$workflow->title = 'Updated Title';
$workflow->save();
```

#### `delete(): bool`
Delete model.

```php
$workflow->delete();
```

#### `fresh(): ?self`
Reload from database.

```php
$workflow = $workflow->fresh();
```

#### `toArray(): array`
Convert to array.

```php
$array = $workflow->toArray();
```

#### `toJson(int $options = 0): string`
Convert to JSON.

```php
$json = $workflow->toJson(JSON_PRETTY_PRINT);
```

---

## Workflow Model

**Namespace:** `Zaplane\Models\Workflow`
**Table:** `zaplane_workflows`

### Schema

```php
{
    id: integer (primary key)
    user_id: integer
    title: string
    name: string
    status: enum('active', 'paused', 'draft')
    created_at: datetime
    updated_at: datetime
}
```

### Usage

```php
use Zaplane\Models\Workflow;

// Create
$workflow = Workflow::create([
    'user_id' => get_current_user_id(),
    'title' => 'Send Slack Notifications',
    'name' => 'slack-notifications',
    'status' => 'draft'
]);

// Find
$workflow = Workflow::find(1);

// Query
$activeWorkflows = Workflow::where('status', 'active')->get();

// Update
$workflow->status = 'active';
$workflow->save();

// Delete
$workflow->delete();
```

### Relationships

```php
// Get versions
$versions = $workflow->versions();

// Get active version
$activeVersion = $workflow->activeVersion();

// Get runs
$runs = $workflow->runs();
```

---

## WorkflowVersion Model

**Namespace:** `Zaplane\Models\WorkflowVersion`
**Table:** `zaplane_workflow_versions`

### Schema

```php
{
    id: integer (primary key)
    workflow_id: integer
    graph_json: longtext (JSON)
    graph_hash: char(64)
    is_active: boolean
    created_at: datetime
}
```

### Usage

```php
use Zaplane\Models\WorkflowVersion;

// Create
$version = WorkflowVersion::create([
    'workflow_id' => 1,
    'graph_json' => json_encode([
        'nodes' => [...],
        'edges' => [...]
    ]),
    'graph_hash' => hash('sha256', $graphJson),
    'is_active' => true
]);

// Get active version for workflow
$active = WorkflowVersion::where('workflow_id', 1)
    ->where('is_active', true)
    ->first();

// Get by hash
$version = WorkflowVersion::where('graph_hash', $hash)->first();
```

### Methods

```php
// Get workflow
$workflow = $version->workflow();

// Get runs
$runs = $version->runs();

// Get graph as array
$graph = $version->getGraph();

// Set graph
$version->setGraph(['nodes' => [...], 'edges' => [...]]);
```

---

## Run Model

**Namespace:** `Zaplane\Models\Run`
**Table:** `zaplane_runs`

### Schema

```php
{
    id: integer (primary key)
    workflow_version_hash: char(64)
    target_node_key: string
    start_node_key: string
    status: enum('running', 'completed', 'failed')
    trigger_data: longtext (JSON)
    attempts: integer
    started_at: datetime
    finished_at: datetime
    last_error: text
}
```

### Custom Timestamps

```php
protected static string $createdAt = 'started_at';
protected static string $updatedAt = 'finished_at';
```

### Usage

```php
use Zaplane\Models\Run;

// Create
$run = Run::create([
    'workflow_version_hash' => 'abc123...',
    'start_node_key' => 'trigger_1',
    'status' => 'running',
    'trigger_data' => ['post_id' => 123],
    'started_at' => current_time('mysql')
]);

// Get recent runs
$runs = Run::latest()->limit(10)->get();

// Get running
$running = Run::where('status', 'running')->get();

// Get for workflow version
$runs = Run::forWorkflowVersion($hash);
```

### Methods

```php
// Mark as completed
$run->markAsCompleted();

// Mark as failed
$run->markAsFailed('Error message');

// Increment attempts
$run->incrementAttempts();

// Check status
if ($run->isRunning()) { ... }
if ($run->isCompleted()) { ... }
if ($run->isFailed()) { ... }

// Get node runs
$nodeRuns = $run->nodeRuns();

// Get execution edges
$edges = $run->executionEdges();

// Get workflow version
$version = $run->workflowVersion();
```

---

## NodeRun Model

**Namespace:** `Zaplane\Models\NodeRun`
**Table:** `zaplane_node_runs`

### Schema

```php
{
    id: integer (primary key)
    run_id: integer
    node_key: string
    parent_node_run_id: integer
    iteration: integer
    status: enum('pending', 'running', 'completed', 'failed', 'waiting')
    input_json: longtext (JSON)
    output_json: longtext (JSON)
    attempts: integer
    max_attempts: integer
    started_at: datetime
    finished_at: datetime
    resume_at: datetime
}
```

### Usage

```php
use Zaplane\Models\NodeRun;

// Create
$nodeRun = NodeRun::create([
    'run_id' => 1,
    'node_key' => 'action_1',
    'parent_node_run_id' => null,
    'status' => 'pending',
    'input_json' => ['data' => 'value'],
    'started_at' => current_time('mysql')
]);

// Get by run
$nodeRuns = NodeRun::where('run_id', 1)->get();

// Get pending
$pending = NodeRun::where('status', 'pending')->get();
```

### Methods

```php
// Get/set input
$input = $nodeRun->getInput();
$nodeRun->setInput(['key' => 'value']);

// Get/set output
$output = $nodeRun->getOutput();
$nodeRun->setOutput(['result' => 'success']);

// Mark as running
$nodeRun->markAsRunning();

// Mark as completed
$nodeRun->markAsCompleted();

// Mark as failed
$nodeRun->markAsFailed('Error message');

// Get run
$run = $nodeRun->run();

// Get parent
$parent = $nodeRun->parent();

// Get children
$children = $nodeRun->children();
```

---

## Connection Model

**Namespace:** `Zaplane\Models\Connection`
**Table:** `zaplane_connections`

### Schema

```php
{
    id: integer (primary key)
    user_id: integer
    integration_slug: string
    name: string
    auth_type: string
    credentials_encrypted: longtext
    metadata: longtext (JSON)
    is_active: boolean
    last_tested_at: datetime
    created_at: datetime
    updated_at: datetime
}
```

### Usage

```php
use Zaplane\Models\Connection;

// Create
$connection = Connection::create([
    'user_id' => get_current_user_id(),
    'integration_slug' => 'slack',
    'name' => 'My Slack Connection',
    'auth_type' => 'oauth2',
    'is_active' => true
]);

// Get user connections
$connections = Connection::where('user_id', get_current_user_id())->get();

// Get by integration
$slackConnections = Connection::where('integration_slug', 'slack')->get();
```

### Methods

```php
// Get/set credentials (auto encrypts/decrypts)
$credentials = $connection->getCredentials();
$connection->setCredentials(['token' => 'abc123']);

// Get metadata
$metadata = $connection->getMetadata();
$connection->setMetadata(['key' => 'value']);

// Test connection
$result = $connection->test();

// Refresh OAuth token
$connection->refreshOAuthToken();
```

---

## QueueJob Model

**Namespace:** `Zaplane\Models\QueueJob`
**Table:** `zaplane_queue`

### Schema

```php
{
    id: integer (primary key)
    queue: string
    payload: longtext (JSON)
    attempts: integer
    reserved_at: datetime
    available_at: datetime
    created_at: datetime
}
```

---

## Type Casting

Models support automatic type casting:

```php
protected static array $casts = [
    'id' => 'integer',
    'user_id' => 'integer',
    'is_active' => 'boolean',
    'metadata' => 'json',
    'created_at' => 'datetime'
];
```

Supported types:
- `integer` / `int`
- `float` / `double`
- `string`
- `boolean` / `bool`
- `json` (auto encode/decode)
- `array`
- `datetime`

---

## Mass Assignment

### Fillable

```php
protected static array $fillable = [
    'title',
    'name',
    'status'
];

// Only these columns can be mass-assigned
$workflow = Workflow::create($request->all());
```

### Guarded

```php
protected static array $guarded = [
    'id',
    'created_at'
];

// These columns cannot be mass-assigned
```

---

## Query Scopes

Create reusable query scopes:

```php
class Workflow extends Model {
    public static function active(): QueryBuilder {
        return static::where('status', 'active');
    }

    public static function forUser(int $userId): QueryBuilder {
        return static::where('user_id', $userId);
    }
}

// Usage
$workflows = Workflow::active()->forUser(123)->get();
```

---

## Events

Models support lifecycle events:

```php
class Workflow extends Model {
    protected function afterCreate(): void {
        zaplane_log_info('Workflow created', ['id' => $this->id]);
    }

    protected function beforeDelete(): void {
        // Cleanup before delete
    }
}
```

Available hooks:
- `beforeCreate()`
- `afterCreate()`
- `beforeUpdate()`
- `afterUpdate()`
- `beforeDelete()`
- `afterDelete()`

---

## Next Steps

- [Query Builder API](query-builder.md) - Advanced querying
- [Collections API](collections.md) - Working with result sets
- [Working with Models Guide](../guides/working-with-models.md) - Practical examples

---

**Questions?** [Open an issue](https://github.com/yourusername/zaplane/issues) or [start a discussion](https://github.com/yourusername/zaplane/discussions).
