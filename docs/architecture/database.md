# Database Architecture

Complete reference for the Zaplane database schema and relationships.

---

## Overview

Zaplane uses a custom database schema with 6 core tables to manage workflows, executions, integrations, and background jobs. All tables use the `zaplane_` prefix to avoid conflicts with WordPress and other plugins.

---

## Database Tables

### 1. zaplane_workflows

Stores workflow definitions and metadata.

```sql
CREATE TABLE zaplane_workflows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    status ENUM('active', 'paused', 'draft') DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY status (status),
    KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Columns:**
- `id` - Unique workflow identifier
- `user_id` - WordPress user who owns the workflow
- `title` - Human-readable workflow name
- `name` - URL-safe slug (e.g., "send-slack-notifications")
- `status` - Workflow state: active (running), paused (stopped), draft (editing)
- `created_at` - When workflow was created
- `updated_at` - Last modification time

**Indexes:**
- Primary key on `id`
- Index on `user_id` for user filtering
- Index on `status` for status queries
- Index on `name` for slug lookups

---

### 2. zaplane_workflow_versions

Stores workflow graph versions with content-addressable hashing.

```sql
CREATE TABLE zaplane_workflow_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id BIGINT UNSIGNED NOT NULL,
    graph_json LONGTEXT NOT NULL,
    graph_hash CHAR(64) NOT NULL,
    is_active TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY graph_hash (graph_hash),
    KEY workflow_id (workflow_id),
    KEY is_active (is_active),
    CONSTRAINT fk_version_workflow FOREIGN KEY (workflow_id)
        REFERENCES zaplane_workflows(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Columns:**
- `id` - Version identifier
- `workflow_id` - Parent workflow reference
- `graph_json` - Complete workflow graph (nodes, edges, configuration)
- `graph_hash` - SHA-256 hash of graph_json for deduplication
- `is_active` - Whether this version is currently active (only one per workflow)
- `created_at` - When version was created

**Indexes:**
- Primary key on `id`
- Unique index on `graph_hash` for content-addressable storage
- Index on `workflow_id` for version history
- Index on `is_active` for active version lookups

**Foreign Keys:**
- `workflow_id` → `zaplane_workflows.id` (CASCADE DELETE)

**Design Notes:**
- Content-addressable: Identical graphs share the same hash, preventing duplicates
- Only one version can be `is_active=1` per workflow
- Cascade delete: Deleting workflow deletes all its versions

---

### 3. zaplane_runs

Stores workflow execution instances.

```sql
CREATE TABLE zaplane_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_version_hash CHAR(64) NOT NULL,
    target_node_key VARCHAR(255) DEFAULT NULL,
    start_node_key VARCHAR(255) DEFAULT NULL,
    status ENUM('running', 'completed', 'failed') DEFAULT 'running',
    trigger_data LONGTEXT DEFAULT NULL,
    attempts INT DEFAULT 1,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY workflow_version_hash (workflow_version_hash),
    KEY status (status),
    KEY started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Columns:**
- `id` - Unique run identifier
- `workflow_version_hash` - Which version was executed (references graph_hash)
- `target_node_key` - Optional: Specific node to run (for partial execution)
- `start_node_key` - Which node started the execution
- `status` - Execution state: running, completed, failed
- `trigger_data` - JSON data that triggered the workflow (e.g., webhook payload)
- `attempts` - How many times execution was attempted
- `started_at` - When execution began
- `finished_at` - When execution completed/failed
- `last_error` - Error message if failed

**Indexes:**
- Primary key on `id`
- Index on `workflow_version_hash` for version lookups
- Index on `status` for status filtering
- Index on `started_at` for time-based queries

**Design Notes:**
- References version by hash (not ID) for content-addressable lookup
- No foreign key constraint (versions can be deleted while preserving run history)
- Supports partial execution via `target_node_key`
- Stores trigger context for debugging

---

### 4. zaplane_node_runs

Stores individual node execution results within a run.

```sql
CREATE TABLE zaplane_node_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id BIGINT UNSIGNED NOT NULL,
    node_key VARCHAR(255) NOT NULL,
    parent_node_run_id BIGINT UNSIGNED DEFAULT NULL,
    iteration INT DEFAULT 0,
    status ENUM('pending', 'running', 'completed', 'failed', 'waiting') DEFAULT 'pending',
    input_json LONGTEXT DEFAULT NULL,
    output_json LONGTEXT DEFAULT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    started_at DATETIME DEFAULT NULL,
    finished_at DATETIME DEFAULT NULL,
    resume_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY run_id (run_id),
    KEY node_key (node_key),
    KEY status (status),
    KEY parent_node_run_id (parent_node_run_id),
    KEY resume_at (resume_at),
    CONSTRAINT fk_node_run_run FOREIGN KEY (run_id)
        REFERENCES zaplane_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_node_run_parent FOREIGN KEY (parent_node_run_id)
        REFERENCES zaplane_node_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Columns:**
- `id` - Node run identifier
- `run_id` - Parent run reference
- `node_key` - Which node in the graph (e.g., "action_1", "trigger_1")
- `parent_node_run_id` - Previous node in execution chain
- `iteration` - For loops: which iteration (0-indexed)
- `status` - Node state: pending, running, completed, failed, waiting (for delays)
- `input_json` - Input data to the node
- `output_json` - Output data from the node
- `attempts` - Retry counter
- `max_attempts` - Maximum retries allowed
- `started_at` - When node execution started
- `finished_at` - When node execution completed
- `resume_at` - For delays: when to resume execution

**Indexes:**
- Primary key on `id`
- Index on `run_id` for run lookups
- Index on `node_key` for node filtering
- Index on `status` for queue processing
- Index on `parent_node_run_id` for chain traversal
- Index on `resume_at` for scheduled resumption

**Foreign Keys:**
- `run_id` → `zaplane_runs.id` (CASCADE DELETE)
- `parent_node_run_id` → `zaplane_node_runs.id` (SET NULL on delete)

**Design Notes:**
- Tracks execution chain via `parent_node_run_id`
- Supports retries with `attempts` and `max_attempts`
- Supports delays with `resume_at` (for scheduled actions)
- Supports loops with `iteration` counter

---

### 5. zaplane_connections

Stores user connections to external services.

```sql
CREATE TABLE zaplane_connections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    integration_slug VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    auth_type VARCHAR(50) NOT NULL,
    credentials_encrypted LONGTEXT NOT NULL,
    metadata LONGTEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_tested_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY integration_slug (integration_slug),
    KEY is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Columns:**
- `id` - Connection identifier
- `user_id` - WordPress user who owns the connection
- `integration_slug` - Which integration (e.g., "slack", "mailchimp")
- `name` - User-friendly connection name
- `auth_type` - Authentication method: "api_key", "oauth2", "basic", etc.
- `credentials_encrypted` - Encrypted credentials (API keys, tokens, etc.)
- `metadata` - Additional connection info (account name, etc.)
- `is_active` - Whether connection is currently valid
- `last_tested_at` - Last successful connection test
- `created_at` - When connection was created
- `updated_at` - Last modification time

**Indexes:**
- Primary key on `id`
- Index on `user_id` for user filtering
- Index on `integration_slug` for integration lookups
- Index on `is_active` for active connection queries

**Security:**
- Credentials stored encrypted using WordPress's encryption functions
- Never exposed in API responses
- Decrypted only when executing actions

---

### 6. zaplane_queue

Stores background jobs for asynchronous processing.

```sql
CREATE TABLE zaplane_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue VARCHAR(255) NOT NULL DEFAULT 'default',
    payload LONGTEXT NOT NULL,
    attempts INT DEFAULT 0,
    reserved_at DATETIME DEFAULT NULL,
    available_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY queue (queue),
    KEY reserved_at (reserved_at),
    KEY available_at (available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Columns:**
- `id` - Job identifier
- `queue` - Queue name (default, email, webhooks, etc.)
- `payload` - Serialized job data
- `attempts` - How many times job was attempted
- `reserved_at` - When job was claimed by a worker
- `available_at` - When job should be processed
- `created_at` - When job was queued

**Indexes:**
- Primary key on `id`
- Index on `queue` for queue filtering
- Index on `reserved_at` for worker coordination
- Index on `available_at` for scheduled jobs

**Design Notes:**
- Supports delayed jobs via `available_at`
- Prevents duplicate processing via `reserved_at`
- Multiple queues for priority management
- Used by Action Scheduler integration

---

## Relationships

### Workflow → Versions (One-to-Many)

```
zaplane_workflows (1) ← (N) zaplane_workflow_versions
    id                          workflow_id
```

One workflow has many versions. Deleting a workflow cascades to all versions.

### Version → Runs (One-to-Many via Hash)

```
zaplane_workflow_versions (1) ← (N) zaplane_runs
    graph_hash                       workflow_version_hash
```

One version can have many runs. No foreign key constraint (preserve run history).

### Run → Node Runs (One-to-Many)

```
zaplane_runs (1) ← (N) zaplane_node_runs
    id                   run_id
```

One run has many node runs. Deleting a run cascades to all node runs.

### Node Run → Parent Node Run (Self-Referential)

```
zaplane_node_runs (1) ← (N) zaplane_node_runs
    id                        parent_node_run_id
```

Node runs form an execution chain. Deleting a parent sets children's parent to NULL.

### User → Workflows (One-to-Many)

```
wp_users (1) ← (N) zaplane_workflows
    ID                user_id
```

One user owns many workflows. No foreign key (WordPress table).

### User → Connections (One-to-Many)

```
wp_users (1) ← (N) zaplane_connections
    ID                user_id
```

One user has many connections. No foreign key (WordPress table).

---

## ER Diagram

```
┌─────────────────┐
│   wp_users      │
│ ────────────── │
│ ID (PK)         │
└────────┬────────┘
         │ 1
         │
         │ N
    ┌────┴────────────────┐
    │                     │
┌───▼──────────────┐  ┌──▼────────────────┐
│ zaplane_workflows│  │zaplane_connections│
│ ──────────────── │  │──────────────────│
│ id (PK)          │  │ id (PK)          │
│ user_id (FK)     │  │ user_id (FK)     │
│ title            │  │ integration_slug │
│ name             │  │ name             │
│ status           │  │ credentials_enc  │
└────────┬─────────┘  └──────────────────┘
         │ 1
         │
         │ N
┌────────▼────────────────────┐
│zaplane_workflow_versions    │
│ ──────────────────────────  │
│ id (PK)                     │
│ workflow_id (FK)            │
│ graph_json                  │
│ graph_hash (UNIQUE)         │
│ is_active                   │
└────────┬────────────────────┘
         │ 1 (via graph_hash)
         │
         │ N
    ┌────▼─────────┐
    │zaplane_runs  │
    │ ────────────│
    │ id (PK)      │
    │ workflow_    │
    │  version_hash│
    │ status       │
    │ trigger_data │
    └────┬─────────┘
         │ 1
         │
         │ N
    ┌────▼──────────────┐
    │zaplane_node_runs  │
    │ ──────────────── │
    │ id (PK)          │
    │ run_id (FK)      │──┐
    │ node_key         │  │
    │ parent_node_     │  │
    │  run_id (FK)     │◄─┘ (self-ref)
    │ input_json       │
    │ output_json      │
    └──────────────────┘
```

---

## Migration System

### Creating Tables

Tables are created via the Migration system:

```php
use Zaplane\Framework\Database\Schema;
use Zaplane\Framework\Database\Blueprint;

class CreateWorkflowsTable extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('title');
            $table->string('name');
            $table->enum('status', ['active', 'paused', 'draft'])->default('draft');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
}
```

### Running Migrations

```bash
# Run all pending migrations
wp zaplane migrate

# Rollback last batch
wp zaplane migrate:rollback

# Reset all migrations
wp zaplane migrate:reset

# Fresh migrate (drop all + re-run)
wp zaplane migrate:fresh
```

See [Migration Guide](../guides/migrations.md) for complete documentation.

---

## Query Performance

### Optimized Indexes

All tables have indexes on frequently queried columns:

- **Primary keys** for fast ID lookups
- **Foreign keys** for join performance
- **Status columns** for filtering
- **Timestamp columns** for sorting and range queries
- **Unique constraints** for content-addressable storage

### Query Examples

```php
// Fast: Uses index on status
$active = Workflow::where('status', 'active')->get();

// Fast: Uses index on workflow_version_hash
$runs = Run::where('workflow_version_hash', $hash)->get();

// Fast: Uses index on run_id
$nodeRuns = NodeRun::where('run_id', 123)->get();

// Fast: Uses unique index on graph_hash
$version = WorkflowVersion::where('graph_hash', $hash)->first();
```

### N+1 Query Prevention

```php
// BAD: N+1 queries
$workflows = Workflow::all();
foreach ($workflows as $workflow) {
    $versions = WorkflowVersion::where('workflow_id', $workflow->id)->get();
}

// GOOD: Single query
$workflows = Workflow::all();
$versions = WorkflowVersion::whereIn('workflow_id', $workflows->pluck('id'))->get();
$versionsByWorkflow = $versions->groupBy('workflow_id');
```

---

## Backup & Restore

### Export Data

```bash
# Export all Zaplane tables
wp db export zaplane-backup.sql --tables=$(wp db query "SHOW TABLES LIKE 'zaplane_%'" --skip-column-names | tr '\n' ',')

# Export specific table
wp db export workflows.sql --tables=zaplane_workflows
```

### Import Data

```bash
wp db import zaplane-backup.sql
```

### Programmatic Backup

```php
function backup_zaplane_data()
{
    return [
        'workflows' => Workflow::all()->toArray(),
        'versions' => WorkflowVersion::all()->toArray(),
        'runs' => Run::all()->toArray(),
        'node_runs' => NodeRun::all()->toArray(),
        'connections' => Connection::all()->map(function($conn) {
            // Don't export credentials
            $data = $conn->toArray();
            unset($data['credentials_encrypted']);
            return $data;
        })->toArray(),
    ];
}
```

---

## Next Steps

- [ORM Documentation](orm.md) - Working with models
- [Migration Guide](../guides/migrations.md) - Database migrations
- [Models API](../api/models.md) - Model reference
- [Query Builder API](../api/query-builder.md) - Querying database

---

**Questions?** [Open an issue](https://github.com/yourusername/zaplane/issues) or [start a discussion](https://github.com/yourusername/zaplane/discussions).
