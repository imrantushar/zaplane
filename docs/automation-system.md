# Zaplane Automation System

This document explains how the Zaplane automation system works internally, covering the complete execution flow from trigger to completion.

## Table of Contents

1. [System Overview](#system-overview)
2. [Execution Flow](#execution-flow)
3. [Trigger System](#trigger-system)
4. [Action Execution](#action-execution)
5. [Data Flow](#data-flow)
6. [Credential Management](#credential-management)
7. [Rate Limiting](#rate-limiting)
8. [Retry Logic](#retry-logic)
9. [Complex Workflow Scenarios](#complex-workflow-scenarios)
10. [Integration with External Apps](#integration-with-external-apps)

---

## System Overview

The automation system consists of several interconnected components:

```
┌─────────────────────────────────────────────────────────────────┐
│                      WordPress Hooks                             │
│    (publish_post, user_register, woocommerce_order_created)     │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Automation Engine                             │
│                   (trigger_router)                               │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │  Modular    │  │   Legacy    │  │  External   │              │
│  │  Triggers   │  │  Triggers   │  │   Webhooks  │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Action Scheduler Queue                        │
│              (zaplane_execute_node_run)                          │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Node Executor                                 │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Credential  │  │   Data      │  │   Action    │              │
│  │ Injection   │  │   Mapping   │  │  Execution  │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Child Node Spawning                           │
│              (spawn_children based on output port)               │
└─────────────────────────────────────────────────────────────────┘
```

### Key Components

| Component | File | Purpose |
|-----------|------|---------|
| `Automation` | `includes/framework/core/automation.php` | Main orchestrator |
| `IntegrationLoader` | `includes/framework/core/integration-loader.php` | Integration discovery |
| `DataMapper` | `includes/framework/classes/data-mapper.php` | Variable resolution |
| `NodeConfig` | `includes/framework/classes/node-config.php` | Node configuration |
| `ExecutionContext` | `includes/framework/classes/execution-context.php` | Execution context |

### Data Models

| Model | Table | Purpose |
|-------|-------|---------|
| `Workflow` | `zaplane_workflows` | Workflow metadata |
| `WorkflowVersion` | `zaplane_workflow_versions` | Versioned graphs (nodes/edges) |
| `Run` | `zaplane_runs` | Single workflow execution |
| `NodeRun` | `zaplane_node_runs` | Single node execution |
| `NodeLog` | `zaplane_node_logs` | Execution logs |
| `Connection` | `zaplane_connections` | Encrypted credentials |

---

## Execution Flow

### 1. Boot Phase

When WordPress initializes, the automation system boots:

```php
// In plugin bootstrap
Automation::init($container);

// In Automation::boot()
public function boot(): void
{
    // Skip during WP-CLI to prevent accidental triggers
    if (defined('WP_CLI') && WP_CLI) {
        return;
    }

    add_action('init', [$this, 'dispatch_active_triggers']);
    add_action('zaplane_execute_node_run', [$this, 'dispatch_node_run'], 10, 1);
    add_action('zaplane_workflow_updated', [$this, 'reload_triggers']);
}
```

### 2. Trigger Registration

Active workflows are scanned and their trigger hooks are registered:

```php
public function dispatch_active_triggers(): void
{
    // Get all unique trigger events from active workflows
    foreach (Query::get_active_trigger_events() as $event) {
        if (!isset($this->registered_hooks[$event])) {
            $cb = [$this, 'trigger_router'];
            add_action($event, $cb, 10, 99);  // 99 args to capture all
            $this->registered_hooks[$event] = $cb;
        }
    }
}
```

### 3. Trigger Fires

When a WordPress hook fires that matches a registered trigger:

```php
public function trigger_router()
{
    $event = current_filter();       // e.g., 'publish_post'
    $args = func_get_args();         // Hook arguments

    foreach (Query::get_active_workflows_for_event($event) as $trigger) {
        $slug = strtolower($trigger['app']);
        $trigger_key = $trigger['graph_node']['data']['event'] ?? '';

        // Try modular trigger first
        $trigger_class = IntegrationLoader::getTriggerClass($slug, $trigger_key);

        if ($trigger_class) {
            // Check if trigger matches (e.g., correct post type)
            if (!$trigger_class::matches($node_data, $args)) {
                continue;
            }
            // Resolve payload from hook arguments
            $payload = $trigger_class::resolve($node_data, $args);
        } else {
            // Fall back to legacy integration
            $integration = $this->container->get('integrations')->get($slug);
            $payload = $integration::resolve_trigger($node_data, $args);
        }

        if (!$payload) {
            continue;
        }

        // Start the workflow run
        $this->start_trigger_run($trigger, $payload);
    }
}
```

### 4. Run Creation

A new Run record is created to track the execution:

```php
private function start_trigger_run(array $trigger, array $payload)
{
    $run = Run::create([
        'workflow_id'           => $trigger['workflow_id'],
        'workflow_version_hash' => $trigger['workflow_version_hash'],
        'trigger_data'          => $payload,
        'status'                => 'running',
        'start_node_key'        => $trigger['id'],
        'started_at'            => current_time('mysql'),
    ]);

    // Spawn the first node run (trigger node)
    $this->spawn_node_run($run->id, $trigger['id'], $payload, null);
}
```

### 5. Node Execution Queue

Nodes are executed asynchronously via Action Scheduler:

```php
public function spawn_node_run(int $run_id, string $node_key, array $input, ?int $parent)
{
    $nodeRun = NodeRun::create([
        'run_id'             => $run_id,
        'node_key'           => $node_key,
        'parent_node_run_id' => $parent,
        'status'             => 'pending',
        'input_json'         => $input,
        'max_attempts'       => self::DEFAULT_MAX_ATTEMPTS,
        'started_at'         => current_time('mysql'),
    ]);

    // Queue for async execution
    as_enqueue_async_action(
        'zaplane_execute_node_run',
        ['node_run_id' => $nodeRun->id],
        'zaplane'  // Group
    );
}
```

### 6. Node Execution

When the queue processes a node:

```php
private function execute_node(NodeRun $nodeRun)
{
    $run = $nodeRun->run();
    $graph = $this->load_graph($run->workflow_version_hash);
    $node = $this->find_node($graph, $nodeRun->node_key);
    $input = $nodeRun->getInput();

    try {
        if ($node['type'] === 'trigger') {
            // Trigger nodes pass through their payload
            $output = $input;
        } else {
            // Action nodes execute logic
            $app_slug = strtolower($node['data']['app'] ?? '');
            $action_key = NodeConfig::from($node)->event();

            // Try modular action first
            $action_class = IntegrationLoader::getActionClass($app_slug, $action_key);

            if ($action_class) {
                // Inject credentials
                $node = $this->inject_credentials($node, $integration);

                // Resolve variables in config
                $node = DataMapper::resolve_node_config($node, $input);

                // Execute modular action
                $config = NodeConfig::from($node)->config();
                $credentials = $node['_connection_credentials'] ?? [];

                $output = $action_class::execute($config, $input, $credentials);
            } else {
                // Legacy integration execution
                $output = $integration::execute_node($node, $input);
            }
        }

        $nodeRun->setOutput($output);

        // Spawn child nodes based on output port
        $this->spawn_children($nodeRun, $output, $graph, $run);

    } catch (\Throwable $e) {
        // Handle retry logic (see Retry Logic section)
    }

    $this->finalize_run($run->id);
}
```

### 7. Child Node Spawning

After a node executes, its children are spawned:

```php
private function spawn_children(NodeRun $nodeRun, array $output, array $graph, Run $run)
{
    $port = $output['port'] ?? 'main';
    $data = $output['data'] ?? $output;

    foreach ($graph['edges'] as $edge) {
        if ($edge['source'] === $nodeRun->node_key) {
            // Port-based routing for conditional actions
            $edge_port = $edge['sourceHandle'] ?? 'main';
            if ($edge_port !== $port && $port !== 'main') {
                continue;
            }

            // Spawn child node
            $this->spawn_node_run(
                $nodeRun->run_id,
                $edge['target'],
                $data,
                $nodeRun->id
            );
        }
    }
}
```

### 8. Run Finalization

When all nodes complete:

```php
private function finalize_run(int $run_id)
{
    $pendingCount = NodeRun::where('run_id', $run_id)
        ->whereIn('status', ['pending', 'running'])
        ->count();

    if ($pendingCount == 0) {
        $failedCount = NodeRun::where('run_id', $run_id)
            ->where('status', 'failed')
            ->count();

        $run = Run::find($run_id);
        $run->status = $failedCount ? 'failed' : 'completed';
        $run->finished_at = current_time('mysql');
        $run->save();
    }
}
```

---

## Trigger System

### Modular Triggers

Each trigger extends `BaseTrigger`:

```php
class PublishPost extends BaseTrigger
{
    public static function get_label(): string
    {
        return 'Post Published';
    }

    public static function get_hook(): string
    {
        return 'publish_post';
    }

    /**
     * Filter triggers based on configuration
     */
    public static function matches(array $node, array $hook_args): bool
    {
        $config = $node['data']['config'] ?? [];
        $post = get_post($hook_args[0] ?? 0);

        // Skip if post type doesn't match
        if (!empty($config['post_type']) && $post->post_type !== $config['post_type']) {
            return false;
        }

        return true;
    }

    /**
     * Extract payload from hook arguments
     */
    public static function resolve(array $node, array $hook_args)
    {
        $post_id = $hook_args[0] ?? 0;
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        return [
            'post_id'      => $post->ID,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_type'    => $post->post_type,
            'post_status'  => $post->post_status,
            'author_id'    => $post->post_author,
        ];
    }
}
```

### Trigger Matching Flow

```
Hook fires (e.g., publish_post)
        │
        ▼
┌───────────────────────────────┐
│  Get all active workflows     │
│  with this hook               │
└───────────────┬───────────────┘
                │
                ▼
┌───────────────────────────────┐
│  For each workflow:           │
│  1. Get trigger class         │
│  2. Call matches()            │
│  3. If true, call resolve()   │
│  4. If payload, start run     │
└───────────────────────────────┘
```

---

## Action Execution

### Modular Actions

Each action extends `BaseAction`:

```php
class SendMessage extends BaseAction
{
    public static function get_label(): string
    {
        return 'Send Message';
    }

    public static function get_config_schema(): array
    {
        return [
            ['key' => 'channel', 'type' => 'text', 'label' => 'Channel', 'required' => true],
            ['key' => 'text', 'type' => 'textarea', 'label' => 'Message', 'required' => true],
        ];
    }

    public static function execute(array $config, array $input, array $credentials = []): array
    {
        $token = $credentials['access_token'] ?? '';

        // Make API call
        $response = wp_remote_post('https://slack.com/api/chat.postMessage', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'body' => [
                'channel' => $config['channel'],
                'text' => $config['text'],
            ],
        ]);

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body['ok']) {
            throw new \Exception('Slack API error: ' . ($body['error'] ?? 'Unknown'));
        }

        // Return merged data (input + new data)
        return static::success($input, [
            'message_ts' => $body['ts'] ?? '',
            'channel_id' => $body['channel'] ?? '',
        ]);
    }
}
```

### Conditional Actions (Branching)

Actions can route to different output ports:

```php
class Condition extends BaseAction
{
    public static function get_output_ports(): array
    {
        return ['true', 'false'];
    }

    public static function execute(array $config, array $input, array $credentials = []): array
    {
        $condition = $this->evaluateExpression($config['condition'], $input);

        return static::branch($condition, $input, [
            'evaluated' => $condition,
        ]);
    }
}
```

---

## Data Flow

### Variable Resolution

The `DataMapper` resolves variables in node configurations:

```php
// Node config with variables
$config = [
    'post_title' => 'Hello {{trigger.user_name}}',
    'post_content' => 'Welcome! Your email is {{trigger.email}}',
];

// Input data from previous node
$input = [
    'user_name' => 'John Doe',
    'email' => 'john@example.com',
];

// After resolution
$resolved = [
    'post_title' => 'Hello John Doe',
    'post_content' => 'Welcome! Your email is john@example.com',
];
```

### Data Accumulation

Data flows through the workflow, accumulating at each step:

```
Trigger Output:
{
    "post_id": 123,
    "post_title": "My Post"
}
        │
        ▼
Action 1 Output (merged):
{
    "post_id": 123,
    "post_title": "My Post",
    "comment_id": 456,
    "comment_text": "Great post!"
}
        │
        ▼
Action 2 Output (merged):
{
    "post_id": 123,
    "post_title": "My Post",
    "comment_id": 456,
    "comment_text": "Great post!",
    "notification_sent": true,
    "notification_id": "notif_789"
}
```

---

## Credential Management

### Connection Storage

Credentials are encrypted and stored in the `connections` table:

```php
class Connection extends Model
{
    protected static array $casts = [
        'credentials' => 'encrypted',  // Auto-encrypted
    ];
}
```

### Credential Injection

Before action execution, credentials are decrypted and injected:

```php
private function inject_credentials(array $node, object $integration): array
{
    $class = get_class($integration);

    // Only inject for integrations requiring connection
    if (!$class::requires_connection()) {
        return $node;
    }

    $connection_id = $node['data']['connection_id'] ?? null;

    if (!$connection_id) {
        return $node;
    }

    // Decrypt and inject
    $connection_manager = $this->container->get('connections');
    $credentials = $connection_manager->get_execution_credentials((int) $connection_id);
    $node['_connection_credentials'] = $credentials;

    return $node;
}
```

### Using Credentials in Actions

```php
public static function execute(array $config, array $input, array $credentials = []): array
{
    // Credentials are automatically injected
    $token = $credentials['access_token'] ?? $credentials['api_key'] ?? '';

    if (empty($token)) {
        throw new \Exception('Authentication token is missing');
    }

    // Use token for API calls
    // ...
}
```

---

## Rate Limiting

### Integration Rate Limits

Integrations can define rate limits:

```php
class SlackIntegration extends ExternalAppIntegration
{
    public static function get_rate_limit(): int
    {
        return 60;  // 60 requests per minute
    }
}
```

### Rate Limit Enforcement

```php
private function enforce_rate_limit(object $integration, NodeRun $nodeRun): void
{
    $class = get_class($integration);
    $limit = $class::get_rate_limit();

    if ($limit <= 0) {
        return;
    }

    $slug = $class::get_slug();
    $transient_key = 'zaplane_rate_' . $slug . '_' . gmdate('YmdHi');

    $current = (int) get_transient($transient_key);

    if ($current >= $limit) {
        $nodeRun->log('warning', "Rate limit reached for {$slug}");
        throw new \Exception("Rate limit exceeded: {$current}/{$limit}");
    }

    // Increment counter (120s TTL for minute boundary)
    set_transient($transient_key, $current + 1, 120);
}
```

---

## Retry Logic

### Exponential Backoff

Failed nodes are retried with exponential backoff:

```php
const DEFAULT_MAX_ATTEMPTS = 3;
const RETRY_BASE_DELAY = 30;  // seconds

// In execute_node catch block:
catch (\Throwable $e) {
    $nodeRun->incrementAttempts();

    if ($nodeRun->canRetry()) {
        // Exponential backoff: 30s, 60s, 120s, ...
        $delay_seconds = self::RETRY_BASE_DELAY * pow(2, $nodeRun->attempts - 1);

        $nodeRun->status = 'pending';
        $nodeRun->save();

        $nodeRun->log('info', "Scheduling retry in {$delay_seconds}s");

        as_schedule_single_action(
            time() + $delay_seconds,
            'zaplane_execute_node_run',
            ['node_run_id' => $nodeRun->id],
            'zaplane'
        );
        return;
    }

    // All retries exhausted
    $nodeRun->status = 'failed';
    $nodeRun->finished_at = current_time('mysql');
    $nodeRun->save();
}
```

### Retry Timeline

| Attempt | Delay | Total Wait |
|---------|-------|------------|
| 1 | Immediate | 0s |
| 2 | 30s | 30s |
| 3 | 60s | 90s |
| 4 (fail) | - | - |

---

## Complex Workflow Scenarios

### Scenario 1: Post Published → Notify + Log

```
┌─────────────────┐
│  Post Published │
│    (trigger)    │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌───────┐  ┌───────┐
│ Slack │  │ Log   │
│ Notify│  │ Entry │
└───────┘  └───────┘
```

**Execution:**
1. `publish_post` fires
2. Trigger resolves post data
3. Two NodeRuns spawned in parallel
4. Each executes independently
5. Run completes when both finish

### Scenario 2: User Registration with Conditions

```
┌─────────────────┐
│ User Registered │
│    (trigger)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   Condition:    │
│  role == admin  │
└────────┬────────┘
    ┌────┴────┐
    │true     │false
    ▼         ▼
┌───────┐  ┌───────┐
│ Admin │  │ Basic │
│ Setup │  │ Setup │
└───────┘  └───────┘
```

**Execution:**
1. `user_register` fires
2. Trigger resolves user data
3. Condition node evaluates
4. Routes to true OR false port
5. Only one child executes

### Scenario 3: Sequential Actions with Data Accumulation

```
┌─────────────────┐
│  Form Submitted │
└────────┬────────┘
         │
         ▼
┌─────────────────┐     Data: {form_id, email}
│  Create User    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐     Data: {form_id, email, user_id}
│  Create Post    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐     Data: {form_id, email, user_id, post_id}
│  Send Email     │
└─────────────────┘
```

**Execution:**
1. Each action receives data from previous
2. Each action adds its output
3. Final action has all accumulated data

### Scenario 4: Loop with Iterator

```
┌─────────────────┐
│  Get Products   │
│    (returns     │
│     array)      │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│    Iterator     │
│  (for each)     │
└────────┬────────┘
         │ (spawns N children)
    ┌────┼────┐
    │    │    │
    ▼    ▼    ▼
┌─────┐┌─────┐┌─────┐
│ Sync││ Sync││ Sync│
│ #1  ││ #2  ││ #3  │
└─────┘└─────┘└─────┘
```

**Execution:**
1. Get Products returns array of 3 items
2. Iterator spawns 3 NodeRuns (one per item)
3. Each Sync action runs in parallel
4. Run completes when all finish

---

## Integration with External Apps

### OAuth2 Flow

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Zaplane    │     │   External   │     │   External   │
│   Frontend   │     │     App      │     │     API      │
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │                    │                    │
       │  1. Click Connect  │                    │
       ├───────────────────►│                    │
       │                    │                    │
       │  2. Redirect to OAuth │                 │
       │◄───────────────────┤                    │
       │                    │                    │
       │  3. User Authorizes │                   │
       │────────────────────►│                    │
       │                    │                    │
       │  4. Callback + Code │                   │
       │◄───────────────────┤                    │
       │                    │                    │
       │  5. Exchange Code  │                    │
       │────────────────────┼───────────────────►│
       │                    │                    │
       │  6. Access Token   │                    │
       │◄───────────────────┼────────────────────│
       │                    │                    │
       │  7. Store Encrypted│                    │
       │  (Connection)      │                    │
       │                    │                    │
```

### API Request Flow

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Node Run    │     │  Connection  │     │  External    │
│  Executor    │     │   Manager    │     │    API       │
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │                    │                    │
       │ 1. Get Credentials │                    │
       ├───────────────────►│                    │
       │                    │                    │
       │ 2. Decrypt & Return│                    │
       │◄───────────────────┤                    │
       │                    │                    │
       │ 3. Make API Request│                    │
       ├────────────────────┼───────────────────►│
       │                    │                    │
       │ 4. Response        │                    │
       │◄───────────────────┼────────────────────│
       │                    │                    │
       │ 5. Update NodeRun  │                    │
       │    with output     │                    │
       │                    │                    │
```

---

## Debugging and Logging

### Node Logging

Each node run can log messages:

```php
$nodeRun->log('info', 'Starting API request');
$nodeRun->log('warning', 'Rate limit approaching');
$nodeRun->log('error', 'API request failed: ' . $error);
```

### Viewing Logs

Logs are stored in `zaplane_node_logs`:

```php
$logs = NodeLog::where('node_run_id', $nodeRunId)
    ->orderBy('created_at', 'asc')
    ->get();

foreach ($logs as $log) {
    echo "[{$log->level}] {$log->message}\n";
}
```

### Debug Mode

Enable detailed logging:

```php
// wp-config.php
define('ZAPLANE_DEBUG', true);
define('ZAPLANE_ALLOW_LOGS', true);
```

---

## Performance Considerations

### Queue Concurrency

Action Scheduler handles concurrency. Configure in WordPress:

```php
// Increase concurrent actions (default: 5)
add_filter('action_scheduler_queue_runner_concurrent_batches', function() {
    return 10;
});
```

### Database Indexes

Ensure indexes on frequently queried columns:

```php
$table->index('workflow_id');
$table->index('status');
$table->index(['workflow_id', 'status']);
```

### Cleanup Old Runs

Use the cleanup command to remove old execution data:

```bash
wp zaplane cleanup --days=30
```

---

## Extending the System

### Custom Trigger Sources

Register custom hooks for external webhooks:

```php
// In your integration
public static function register_webhook_handler(): void
{
    add_action('rest_api_init', function() {
        register_rest_route('zaplane/v1', '/webhook/myapp', [
            'methods' => 'POST',
            'callback' => [static::class, 'handle_webhook'],
            'permission_callback' => '__return_true',
        ]);
    });
}

public static function handle_webhook($request): WP_REST_Response
{
    $data = $request->get_json_params();

    // Fire custom hook for trigger system to catch
    do_action('myapp_webhook_received', $data);

    return new WP_REST_Response(['success' => true]);
}
```

### Custom Action Types

Create specialized action types:

```php
class ApiAction extends BaseAction
{
    protected static function make_request(string $method, string $url, array $body, array $credentials): array
    {
        $response = wp_remote_request($url, [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . ($credentials['access_token'] ?? ''),
                'Content-Type' => 'application/json',
            ],
            'body' => $method !== 'GET' ? json_encode($body) : null,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
```

---

## Summary

The Zaplane automation system provides:

1. **Event-driven architecture** - WordPress hooks trigger workflows
2. **Async execution** - Action Scheduler handles queuing
3. **Data accumulation** - Output flows through the workflow
4. **Credential security** - Encrypted storage and injection
5. **Fault tolerance** - Retry logic with exponential backoff
6. **Flexible routing** - Conditional branching via ports
7. **Extensibility** - Modular triggers and actions

For integration development, see the main [README](./readme.md).
