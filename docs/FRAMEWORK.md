# Zaplane Framework Documentation

Complete guide to building workflow automation plugins with the Zaplane framework.

## Table of Contents

1. [Framework Overview](#framework-overview)
2. [ORM System](#orm-system)
3. [Creating Integrations](#creating-integrations)
4. [Console Commands](#console-commands)
5. [Configuration System](#configuration-system)
6. [Logging System](#logging-system)
7. [REST API Development](#rest-api-development)
8. [AJAX Handlers](#ajax-handlers)
9. [Service Container](#service-container)
10. [Workflow & Automation](#workflow--automation)
11. [Best Practices](#best-practices)

---

## Framework Overview

Zaplane is a Laravel-inspired WordPress framework for building visual workflow automation (like n8n, Zapier, or Bitflow). It provides:

- **ORM**: ActiveRecord-style models with fluent query builder
- **Integration System**: Plugin architecture for triggers and actions
- **CLI**: WP-CLI commands for migrations, code generation
- **Service Container**: Dependency injection for clean architecture
- **Config**: Laravel-like configuration with dot notation
- **Logging**: PSR-3 compliant multi-channel logging
- **API/AJAX**: Base classes for REST and AJAX endpoints

### Architecture

```
Framework (includes/framework/)
  ├── Database/ORM      → Models, QueryBuilder, Schema
  ├── Console           → CLI commands
  ├── Config            → Configuration management
  ├── Logging           → Multi-channel logging
  ├── Classes           → Utilities (Container, IntegrationBase, etc.)
  └── Core              → Bootstrap, ModuleManager, Automation

Application (includes/)
  ├── models/           → Your workflow models
  ├── api/              → REST API controllers
  ├── ajax/             → AJAX handlers
  ├── commands/         → Custom CLI commands
  ├── config/           → Configuration files
  └── database/         → Migrations
```

---

## ORM System

### Creating a Model

```php
<?php
namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

class Product extends Model
{
    // Table name (auto-detected as wp_zaplane_products if not set)
    protected static string $table = 'products';

    // Primary key
    protected static string $primaryKey = 'id';

    // Enable timestamps
    protected bool $timestamps = true;

    // Mass-assignable fields
    protected array $fillable = ['name', 'price', 'description', 'category_id'];

    // Hidden fields (excluded from toArray/toJson)
    protected array $hidden = ['internal_notes'];

    // Type casting
    protected array $casts = [
        'price' => 'float',
        'is_active' => 'bool',
        'metadata' => 'json',
        'published_at' => 'datetime',
    ];
}
```

### Querying Data

```php
// Find by ID
$product = Product::find(1);
$product = Product::findOrFail(1); // throws exception if not found

// Get all
$products = Product::all();

// Where clauses
$products = Product::where('price', '>', 100)->get();
$products = Product::whereIn('category_id', [1, 2, 3])->get();
$products = Product::whereBetween('price', [10, 50])->get();

// Ordering
$products = Product::orderBy('price', 'desc')->get();
$products = Product::latest()->get(); // order by created_at desc

// Limiting
$products = Product::limit(10)->get();
$products = Product::forPage(2, 20)->get(); // pagination

// Aggregates
$count = Product::count();
$total = Product::sum('price');
$avg = Product::avg('price');
```

### Creating & Updating

```php
// Create
$product = Product::create([
    'name' => 'Widget',
    'price' => 29.99,
]);

// Find or create
$product = Product::firstOrCreate(
    ['sku' => 'WDG-001'],
    ['name' => 'Widget', 'price' => 29.99]
);

// Update or create
$product = Product::updateOrCreate(
    ['sku' => 'WDG-001'],
    ['name' => 'New Widget', 'price' => 34.99]
);

// Update via model
$product->price = 39.99;
$product->save();

// Bulk update
Product::where('category_id', 5)->update(['is_active' => false]);

// Delete
$product->delete();
Product::where('price', '<', 10)->delete();
```

### Collections

All `get()` queries return a `Collection` with 80+ methods:

```php
$products = Product::all();

// Transformation
$names = $products->pluck('name');
$expensive = $products->filter(fn($p) => $p->price > 100);
$grouped = $products->groupBy('category_id');

// Aggregation
$total = $products->sum('price');
$average = $products->avg('price');

// Utilities
$first10 = $products->take(10);
$chunks = $products->chunk(25);
$unique = $products->unique('sku');
```

### Migrations

Create a migration:

```bash
wp zaplane make:migration create_products_table
```

This creates `includes/database/migrations/YYYY_MM_DD_HHMMSS_create_products_table.php`:

```php
<?php
namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('category_id')
                  ->references('id')
                  ->on('categories')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::drop('products');
    }
}
```

Run migrations:

```bash
wp zaplane migrate run        # Run pending migrations
wp zaplane migrate rollback   # Rollback last batch
wp zaplane migrate fresh      # Drop all tables and re-run
wp zaplane migrate status     # Show migration status
```

### Available Column Types

```php
// Numeric
$table->id();                          // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY
$table->bigInteger('column');
$table->integer('column');
$table->tinyInteger('column');
$table->decimal('price', 10, 2);
$table->float('amount');

// String
$table->string('name', 255);
$table->text('description');
$table->mediumText('content');
$table->longText('data');

// Dates
$table->datetime('published_at');
$table->timestamp('created_at');
$table->timestamps();                  // created_at + updated_at

// Special
$table->boolean('is_active');
$table->json('metadata');
$table->enum('status', ['pending', 'approved', 'rejected']);

// Column Modifiers
$table->string('email')->nullable();
$table->integer('views')->default(0);
$table->string('slug')->unique();
$table->timestamp('created_at')->useCurrent();
```

---

## Creating Integrations

Integrations are the building blocks of workflows. Each integration provides **triggers** (events that start workflows) and **actions** (operations workflows can perform).

### Integration Structure

```php
<?php
namespace Zaplane\Integration;

use Zaplane\Framework\Classes\IntegrationBase;

class Slack extends IntegrationBase
{
    public function get_slug(): string {
        return 'slack';
    }

    public function get_name(): string {
        return 'Slack';
    }

    public function get_icon(): string {
        return ZAPLANE_PLUGIN_ROOT_URI . 'assets/icons/slack.svg';
    }

    public function get_category(): string {
        return 'app'; // or 'tool'
    }
}
```

### Defining Triggers

Triggers watch for WordPress events and start workflows:

```php
public function get_triggers(): array {
    return [
        'message_received' => [
            'label' => 'New Message Received',
            'hook' => 'slack_message_received', // WordPress action hook
        ],
    ];
}

public function resolve_trigger(array $node, array $hook_args): array {
    // Convert WordPress hook arguments into workflow data
    [$message] = $hook_args;

    return [
        'channel' => $message['channel'],
        'user' => $message['user'],
        'text' => $message['text'],
        'timestamp' => $message['ts'],
    ];
}
```

### Defining Actions

Actions are operations workflows can perform:

```php
public function get_actions(): array {
    return [
        'send_message' => [
            'label' => 'Send Message',
        ],
        'create_channel' => [
            'label' => 'Create Channel',
        ],
    ];
}

public function execute_node(array $node, array $input): array {
    $action = $node['data']['action'] ?? null;

    switch ($action) {
        case 'send_message':
            return $this->send_message($node, $input);
        case 'create_channel':
            return $this->create_channel($node, $input);
        default:
            throw new \Exception("Unknown action: {$action}");
    }
}

protected function send_message(array $node, array $input): array {
    $config = $node['data']['config'] ?? [];
    $channel = $config['channel'] ?? '';
    $text = $config['message'] ?? '';

    // Replace {{variables}} with data from input
    $text = $this->interpolate($text, $input);

    // Call Slack API
    $response = wp_remote_post('https://slack.com/api/chat.postMessage', [
        'headers' => [
            'Authorization' => 'Bearer ' . $this->get_access_token(),
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'channel' => $channel,
            'text' => $text,
        ]),
    ]);

    if (is_wp_error($response)) {
        throw new \Exception($response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return [
        'port' => 'main',
        'data' => [
            'message_id' => $body['ts'],
            'channel' => $body['channel'],
        ],
    ];
}
```

### Configuration Schema

Define UI fields for node configuration:

```php
public function get_action_config_schema(string $action): array {
    switch ($action) {
        case 'send_message':
            return [
                [
                    'name' => 'channel',
                    'label' => 'Channel',
                    'type' => 'select',
                    'required' => true,
                    'dynamic' => 'channels', // Load from get_dynamic_fields()
                ],
                [
                    'name' => 'message',
                    'label' => 'Message',
                    'type' => 'textarea',
                    'required' => true,
                    'placeholder' => 'Enter message text',
                    'help' => 'Use {{variable}} for dynamic content',
                ],
            ];
        default:
            return [];
    }
}

public function get_dynamic_fields(): array {
    return [
        'channels' => function($credentials) {
            // Fetch channels from Slack API
            $response = wp_remote_get('https://slack.com/api/conversations.list', [
                'headers' => ['Authorization' => 'Bearer ' . $credentials['access_token']],
            ]);

            $body = json_decode(wp_remote_retrieve_body($response), true);

            return array_map(function($channel) {
                return [
                    'value' => $channel['id'],
                    'label' => '#' . $channel['name'],
                ];
            }, $body['channels']);
        },
    ];
}
```

### Authentication

For integrations requiring API credentials:

```php
public function requires_connection(): bool {
    return true;
}

public function get_auth_type(): string {
    return 'oauth2'; // or 'api_key', 'basic', 'none', 'both'
}

// For OAuth2
public function get_oauth_auth_url(string $redirect_uri, string $state, array $credentials): string {
    $client_id = $credentials['client_id'] ?? '';
    $scopes = implode(',', $this->get_oauth_scopes());

    return "https://slack.com/oauth/v2/authorize?" . http_build_query([
        'client_id' => $client_id,
        'scope' => $scopes,
        'redirect_uri' => $redirect_uri,
        'state' => $state,
    ]);
}

public function exchange_oauth_code(string $code, string $redirect_uri, array $credentials): array {
    $response = wp_remote_post('https://slack.com/api/oauth.v2.access', [
        'body' => [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'code' => $code,
            'redirect_uri' => $redirect_uri,
        ],
    ]);

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return [
        'access_token' => $body['access_token'],
        'refresh_token' => $body['refresh_token'] ?? null,
        'expires_in' => $body['expires_in'] ?? null,
    ];
}

public function get_oauth_scopes(): array {
    return ['chat:write', 'channels:read', 'users:read'];
}

// For API Key
public function get_auth_fields(string $auth_type): array {
    return [
        [
            'name' => 'api_key',
            'label' => 'API Key',
            'type' => 'password',
            'required' => true,
        ],
    ];
}

public function test_connection(array $credentials): bool {
    $response = wp_remote_get('https://slack.com/api/auth.test', [
        'headers' => ['Authorization' => 'Bearer ' . $credentials['access_token']],
    ]);

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return $body['ok'] ?? false;
}
```

### Conditional Branching

For integrations with multiple output ports (like IF/THEN):

```php
public function get_output_ports(): array {
    return ['true', 'false'];
}

public function execute_node(array $node, array $input): array {
    $config = $node['data']['config'] ?? [];
    $expression = $config['expression'] ?? '';

    // Evaluate expression
    $result = $this->evaluate_expression($expression, $input);

    // Route to appropriate port
    return [
        'port' => $result ? 'true' : 'false',
        'data' => $input, // pass through input data
    ];
}
```

### Registering Your Integration

Add to `includes/framework/core/integration-registry.php`:

```php
public static function get_integrations(): array {
    return [
        // ... existing integrations
        'slack' => [
            'file' => ZAPLANE_INTEGRATION_DIR_PATH . 'slack.php',
            'class' => 'Zaplane\\Integration\\Slack',
        ],
    ];
}
```

---

## Console Commands

### Creating a Command

Create `includes/commands/export-workflows-command.php`:

```php
<?php
namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Models\Workflow;

class ExportWorkflowsCommand extends Command
{
    protected string $signature = 'workflows:export {--format=json}';
    protected string $description = 'Export all workflows';

    public function handle(array $args, array $assoc_args): void
    {
        $format = $assoc_args['format'] ?? 'json';

        $this->info('Exporting workflows...');

        $workflows = Workflow::all();

        if ($workflows->isEmpty()) {
            $this->warning('No workflows found.');
            return;
        }

        $data = $workflows->map(function($workflow) {
            return [
                'id' => $workflow->id,
                'title' => $workflow->title,
                'status' => $workflow->status,
            ];
        });

        if ($format === 'json') {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
        } elseif ($format === 'table') {
            $this->table(
                ['ID', 'Title', 'Status'],
                $data->map(fn($w) => [$w['id'], $w['title'], $w['status']])->toArray()
            );
        }

        $this->success("Exported {$workflows->count()} workflows");
    }
}
```

The command is **auto-discovered** and available as:

```bash
wp zaplane workflows:export
wp zaplane workflows:export --format=table
```

### Built-in Commands

```bash
# Migrations
wp zaplane migrate run
wp zaplane migrate rollback
wp zaplane migrate fresh
wp zaplane migrate status

# Code Generation
wp zaplane make:migration create_custom_table
wp zaplane make:model CustomModel

# Demo
wp zaplane demo
```

---

## Configuration System

### Using Configuration

```php
// Get config value
$appName = config('app.name');
$debug = config('app.debug', false); // with default

// Set config value
config()->set('app.custom_key', 'value');

// Check if exists
if (config()->has('logging.file.path')) {
    // ...
}
```

### Configuration Files

**includes/config/app.php:**

```php
<?php
return [
    'name' => 'Zaplane',
    'version' => ZAPLANE_VERSION,
    'debug' => defined('WP_DEBUG') && WP_DEBUG,
    'timezone' => 'UTC',
    'url' => ZAPLANE_PLUGIN_ROOT_URI,
];
```

**includes/config/logging.php:**

```php
<?php
return [
    'default' => 'file',

    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => ZAPLANE_ROOT_DIR_PATH . 'logs/zaplane.log',
            'level' => 'debug',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'zaplane_logs',
            'level' => 'info',
        ],
    ],
];
```

### Loading Custom Config

```php
// Load file
config()->loadFile('/path/to/config.php', 'myapp');

// Access
$value = config('myapp.key');
```

---

## Logging System

### Basic Logging

```php
use Zaplane\Framework\Logging\Logger;

$logger = Logger::getInstance();

// Log levels
$logger->debug('Debug message', ['user_id' => 123]);
$logger->info('User logged in', ['username' => 'john']);
$logger->warning('API rate limit approaching');
$logger->error('Payment failed', ['order_id' => 456]);
$logger->critical('Database connection lost');

// Exception logging
try {
    // code
} catch (\Exception $e) {
    $logger->exception($e, 'error', ['context' => 'data']);
}
```

### Helper Functions

```php
// Quick logging
zaplane_log_info('User registered');
zaplane_log_error('Failed to process order');
zaplane_log_debug('Cache cleared');
zaplane_log_exception($exception);

// Channel-specific logging
$fileLogger = zaplane_log_channel('file');
$dbLogger = zaplane_log_channel('database');
```

### Custom Log Handlers

```php
use Zaplane\Framework\Logging\Handlers\AbstractHandler;
use Zaplane\Framework\Logging\LogEntry;

class SlackHandler extends AbstractHandler
{
    protected string $webhookUrl;

    public function __construct(string $webhookUrl, string $minLevel = 'error')
    {
        parent::__construct($minLevel);
        $this->webhookUrl = $webhookUrl;
    }

    public function handle(LogEntry $entry): bool
    {
        if (!$this->isHandling($entry->getLevel())) {
            return false;
        }

        wp_remote_post($this->webhookUrl, [
            'body' => json_encode([
                'text' => $entry->getInterpolatedMessage(),
                'attachments' => [
                    [
                        'color' => $this->getColorForLevel($entry->getLevel()),
                        'fields' => $this->formatContext($entry->getContext()),
                    ],
                ],
            ]),
        ]);

        return true;
    }
}

// Register handler
$logger->addHandler(new SlackHandler('https://hooks.slack.com/...', 'error'));
```

---

## REST API Development

### Creating a Controller

Create `includes/api/products-controller.php`:

```php
<?php
namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use Zaplane\Models\Product;

class ProductsController extends WP_REST_Controller
{
    public function register_routes()
    {
        $namespace = 'zaplane/v1';

        register_rest_route($namespace, '/products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        register_rest_route($namespace, '/products/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
            [
                'methods' => ['PUT', 'PATCH'],
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);
    }

    public function check_permissions(): bool
    {
        return current_user_can('manage_options');
    }

    public function get_items(WP_REST_Request $request): WP_REST_Response
    {
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 20;

        $products = Product::latest()
            ->forPage($page, $per_page)
            ->get();

        return rest_ensure_response([
            'data' => $products->toArray(),
            'total' => Product::count(),
            'page' => $page,
            'per_page' => $per_page,
        ]);
    }

    public function create_item(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params();

        $product = Product::create([
            'name' => sanitize_text_field($data['name']),
            'price' => floatval($data['price']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
        ]);

        return rest_ensure_response([
            'success' => true,
            'data' => $product->toArray(),
        ], 201);
    }
}
```

### Registering Controllers

In `includes/api.php`:

```php
public function register_route()
{
    (new \Zaplane\API\ProductsController($this->container))->register_routes();
    // ... other controllers
}
```

---

## AJAX Handlers

### Creating an AJAX Handler

Create `includes/ajax/products.php`:

```php
<?php
namespace Zaplane\Ajax;

use Zaplane\Framework\Classes\AbstractAjaxHandler;
use Zaplane\Models\Product;

class Products extends AbstractAjaxHandler
{
    protected array $actions = [
        'delete_product' => [
            'callback' => [$this, 'delete_product'],
            'fields' => [
                'id' => 'absint',
            ],
            'capability' => 'manage_options',
        ],

        'search_products' => [
            'callback' => [$this, 'search_products'],
            'fields' => [
                'query' => 'text',
                'limit' => 'absint',
            ],
            'capability' => 'read',
            'allow_visitor_action' => false,
        ],
    ];

    public function register(): void
    {
        $this->dispatch_actions();
    }

    protected function delete_product(array $data): array
    {
        $product = Product::find($data['id']);

        if (!$product) {
            throw new \Exception('Product not found');
        }

        $product->delete();

        return [
            'message' => 'Product deleted successfully',
        ];
    }

    protected function search_products(array $data): array
    {
        $query = $data['query'];
        $limit = $data['limit'] ?? 10;

        $products = Product::where('name', 'LIKE', "%{$query}%")
            ->limit($limit)
            ->get();

        return [
            'products' => $products->toArray(),
        ];
    }
}
```

### Registering AJAX Handler

In `includes/ajax.php`:

```php
public function register_hooks(): void
{
    $products = new \Zaplane\Ajax\Products();
    $products->register();
}
```

### Frontend JavaScript

```javascript
// Delete product
jQuery.post(ajaxurl, {
    action: 'zaplane_delete_product',
    nonce: zaplane_nonce,
    id: 123
}, function(response) {
    if (response.success) {
        console.log(response.data.message);
    }
});

// Search products
jQuery.post(ajaxurl, {
    action: 'zaplane_search_products',
    nonce: zaplane_nonce,
    query: 'widget',
    limit: 5
}, function(response) {
    if (response.success) {
        console.log(response.data.products);
    }
});
```

---

## Service Container

### Using the Container

```php
// Get container instance
$container = \Zaplane\Framework\Core\Zaplane::init()->container;

// Get service
$integrations = $container->get('integrations');
$automation = $container->get('automation');
$connections = $container->get('connections');

// Register custom service
$container->set('my_service', function($c) {
    return new MyService($c->get('integrations'));
});

// Use custom service
$myService = $container->get('my_service');
```

### Available Services

- `integrations`: Integration loader
- `modules`: Module manager
- `automation`: Workflow automation engine
- `connections`: API connection manager
- `oauth`: OAuth handler

---

## Workflow & Automation

### Workflow Structure

A workflow is a **graph** of nodes connected by edges:

```json
{
  "nodes": [
    {
      "id": "trigger-1",
      "type": "trigger",
      "data": {
        "app": "wordpress",
        "event": "publish_post"
      }
    },
    {
      "id": "action-1",
      "type": "action",
      "data": {
        "app": "slack",
        "action": "send_message",
        "config": {
          "channel": "#general",
          "message": "New post: {{title}}"
        }
      }
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

1. **Trigger**: WordPress fires `publish_post` hook
2. **Automation**: Detects workflow has trigger for this hook
3. **Run Created**: Creates Run record
4. **Node Queued**: Trigger node queued for async execution
5. **Node Executed**: Integration's `execute_node()` called
6. **Children Spawned**: Following nodes queued based on edges
7. **Completion**: All nodes executed, run marked complete

### Accessing Workflow Data

```php
use Zaplane\Models\Workflow;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;

// Get all active workflows
$workflows = Workflow::active()->get();

// Get workflow runs
$workflow = Workflow::find(1);
$runs = $workflow->runs()->latest()->get();

// Get run details
$run = Run::find(1);
$nodeRuns = $run->nodeRuns()->get();

// Check run status
if ($run->isCompleted()) {
    echo "Success!";
} elseif ($run->isFailed()) {
    echo "Error: " . $run->last_error;
}
```

---

## Best Practices

### 1. Use Type Casting in Models

```php
protected array $casts = [
    'price' => 'float',
    'is_active' => 'bool',
    'settings' => 'json',
    'published_at' => 'datetime',
];
```

### 2. Use Fillable/Guarded for Security

```php
// Whitelist approach (recommended)
protected array $fillable = ['name', 'email', 'bio'];

// Blacklist approach
protected array $guarded = ['id', 'user_id', 'created_at'];
```

### 3. Use Query Scopes

```php
class Product extends Model
{
    public static function active()
    {
        return static::query()->where('is_active', true);
    }

    public static function inCategory(int $categoryId)
    {
        return static::query()->where('category_id', $categoryId);
    }
}

// Usage
$products = Product::active()->inCategory(5)->get();
```

### 4. Handle Errors Gracefully

```php
public function execute_node(array $node, array $input): array
{
    try {
        $result = $this->callExternalAPI();

        return ['port' => 'main', 'data' => $result];
    } catch (\Exception $e) {
        zaplane_log_error('API call failed', [
            'integration' => $this->get_slug(),
            'error' => $e->getMessage(),
        ]);

        throw new \Zaplane\Framework\Exceptions\IntegrationException(
            'Failed to execute action: ' . $e->getMessage()
        );
    }
}
```

### 5. Use Config Instead of Hardcoding

```php
// Bad
$apiUrl = 'https://api.example.com';

// Good
$apiUrl = config('integrations.slack.api_url');
```

### 6. Log Important Events

```php
zaplane_log_info('Workflow started', ['workflow_id' => $workflow->id]);
zaplane_log_error('Node execution failed', ['node_id' => $node['id']]);
```

### 7. Use Collections for Data Manipulation

```php
// Instead of loops
$total = 0;
foreach ($products as $product) {
    $total += $product->price;
}

// Use collection methods
$total = $products->sum('price');
```

### 8. Validate Input in Integrations

```php
public function execute_node(array $node, array $input): array
{
    $config = $node['data']['config'] ?? [];

    if (empty($config['channel'])) {
        throw new \Exception('Channel is required');
    }

    // ... execute
}
```

---

## Architecture for n8n/Bitflow-like Plugin

The Zaplane framework is designed to support visual workflow builders similar to n8n and Bitflow. Here's what you need:

### ✅ Already Implemented

1. **Graph-based Workflow Engine** - Nodes and edges stored in `workflow_versions` table
2. **Async Execution** - Uses Action Scheduler for background processing
3. **Integration System** - Plug-and-play integrations with triggers and actions
4. **OAuth Support** - Built-in OAuth2 flow handling
5. **Connection Management** - Secure credential storage
6. **Execution Tracking** - Runs, NodeRuns, ExecutionEdges for debugging
7. **Branching** - Multiple output ports (true/false, success/error)
8. **Data Transformation** - Expression evaluation with variable interpolation
9. **Error Handling** - Try-catch patterns, error logging, retry support

### 🔧 Implementation Checklist

1. **Frontend UI** (React/Vue):
   - Visual node editor (use React Flow or similar)
   - Drag-and-drop canvas
   - Node configuration forms
   - Real-time execution preview

2. **Additional Integrations**:
   - HTTP Request (✅ already implemented)
   - Webhooks (✅ already implemented)
   - Database queries
   - Email (SMTP/Gmail)
   - File operations
   - Spreadsheets (Google Sheets, Excel)

3. **Advanced Features**:
   - Loop nodes (iterate over arrays)
   - Merge nodes (wait for multiple branches)
   - Switch/Router nodes (multi-way branching)
   - Schedule triggers (cron)
   - Manual triggers (button click)

4. **Developer Experience**:
   - ✅ CLI commands for scaffolding
   - ✅ Hot reload for integrations
   - ✅ Logging and debugging tools
   - Testing framework for integrations

All the core infrastructure is in place. You just need to build the frontend and add more integrations!

---

## Summary

The Zaplane framework provides everything needed to build sophisticated workflow automation plugins:

- **Laravel-inspired** patterns for familiarity
- **ORM** for database operations without raw SQL
- **Integration system** that's easy to extend
- **CLI** for developer productivity
- **Modern architecture** with dependency injection
- **Production-ready** logging, error handling, security

Start building your automations today! 🚀
