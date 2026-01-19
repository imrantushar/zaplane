# Architecture Overview

A comprehensive guide to Zaplane's system architecture and design patterns.

---

## System Architecture

Zaplane follows a layered architecture pattern inspired by Laravel and modern PHP frameworks:

```
┌─────────────────────────────────────────────────────────────┐
│                     Presentation Layer                       │
│  ┌────────────┐  ┌────────────┐  ┌─────────────────────┐  │
│  │  React UI  │  │  REST API  │  │   Admin Interface   │  │
│  └────────────┘  └────────────┘  └─────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
│  ┌────────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │   Automation   │  │  Workflow    │  │  Integration  │  │
│  │     Engine     │  │   Manager    │  │    Loader     │  │
│  └────────────────┘  └──────────────┘  └───────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                      Framework Layer                         │
│  ┌──────┐  ┌────────┐  ┌────────┐  ┌────────┐  ┌───────┐ │
│  │ ORM  │  │ Config │  │ Logger │  │ Cache  │  │ Queue │ │
│  └──────┘  └────────┘  └────────┘  └────────┘  └───────┘ │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                       Data Layer                             │
│  ┌────────┐  ┌──────────────┐  ┌────────────┐  ┌────────┐ │
│  │ Models │  │ Query Builder │  │Collections │  │ Schema │ │
│  └────────┘  └──────────────┘  └────────────┘  └────────┘ │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                  Infrastructure Layer                        │
│  ┌──────────┐  ┌────────┐  ┌────────────────┐  ┌─────────┐│
│  │WordPress │  │ MySQL  │  │Action Scheduler│  │ WP Cron ││
│  └──────────┘  └────────┘  └────────────────┘  └─────────┘│
└─────────────────────────────────────────────────────────────┘
```

---

## Core Components

### 1. Framework Core (`includes/framework/`)

The framework layer provides foundational services:

#### Zaplane Core
```php
namespace Zaplane\Framework\Core;

class Zaplane {
    // Singleton instance
    private static ?self $instance = null;

    // Service container
    public Container $container;

    // Boot application
    public function init_plugin(): void;
}
```

**Responsibilities:**
- Bootstrap plugin
- Manage service container
- Load framework components
- Handle WordPress hooks

#### Container
```php
namespace Zaplane\Framework\Classes;

class Container {
    // Service registry
    protected array $services = [];

    // Register service
    public function set(string $name, callable $factory);

    // Resolve service
    public function get(string $name);
}
```

**Responsibilities:**
- Dependency injection
- Service location
- Lazy loading

---

### 2. ORM System (`includes/framework/database/orm/`)

Custom ORM inspired by Laravel Eloquent:

#### Model
```php
namespace Zaplane\Framework\Database\ORM;

abstract class Model {
    // Table name
    protected static string $table = '';

    // Query builder
    public static function query(): QueryBuilder;

    // Find by ID
    public static function find(int $id): ?self;

    // Get all records
    public static function all(): Collection;
}
```

#### Query Builder
```php
class QueryBuilder {
    // WHERE clause
    public function where(string $column, $value): self;

    // ORDER BY clause
    public function orderBy(string $column): self;

    // Execute query
    public function get(): Collection;
}
```

#### Collection
```php
class Collection implements ArrayAccess, Iterator {
    // Collection methods
    public function map(callable $callback): self;
    public function filter(callable $callback): self;
    public function pluck(string $key): array;
}
```

[Read more about the ORM →](orm.md)

---

### 3. Configuration System (`includes/framework/config/`)

Auto-loading configuration with dot notation:

```php
namespace Zaplane\Framework\Config;

class Config {
    // Singleton instance
    private static ?self $instance = null;

    // Config data
    protected array $items = [];

    // Get value with dot notation
    public function get(string $key, $default = null);

    // Set value
    public function set(string $key, $value): self;

    // Load from file
    public function loadFile(string $path, ?string $namespace = null): self;

    // Load from directory
    public function loadDirectory(string $directory): self;
}
```

**Features:**
- Auto-loading from `includes/config/`
- Dot notation access (`app.debug`)
- Environment-aware
- Cached for performance

[Read more about Config →](config.md)

---

### 4. Logging System (`includes/framework/logging/`)

Comprehensive logging framework:

```php
namespace Zaplane\Framework\Logging;

class Logger {
    // Log levels
    public function debug(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;

    // Log exception
    public function exception(\Throwable $exception): void;
}
```

**Features:**
- Multiple log levels
- Contextual logging
- Channel support
- File and database handlers
- Log rotation

[Read more about Logging →](logging.md)

---

### 5. Integration System (`includes/framework/core/integration-loader.php`)

Extensible integration architecture:

```php
namespace Zaplane\Framework\Core;

class IntegrationLoader {
    // Get integration instance
    public static function get(string $slug): ?object;

    // Get all integrations
    public static function all(): array;

    // Get registry metadata
    public static function getRegistry(): array;
}
```

```php
namespace Zaplane\Framework\Classes;

abstract class IntegrationBase {
    // Integration identity
    abstract public static function get_slug(): string;

    // Available triggers
    public static function get_triggers(): array;

    // Available actions
    public static function get_actions(): array;

    // Execute node
    public static function execute_node(array $node, array $input): array;

    // Resolve trigger
    public static function resolve_trigger(array $node, array $hook_args);
}
```

[Read more about Integrations →](integrations.md)

---

### 6. Workflow Engine (`includes/framework/core/automation.php`)

Automation execution system:

```php
namespace Zaplane\Framework\Core;

class Automation {
    // Boot automation system
    public function boot(): void;

    // Register triggers
    public function dispatch_active_triggers(): void;

    // Route trigger events
    public function trigger_router(): void;

    // Execute node run
    public function dispatch_node_run(int $node_run_id): void;
}
```

**Flow:**
1. Register WordPress hooks for active workflows
2. Listen for trigger events
3. Create Run record
4. Queue NodeRun executions (via Action Scheduler)
5. Execute nodes asynchronously
6. Track execution edges
7. Spawn child nodes
8. Finalize run

[Read more about Workflow Engine →](workflow-engine.md)

---

## Data Models

### Workflow
```php
Workflow {
    id: integer
    user_id: integer
    title: string
    name: string
    status: enum('active', 'paused', 'draft')
    created_at: datetime
    updated_at: datetime
}
```

### WorkflowVersion
```php
WorkflowVersion {
    id: integer
    workflow_id: integer
    graph_json: longtext (JSON)
    graph_hash: char(64)
    is_active: boolean
    created_at: datetime
}
```

### Run
```php
Run {
    id: integer
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

### NodeRun
```php
NodeRun {
    id: integer
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

[Read more about Database Schema →](database.md)

---

## Design Patterns

### Singleton Pattern
Used for core services (Config, Logger, Zaplane):

```php
class Config {
    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Private constructor
    }
}
```

### Repository Pattern
Models act as repositories:

```php
// Model is repository
$workflows = Workflow::where('status', 'active')->get();

// Instead of separate repository class
$workflows = $workflowRepository->findByStatus('active');
```

### Factory Pattern
Container acts as service factory:

```php
$container->set('integrations', fn($c) => IntegrationLoader::init($c));
$integrations = $container->get('integrations');
```

### Builder Pattern
Query Builder uses method chaining:

```php
$workflows = Workflow::where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

---

## Performance Optimizations

### 1. Query Result Caching
```php
// First call - hits database
$workflows = Workflow::all();

// Second call - served from cache
$workflows2 = Workflow::all(); // Instant!
```

### 2. Lazy Loading
```php
// Only loads metadata, not instances
$registry = IntegrationLoader::getRegistry(); // Fast!

// Load instance on-demand
$slack = IntegrationLoader::get('slack'); // Only when needed
```

### 3. Flexible Timestamps
```php
// Automatically uses correct column
class Run extends Model {
    protected static string $createdAt = 'started_at';
}

$runs = Run::latest(); // Uses 'started_at' automatically
```

[Read more about Performance →](../performance/optimization.md)

---

## Request Flow Example

### Creating a Workflow

```
User Action
    ↓
POST /wp-json/zaplane/v1/workflows
    ↓
WorkflowsController::create_item()
    ↓
Workflow::create([...])
    ↓
QueryBuilder->insert()
    ↓
$wpdb->insert()
    ↓
MySQL INSERT
    ↓
Return Workflow model
    ↓
JSON response to user
```

### Executing a Workflow

```
WordPress Hook (e.g., publish_post)
    ↓
Automation::trigger_router()
    ↓
Find workflows for event
    ↓
Integration::resolve_trigger()
    ↓
Create Run record
    ↓
Queue NodeRun (Action Scheduler)
    ↓
[Async execution]
    ↓
Automation::dispatch_node_run()
    ↓
Load graph & node data
    ↓
Integration::execute_node()
    ↓
Update NodeRun output
    ↓
Spawn child nodes
    ↓
Repeat until complete
    ↓
Finalize Run
```

---

## Directory Structure

```
zaplane/
├── assets/                    # Frontend assets
│   ├── css/
│   ├── js/
│   └── react/                # React UI
├── includes/
│   ├── framework/            # Framework layer
│   │   ├── core/            # Core services
│   │   ├── database/orm/    # ORM system
│   │   ├── config/          # Config management
│   │   ├── logging/         # Logging system
│   │   ├── classes/         # Utility classes
│   │   └── exceptions/      # Exception classes
│   ├── models/              # Data models
│   ├── config/              # Config files
│   ├── database/migrations/ # Database migrations
│   └── api/                 # REST API controllers
├── integration/             # Integration classes
├── tests/                   # Automated tests
├── vendor/                  # Composer dependencies
└── docs/                    # Documentation
```

---

## Next Steps

- [Database Schema](database.md) - Learn about the database structure
- [ORM System](orm.md) - Master the ORM
- [Config System](config.md) - Configure the plugin
- [Integration System](integrations.md) - Build custom integrations
- [Workflow Engine](workflow-engine.md) - Understand workflow execution

---

**Need help?** Check the [API Reference](../api/models.md) or [join discussions](https://github.com/yourusername/zaplane/discussions).
