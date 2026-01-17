# Zaplane Plugin Structure

## Overview

Zaplane follows a **Laravel-inspired** architecture with clear separation between **framework code** (core system) and **application code** (where developers work). The structure is optimized for building workflow automation plugins similar to n8n, Zapier, or Bitflow.

---

## Directory Structure

```
zaplane/
├── includes/
│   ├── framework/              # Core framework (like vendor/ - rarely modified)
│   │   ├── classes/           # Core utility classes
│   │   ├── config/            # Configuration system classes
│   │   ├── console/           # CLI framework
│   │   ├── core/              # Bootstrap and core services
│   │   ├── database/          # ORM system
│   │   │   └── orm/          # QueryBuilder, Model, Schema, etc.
│   │   ├── exceptions/        # Framework exceptions
│   │   ├── logging/           # Logging system
│   │   │   └── handlers/     # Log handlers (File, Database, etc.)
│   │   └── models/           # Built-in framework models
│   │       └── wordpress/    # WordPress core models (Post, User, etc.)
│   │
│   ├── admin/                 # Admin module (menu, assets)
│   ├── api/                   # REST API controllers
│   ├── ajax/                  # AJAX handlers
│   ├── commands/              # Custom WP-CLI commands
│   ├── config/                # Application configuration files
│   │   ├── app.php
│   │   ├── database.php
│   │   └── logging.php
│   ├── database/
│   │   └── migrations/       # Database migrations
│   ├── models/                # Application models
│   ├── admin.php              # Admin module loader
│   ├── api.php                # API module loader
│   ├── ajax.php               # Ajax module loader
│   ├── autoload.php           # PSR-4 autoloader
│   ├── database.php           # Database utilities
│   ├── helpers.php            # Helper functions
│   └── installer.php          # Plugin installer
│
├── integration/               # Integrations (WordPress, Slack, etc.)
├── assets/                    # Frontend assets (CSS, JS, images)
├── tests/                     # PHPUnit tests
├── zaplane.php                # Main plugin file
├── STRUCTURE.md               # This file
└── FRAMEWORK.md               # Complete framework documentation
```

---

## Namespace Structure

### Framework Namespaces (Core System - Rarely Modified)

These are the building blocks of the framework:

```php
Zaplane\Framework\Classes\*          # includes/framework/classes/
  ├── Container                      # Dependency injection container
  ├── IntegrationBase                # Base class for all integrations
  ├── AbstractRequestHandler         # Base for AJAX/POST handlers
  ├── AbstractAjaxHandler            # Base for AJAX endpoints
  ├── ConnectionManager              # API credential management
  ├── OAuthHandler                   # OAuth2 flow handling
  ├── Encryption                     # Credential encryption
  └── Helper                         # Utility functions

Zaplane\Framework\Config\*           # includes/framework/config/
  ├── Config                         # Configuration manager
  └── Repository                     # Scoped configuration

Zaplane\Framework\Console\*          # includes/framework/console/
  ├── Command                        # Base command class
  ├── Kernel                         # Command registry
  └── Commands\*                     # Built-in commands

Zaplane\Framework\Core\*             # includes/framework/core/
  ├── Zaplane                        # Main bootstrap class
  ├── ModuleInterface                # Module contract
  ├── ModuleManager                  # Module loader
  ├── Automation                     # Workflow execution engine
  ├── IntegrationLoader              # Integration lazy-loader
  └── IntegrationRegistry            # Integration directory

Zaplane\Framework\Database\ORM\*     # includes/framework/database/orm/
  ├── Model                          # ActiveRecord base class
  ├── QueryBuilder                   # Fluent SQL builder
  ├── Schema                         # Table management
  ├── Blueprint                      # Table definition
  ├── Migration                      # Migration base class
  ├── Collection                     # Result collection
  ├── ColumnDefinition               # Column builder
  └── DB                             # Database facade

Zaplane\Framework\Exceptions\*       # includes/framework/exceptions/
  ├── ZaplaneException               # Base exception
  ├── DatabaseException              # Database errors
  ├── IntegrationException           # Integration errors
  ├── ConnectionException            # Connection errors
  └── ValidationException            # Validation errors

Zaplane\Framework\Logging\*          # includes/framework/logging/
  ├── Logger                         # Main logger
  ├── LogManager                     # Multi-channel manager
  ├── LogLevel                       # PSR-3 log levels
  ├── LogEntry                       # Log entry object
  └── Handlers\*                     # Log handlers

Zaplane\Framework\Models\WordPress\* # includes/framework/models/wordpress/
  ├── WPModel                        # WordPress model base
  ├── Post                           # Post model
  ├── User                           # User model
  ├── Comment                        # Comment model
  ├── Term                           # Term model
  ├── TermTaxonomy                   # Taxonomy model
  └── Option                         # Option model
```

### Application Namespaces (Developer Zone - Frequently Modified)

This is where you build your application:

```php
Zaplane\Models\*                     # includes/models/
  ├── Workflow                       # Workflow model
  ├── WorkflowVersion                # Workflow version snapshots
  ├── Run                            # Workflow execution
  ├── NodeRun                        # Node execution
  ├── ExecutionEdge                  # Data flow tracking
  ├── NodeLog                        # Node execution logs
  ├── QueueJob                       # Background jobs
  └── Connection                     # API credentials

Zaplane\Commands\*                   # includes/commands/
  └── DemoCommand                    # Example custom command

Zaplane\API\*                        # includes/api/
  ├── IntegrationsController         # Integration endpoints
  ├── WorkflowsController            # Workflow CRUD
  ├── RunController                  # Execution details
  └── ConnectionsController          # Connection management

Zaplane\Ajax\*                       # includes/ajax/
  └── Workflows                      # Workflow AJAX handlers

Zaplane\Admin\*                      # includes/admin/
  ├── Menu                           # Admin menu
  └── Assets                         # Admin assets

Zaplane\Database\Migrations\*        # includes/database/migrations/
  └── [timestamp]_[name].php         # Migration files

Zaplane\Integration\*                # integration/
  ├── WordPress                      # WordPress integration
  ├── Slack                          # Slack integration
  ├── Woo                            # WooCommerce integration
  ├── Condition                      # Conditional logic
  ├── Delay                          # Delay execution
  └── ...                            # More integrations

Zaplane\Admin                        # includes/admin.php
Zaplane\API                          # includes/api.php
Zaplane\Ajax                         # includes/ajax.php
```

---

## Laravel-Like Features

### 1. **Config System**

Laravel-style configuration with dot notation:

```php
// Get config value
$appName = config('app.name');
$debug = config('app.debug', false);

// Set config value
config()->set('custom.key', 'value');
```

**Configuration Files:**
- `includes/config/app.php` - Application settings
- `includes/config/database.php` - Database tables and settings
- `includes/config/logging.php` - Logging channels

### 2. **ORM System**

ActiveRecord-style models:

```php
// Query
$workflows = Workflow::where('status', 'active')->get();

// Create
$workflow = Workflow::create(['title' => 'My Workflow']);

// Update
$workflow->status = 'paused';
$workflow->save();

// Delete
$workflow->delete();

// Collections
$active = $workflows->filter(fn($w) => $w->is_active);
$total = $workflows->sum('views');
```

### 3. **Migrations**

Database version control:

```bash
wp zaplane make:migration create_products_table
wp zaplane migrate run
wp zaplane migrate rollback
```

### 4. **Custom Commands**

Auto-discovered WP-CLI commands:

```php
// includes/commands/my-command.php
class MyCommand extends Command
{
    protected string $signature = 'my-command';

    public function handle(array $args, array $assoc_args): void
    {
        $this->info('Hello World!');
    }
}
```

```bash
wp zaplane my-command
```

### 5. **Service Container**

Dependency injection:

```php
$container = Zaplane::init()->container;

// Get services
$integrations = $container->get('integrations');
$automation = $container->get('automation');

// Register custom service
$container->set('my_service', fn($c) => new MyService());
```

### 6. **Logging System**

PSR-3 compliant multi-channel logging:

```php
zaplane_log_info('User registered', ['user_id' => 123]);
zaplane_log_error('Payment failed', ['order_id' => 456]);
zaplane_log_exception($exception);

// Channel-specific
$fileLogger = zaplane_log_channel('file');
$dbLogger = zaplane_log_channel('database');
```

---

## Development Workflow

### Where to Work

Developers should primarily work in:

- `includes/models/` - Your application models
- `includes/api/` - REST API controllers
- `includes/ajax/` - AJAX handlers
- `includes/commands/` - Custom CLI commands
- `includes/config/` - Configuration files
- `includes/database/migrations/` - Database changes
- `integration/` - Custom integrations

### Framework Code

The `includes/framework/` directory contains core system code and should rarely need modification. Think of it like `vendor/` in a Composer project.

---

## Integration System

### Creating an Integration

1. Create file in `integration/my-integration.php`
2. Extend `Zaplane\Framework\Classes\IntegrationBase`
3. Implement required methods:
   - `get_slug()` - Unique identifier
   - `get_triggers()` - Available triggers
   - `get_actions()` - Available actions
   - `execute_node()` - Execute actions

4. Register in `includes/framework/core/integration-registry.php`

**Example:**

```php
namespace Zaplane\Integration;

use Zaplane\Framework\Classes\IntegrationBase;

class Slack extends IntegrationBase
{
    public function get_slug(): string {
        return 'slack';
    }

    public function get_triggers(): array {
        return [
            'message_received' => ['label' => 'New Message', 'hook' => 'slack_message'],
        ];
    }

    public function get_actions(): array {
        return [
            'send_message' => ['label' => 'Send Message'],
        ];
    }

    public function execute_node(array $node, array $input): array {
        // Execute action
        return ['port' => 'main', 'data' => $result];
    }
}
```

---

## Module System

### Built-in Modules

1. **Admin Module** (`includes/admin.php`)
   - WordPress admin menu
   - Admin assets (CSS, JS)
   - Settings pages

2. **API Module** (`includes/api.php`)
   - REST API endpoints
   - Loads all controllers from `includes/api/`

3. **Ajax Module** (`includes/ajax.php`)
   - AJAX request handlers
   - Loads all handlers from `includes/ajax/`

### Module Structure

All modules implement `Zaplane\Framework\Core\ModuleInterface`:

```php
namespace Zaplane;

use Zaplane\Framework\Core\ModuleInterface;
use Zaplane\Framework\Classes\Container;

class MyModule implements ModuleInterface
{
    public static function init(Container $container): self
    {
        return new self($container);
    }

    public function register_hooks(): void
    {
        // Register WordPress hooks
    }
}
```

---

## Workflow Execution

### Workflow Structure

Workflows are stored as **graphs** with nodes and edges:

```json
{
  "nodes": [
    {
      "id": "trigger-1",
      "type": "trigger",
      "data": { "app": "wordpress", "event": "publish_post" }
    },
    {
      "id": "action-1",
      "type": "action",
      "data": { "app": "slack", "action": "send_message" }
    }
  ],
  "edges": [
    {
      "id": "edge-1",
      "source": "trigger-1",
      "target": "action-1"
    }
  ]
}
```

### Execution Flow

1. **Trigger** - WordPress hook fires (`publish_post`)
2. **Automation** - Detects workflows with this trigger
3. **Run** - Creates `Run` record
4. **Queue** - Queues first node via Action Scheduler
5. **Execute** - Calls integration's `execute_node()`
6. **Spawn** - Queues child nodes based on edges
7. **Complete** - Marks run as completed/failed

---

## Testing

### Running Tests

```bash
./vendor/bin/phpunit                 # Run all tests
./vendor/bin/phpunit --filter Model  # Run specific tests
```

### Test Structure

```
tests/
├── Unit/
│   ├── ModelTest.php
│   ├── QueryBuilderTest.php
│   ├── CollectionTest.php
│   ├── ConfigTest.php
│   └── LoggerTest.php
├── API/
│   └── WorkflowsControllerTest.php
├── TestCase.php
└── WPMocks.php
```

---

## Helper Functions

### Config
```php
config('app.name')                   # Get config
config('app.debug', false)           # With default
```

### Logging
```php
zaplane_log_info($message, $context)
zaplane_log_error($message, $context)
zaplane_log_debug($message, $context)
zaplane_log_exception($exception)
zaplane_log_channel('file')
```

### Environment
```php
zaplane_env()                        # Get environment
zaplane_is_debug()                   # Check debug mode
```

---

## Best Practices

1. **Use the Framework** - Leverage framework classes instead of reinventing
2. **Follow Namespaces** - Keep application code in application namespaces
3. **Use Config** - Store settings in config files, not hardcoded
4. **Log Appropriately** - Use logging for debugging and monitoring
5. **Type Hint** - Use PHP type hints for better IDE support
6. **Test Your Code** - Write unit tests for critical functionality
7. **Document APIs** - Add docblocks to public methods
8. **Security First** - Validate input, escape output, check permissions

---

## Comparison with Laravel

| Feature | Laravel | Zaplane |
|---------|---------|---------|
| ORM | Eloquent | Similar (Model, QueryBuilder) |
| Migrations | ✅ | ✅ |
| Config | ✅ | ✅ (dot notation) |
| Logging | ✅ | ✅ (PSR-3 compliant) |
| Console | Artisan | WP-CLI integration |
| Container | ✅ | ✅ (simplified) |
| Routing | ✅ | WordPress REST API |
| Blade Templates | ✅ | ❌ (use WordPress templates) |
| Queue | ✅ | Action Scheduler |

---

## Getting Started

1. **Read** `FRAMEWORK.md` for complete documentation
2. **Explore** existing integrations in `integration/`
3. **Create** your first integration
4. **Build** REST API endpoints in `includes/api/`
5. **Add** custom commands in `includes/commands/`
6. **Test** your code with PHPUnit

---

## Support

- **Documentation**: `FRAMEWORK.md`
- **Examples**: Check `integration/` directory
- **Tests**: See `tests/` for examples

Happy coding! 🚀
