# Zaplane Developer Documentation

Complete documentation for building integrations, using the ORM, configuring the framework, and understanding the automation system.

## Table of Contents

1. [Integration Development](#integration-development)
   - [Quick Start](#quick-start)
   - [Creating Triggers](#creating-triggers)
   - [Creating Actions](#creating-actions)
   - [Configuration Schema](#configuration-schema)
   - [Integration Registration](#integration-registration)
   - [Testing Integrations](#testing-integrations)
2. [ORM (Object-Relational Mapper)](#orm)
   - [Models](#models)
   - [Creating New Models](#creating-new-models)
   - [WordPress Table Models](#wordpress-table-models)
   - [Query Builder](#query-builder)
   - [Collections](#collections)
   - [Migrations](#migrations)
3. [Controllers and API](#controllers-and-api)
   - [REST API Controllers](#rest-api-controllers)
   - [Creating Controllers](#creating-controllers)
   - [Ajax Handling](#ajax-handling)
4. [WP-CLI Commands](#wp-cli-commands)
   - [Available Commands](#available-commands)
   - [Creating Commands](#creating-commands)
5. [Configuration System](#configuration-system)
   - [Config Helper](#config-helper)
   - [Config Manager](#config-manager)
   - [Configuration Repository](#configuration-repository)
6. [Automation System](#automation-system) - [See detailed documentation](./automation-system.md)

---

## Integration Development

Zaplane uses a modular integration architecture where each trigger and action lives in its own file. This follows the Single Responsibility Principle and is an industry-standard approach used by Zapier, Make.com, and n8n.

### Quick Start

Create a new integration in 3 steps:

#### 1. Create the Integration Directory

```
integrations/
  myapp/
    myapp-integration.php      # Main integration class
    triggers/
      user-created.php         # One trigger per file
      order-placed.php
    actions/
      create-record.php        # One action per file
      update-record.php
```

#### 2. Create the Main Integration Class

```php
<?php
// integrations/myapp/myapp-integration.php

namespace Zaplane\Integrations\Myapp;

use Zaplane\Framework\Classes\WordPressPluginIntegration;
// OR use Zaplane\Framework\Classes\ExternalAppIntegration for OAuth apps

class MyappIntegration extends WordPressPluginIntegration
{
    public static function get_slug(): string
    {
        return 'myapp';
    }

    public static function get_name(): string
    {
        return 'My Application';
    }

    public static function get_icon(): string
    {
        return 'dashicons-admin-generic';
    }

    public static function get_category(): string
    {
        return 'app'; // or 'tool' for utility integrations
    }
}
```

#### 3. Create Triggers and Actions

Triggers and actions are auto-discovered from the `triggers/` and `actions/` directories.

---

### Creating Triggers

Triggers listen to WordPress hooks and initiate workflows when events occur.

```php
<?php
// integrations/myapp/triggers/user-created.php

namespace Zaplane\Integrations\Myapp\Triggers;

use Zaplane\Framework\Classes\BaseTrigger;

class UserCreated extends BaseTrigger
{
    /**
     * Human-readable label for the UI
     */
    public static function get_label(): string
    {
        return 'User Created';
    }

    /**
     * WordPress hook to listen to
     */
    public static function get_hook(): string
    {
        return 'user_register';
    }

    /**
     * Optional: Description for help text
     */
    public static function get_description(): string
    {
        return 'Triggers when a new user registers on the site.';
    }

    /**
     * Configuration fields shown in the UI
     */
    public static function get_config_schema(): array
    {
        return [
            [
                'key'     => 'role',
                'label'   => 'User Role',
                'type'    => 'select',
                'options' => [
                    ['label' => 'Any', 'value' => ''],
                    ['label' => 'Administrator', 'value' => 'administrator'],
                    ['label' => 'Subscriber', 'value' => 'subscriber'],
                ],
            ],
        ];
    }

    /**
     * Output schema for variable mapping
     */
    public static function get_output_schema(): array
    {
        return [
            'user_id'      => 'integer',
            'user_email'   => 'string',
            'user_login'   => 'string',
            'display_name' => 'string',
        ];
    }

    /**
     * Optional: Filter which events should trigger the workflow
     */
    public static function matches(array $node, array $hook_args): bool
    {
        $config = $node['data']['config'] ?? [];
        $user_id = $hook_args[0] ?? 0;
        $user = get_user_by('ID', $user_id);

        if (!$user) {
            return false;
        }

        // Filter by role if configured
        if (!empty($config['role']) && !in_array($config['role'], $user->roles)) {
            return false;
        }

        return true;
    }

    /**
     * Extract data from the hook arguments
     *
     * @param array $node The trigger node configuration
     * @param array $hook_args Arguments passed to the WordPress hook
     * @return array|false Payload data or false to skip
     */
    public static function resolve(array $node, array $hook_args)
    {
        $user_id = $hook_args[0] ?? 0;
        $user = get_user_by('ID', $user_id);

        if (!$user) {
            return false;
        }

        return [
            'user_id'      => $user->ID,
            'user_email'   => $user->user_email,
            'user_login'   => $user->user_login,
            'display_name' => $user->display_name,
        ];
    }
}
```

#### Trigger Key Auto-Generation

The trigger key is automatically derived from the class name:
- `UserCreated` → `user_created`
- `OrderStatusChanged` → `order_status_changed`

Override `get_key()` if you need a custom key.

---

### Creating Actions

Actions perform operations when executed in a workflow.

```php
<?php
// integrations/myapp/actions/create-record.php

namespace Zaplane\Integrations\Myapp\Actions;

use Zaplane\Framework\Classes\BaseAction;

class CreateRecord extends BaseAction
{
    /**
     * Human-readable label for the UI
     */
    public static function get_label(): string
    {
        return 'Create Record';
    }

    /**
     * Optional: Description for help text
     */
    public static function get_description(): string
    {
        return 'Creates a new record in the database.';
    }

    /**
     * Configuration fields shown in the UI
     */
    public static function get_config_schema(): array
    {
        return [
            [
                'key'      => 'title',
                'label'    => 'Title',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'   => 'content',
                'label' => 'Content',
                'type'  => 'textarea',
            ],
            [
                'key'     => 'status',
                'label'   => 'Status',
                'type'    => 'select',
                'options' => [
                    ['label' => 'Draft', 'value' => 'draft'],
                    ['label' => 'Published', 'value' => 'publish'],
                ],
            ],
        ];
    }

    /**
     * Output schema for variable mapping
     */
    public static function get_output_schema(): array
    {
        return [
            'record_id' => 'integer',
            'title'     => 'string',
            'status'    => 'string',
        ];
    }

    /**
     * Execute the action
     *
     * @param array $config      Resolved configuration values
     * @param array $input       Data from previous nodes
     * @param array $credentials Decrypted connection credentials (for external APIs)
     * @return array Must return ['port' => 'main', 'data' => [...]]
     */
    public static function execute(array $config, array $input, array $credentials = []): array
    {
        $record_id = wp_insert_post([
            'post_title'   => $config['title'] ?? '',
            'post_content' => $config['content'] ?? '',
            'post_status'  => $config['status'] ?? 'draft',
            'post_type'    => 'record',
        ], true);

        if (is_wp_error($record_id)) {
            throw new \Exception($record_id->get_error_message());
        }

        // Use the success() helper to merge input with new data
        return static::success($input, [
            'record_id' => $record_id,
            'title'     => $config['title'],
            'status'    => $config['status'],
        ]);
    }
}
```

#### Conditional Actions (Branching)

For actions that need to branch the workflow:

```php
<?php
// integrations/tools/actions/condition.php

namespace Zaplane\Integrations\Tools\Actions;

use Zaplane\Framework\Classes\BaseAction;

class Condition extends BaseAction
{
    public static function get_label(): string
    {
        return 'Condition';
    }

    public static function get_config_schema(): array
    {
        return [
            [
                'key'      => 'condition',
                'label'    => 'Condition',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    /**
     * Define custom output ports for branching
     */
    public static function get_output_ports(): array
    {
        return ['true', 'false'];
    }

    public static function execute(array $config, array $input, array $credentials = []): array
    {
        $condition = !empty($config['condition']);

        // Use the branch() helper for conditional routing
        return static::branch($condition, $input, [
            'evaluated' => $condition,
        ]);
    }
}
```

---

### Configuration Schema

The config schema defines UI fields for triggers and actions.

#### Field Types

| Type | Description | Properties |
|------|-------------|------------|
| `text` | Single-line text input | `placeholder` |
| `textarea` | Multi-line text input | `placeholder`, `rows` |
| `expression` | Text with variable support | Allows `{{variable}}` syntax |
| `number` | Numeric input | `min`, `max`, `step` |
| `boolean` | Checkbox | `default` |
| `select` | Dropdown | `options`, `dynamic` |
| `multiselect` | Multi-select dropdown | `options`, `dynamic` |
| `datetime` | Date/time picker | `format` |
| `password` | Password input | Masked display |

#### Field Properties

```php
[
    'key'         => 'field_name',        // Required: unique identifier
    'label'       => 'Field Label',       // Required: display label
    'type'        => 'text',              // Required: field type
    'required'    => true,                // Optional: validation
    'default'     => 'value',             // Optional: default value
    'placeholder' => 'Enter value...',    // Optional: placeholder text
    'description' => 'Help text',         // Optional: help text below field
    'options'     => [...],               // For select/multiselect
    'dynamic'     => [...],               // For dynamic options
]
```

#### Static Options

```php
[
    'key'     => 'status',
    'label'   => 'Status',
    'type'    => 'select',
    'options' => [
        ['label' => 'Active', 'value' => 'active'],
        ['label' => 'Inactive', 'value' => 'inactive'],
    ],
]
```

#### Dynamic Options

For dropdowns populated from the database:

```php
[
    'key'     => 'post_type',
    'label'   => 'Post Type',
    'type'    => 'select',
    'dynamic' => [
        'integration' => 'wordpress',
        'query'       => 'post_types',
        'select'      => ['name', 'label'],
    ],
]
```

---

### Integration Registration

Integrations are auto-discovered from the `integrations/` directory.

#### Directory Structure

```
integrations/
  slack/
    slack-integration.php           # Main class (required)
    triggers/                        # Trigger classes (optional)
      message-received.php
    actions/                         # Action classes (optional)
      send-message.php
      create-channel.php
```

#### File Naming Convention

- **Directories**: lowercase (e.g., `slack`, `wordpress`)
- **Files**: kebab-case (e.g., `slack-integration.php`, `send-message.php`)
- **Classes**: PascalCase (e.g., `SlackIntegration`, `SendMessage`)

#### Using IntegrationLoader

```php
use Zaplane\Framework\Core\IntegrationLoader;

// Get an integration instance
$slack = IntegrationLoader::get('slack');

// Get all triggers for an integration
$triggers = IntegrationLoader::getTriggers('wordpress');

// Get all actions for an integration
$actions = IntegrationLoader::getActions('wordpress');

// Check if integration exists
if (IntegrationLoader::has('slack')) {
    // ...
}

// Get specific trigger/action class
$triggerClass = IntegrationLoader::getTriggerClass('wordpress', 'publish_post');
$actionClass = IntegrationLoader::getActionClass('wordpress', 'create_post');
```

---

### Testing Integrations

Proper testing ensures your triggers and actions work correctly. Zaplane uses PHPUnit with WordPress mocks.

#### Test Directory Structure

```
tests/
  Unit/
    BaseTriggerTest.php           # Tests for trigger base class
    BaseActionTest.php            # Tests for action base class
  Integration/
    MyappIntegrationTest.php      # Tests for your integration
    MyappWorkflowTest.php         # Tests for workflow scenarios
  TestCase.php                    # Base test class
  WPMocks.php                     # WordPress function mocks
  bootstrap.php                   # Test bootstrap
```

#### Setting Up WordPress Mocks

Create `tests/WPMocks.php` to mock WordPress functions:

```php
<?php
// tests/WPMocks.php

if (!function_exists('get_post')) {
    function get_post($post_id) {
        // Return mock post object
        return (object) [
            'ID'           => $post_id,
            'post_title'   => 'Test Post ' . $post_id,
            'post_content' => 'Test content',
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'post_author'  => 1,
        ];
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($args, $wp_error = false) {
        static $id = 1000;
        return ++$id;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        return (object) [
            'ID'           => $value,
            'user_login'   => 'testuser',
            'user_email'   => 'test@example.com',
            'display_name' => 'Test User',
            'roles'        => ['subscriber'],
        ];
    }
}

// Add more mocks as needed...
```

#### Base Test Class

Create `tests/TestCase.php`:

```php
<?php

namespace Zaplane\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Common setup for all tests
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Common cleanup
    }
}
```

#### Testing Triggers

##### Test Trigger Definition (build)

```php
<?php

namespace Zaplane\Tests\Integration;

use Zaplane\Tests\TestCase;
use Zaplane\Integrations\Myapp\Triggers\UserCreated;

class MyappTriggerTest extends TestCase
{
    /**
     * Test trigger builds correct definition
     */
    public function testBuildReturnsCorrectDefinition(): void
    {
        $definition = UserCreated::build();

        // Required fields
        $this->assertArrayHasKey('label', $definition);
        $this->assertArrayHasKey('hook', $definition);
        $this->assertArrayHasKey('_class', $definition);

        // Verify values
        $this->assertEquals('User Created', $definition['label']);
        $this->assertEquals('user_register', $definition['hook']);
        $this->assertEquals(UserCreated::class, $definition['_class']);
    }

    /**
     * Test trigger key is auto-generated correctly
     */
    public function testKeyAutoGeneration(): void
    {
        $this->assertEquals('user_created', UserCreated::get_key());
    }

    /**
     * Test config schema structure
     */
    public function testConfigSchemaStructure(): void
    {
        $schema = UserCreated::get_config_schema();

        $this->assertIsArray($schema);

        foreach ($schema as $field) {
            $this->assertArrayHasKey('key', $field);
            $this->assertArrayHasKey('label', $field);
            $this->assertArrayHasKey('type', $field);
        }
    }

    /**
     * Test output schema is defined
     */
    public function testOutputSchemaIsDefined(): void
    {
        $schema = UserCreated::get_output_schema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('user_id', $schema);
        $this->assertEquals('integer', $schema['user_id']);
    }
}
```

##### Test Trigger Resolution (resolve)

```php
<?php

/**
 * Test trigger resolves hook arguments correctly
 */
public function testResolveReturnsCorrectPayload(): void
{
    $node = [
        'data' => [
            'config' => ['role' => ''],  // Any role
        ],
    ];
    $hookArgs = [42];  // User ID

    $result = UserCreated::resolve($node, $hookArgs);

    $this->assertIsArray($result);
    $this->assertEquals(42, $result['user_id']);
    $this->assertArrayHasKey('user_email', $result);
    $this->assertArrayHasKey('display_name', $result);
}

/**
 * Test trigger returns false for invalid data
 */
public function testResolveReturnsFalseForInvalidUser(): void
{
    $node = ['data' => ['config' => []]];
    $hookArgs = [0];  // Invalid user ID

    $result = UserCreated::resolve($node, $hookArgs);

    $this->assertFalse($result);
}
```

##### Test Trigger Matching (matches)

```php
<?php

/**
 * Test trigger matches based on configuration
 */
public function testMatchesFiltersCorrectly(): void
{
    // Test with matching role
    $node = [
        'data' => [
            'config' => ['role' => 'subscriber'],
        ],
    ];
    $hookArgs = [42];

    $this->assertTrue(UserCreated::matches($node, $hookArgs));

    // Test with non-matching role
    $node['data']['config']['role'] = 'administrator';
    $this->assertFalse(UserCreated::matches($node, $hookArgs));

    // Test with empty role (any)
    $node['data']['config']['role'] = '';
    $this->assertTrue(UserCreated::matches($node, $hookArgs));
}
```

#### Testing Actions

##### Test Action Definition (build)

```php
<?php

namespace Zaplane\Tests\Integration;

use Zaplane\Tests\TestCase;
use Zaplane\Integrations\Myapp\Actions\CreateRecord;

class MyappActionTest extends TestCase
{
    /**
     * Test action builds correct definition
     */
    public function testBuildReturnsCorrectDefinition(): void
    {
        $definition = CreateRecord::build();

        $this->assertArrayHasKey('label', $definition);
        $this->assertArrayHasKey('config_schema', $definition);
        $this->assertArrayHasKey('_class', $definition);

        $this->assertEquals('Create Record', $definition['label']);
    }

    /**
     * Test action key is auto-generated correctly
     */
    public function testKeyAutoGeneration(): void
    {
        $this->assertEquals('create_record', CreateRecord::get_key());
    }

    /**
     * Test default output ports
     */
    public function testDefaultOutputPorts(): void
    {
        $ports = CreateRecord::get_output_ports();

        $this->assertEquals(['main'], $ports);
    }
}
```

##### Test Action Execution (execute)

```php
<?php

/**
 * Test action executes successfully
 */
public function testExecuteCreatesRecord(): void
{
    $config = [
        'title'   => 'Test Record',
        'content' => 'Test content',
        'status'  => 'publish',
    ];
    $input = ['trigger_data' => 'from_trigger'];

    $result = CreateRecord::execute($config, $input);

    // Check response structure
    $this->assertArrayHasKey('port', $result);
    $this->assertArrayHasKey('data', $result);
    $this->assertEquals('main', $result['port']);

    // Check output data
    $this->assertArrayHasKey('record_id', $result['data']);
    $this->assertIsInt($result['data']['record_id']);
    $this->assertEquals('Test Record', $result['data']['title']);

    // Check input is preserved
    $this->assertEquals('from_trigger', $result['data']['trigger_data']);
}

/**
 * Test action handles errors correctly
 */
public function testExecuteThrowsExceptionOnError(): void
{
    $this->expectException(\Exception::class);

    // Configure mock to return WP_Error
    $config = ['title' => ''];  // Invalid - will cause error
    $input = [];

    CreateRecord::execute($config, $input);
}

/**
 * Test action with credentials (for external APIs)
 */
public function testExecuteUsesCredentials(): void
{
    $config = ['channel' => '#general', 'text' => 'Hello'];
    $input = [];
    $credentials = ['access_token' => 'xoxb-test-token'];

    $result = SendMessage::execute($config, $input, $credentials);

    $this->assertEquals('main', $result['port']);
}
```

##### Test Conditional Actions

```php
<?php

/**
 * Test conditional action branches to true port
 */
public function testBranchesToTruePort(): void
{
    $config = ['condition' => true];
    $input = ['original' => 'data'];

    $result = Condition::execute($config, $input);

    $this->assertEquals('true', $result['port']);
    $this->assertTrue($result['data']['evaluated']);
    $this->assertEquals('data', $result['data']['original']);
}

/**
 * Test conditional action branches to false port
 */
public function testBranchesToFalsePort(): void
{
    $config = ['condition' => false];
    $input = ['original' => 'data'];

    $result = Condition::execute($config, $input);

    $this->assertEquals('false', $result['port']);
    $this->assertFalse($result['data']['evaluated']);
}
```

#### Testing Workflow Scenarios

Test complete workflows with trigger → action chains:

```php
<?php

namespace Zaplane\Tests\Integration;

use Zaplane\Tests\TestCase;
use Zaplane\Integrations\Wordpress\Triggers\PublishPost;
use Zaplane\Integrations\Wordpress\Actions\CreateComment;
use Zaplane\Integrations\Wordpress\Actions\UpdatePost;

class WorkflowTest extends TestCase
{
    /**
     * Test complete workflow: Post Published → Create Comment → Update Post
     */
    public function testComplexWorkflowChain(): void
    {
        // Step 1: Trigger fires (simulate post published)
        $triggerNode = [
            'data' => ['config' => ['post_type' => 'post']],
        ];
        $triggerHookArgs = [100];  // Post ID

        $triggerPayload = PublishPost::resolve($triggerNode, $triggerHookArgs);

        $this->assertIsArray($triggerPayload);
        $this->assertEquals(100, $triggerPayload['post_id']);

        // Step 2: Create comment on the published post
        $commentConfig = [
            'post_id'      => $triggerPayload['post_id'],
            'author_name'  => 'Auto Commenter',
            'author_email' => 'auto@example.com',
            'content'      => 'Comment on: ' . $triggerPayload['post_title'],
        ];

        $commentResult = CreateComment::execute($commentConfig, $triggerPayload);

        $this->assertEquals('main', $commentResult['port']);
        $this->assertArrayHasKey('comment_id', $commentResult['data']);
        // Trigger data flows through
        $this->assertEquals(100, $commentResult['data']['post_id']);

        // Step 3: Update the post
        $updateConfig = [
            'post_id'    => $triggerPayload['post_id'],
            'post_title' => $triggerPayload['post_title'] . ' (Updated)',
        ];

        $updateResult = UpdatePost::execute($updateConfig, $commentResult['data']);

        $this->assertEquals('main', $updateResult['port']);
        // All previous data preserved
        $this->assertArrayHasKey('post_id', $updateResult['data']);
        $this->assertArrayHasKey('comment_id', $updateResult['data']);
    }

    /**
     * Test workflow with multiple branches
     */
    public function testWorkflowWithBranches(): void
    {
        // Trigger fires
        $triggerPayload = ['user_id' => 25, 'display_name' => 'New User'];

        // Branch 1: Create welcome post
        $welcomeConfig = [
            'post_title'   => 'Welcome ' . $triggerPayload['display_name'],
            'post_content' => 'Welcome message',
            'post_status'  => 'publish',
        ];
        $welcomeResult = CreatePost::execute($welcomeConfig, $triggerPayload);

        // Branch 2: Create profile page
        $profileConfig = [
            'post_title'   => 'Profile: ' . $triggerPayload['display_name'],
            'post_content' => 'Profile content',
            'post_type'    => 'page',
        ];
        $profileResult = CreatePost::execute($profileConfig, $triggerPayload);

        // Both branches created different posts
        $this->assertNotEquals(
            $welcomeResult['data']['post_id'],
            $profileResult['data']['post_id']
        );
    }
}
```

#### Testing with IntegrationLoader

```php
<?php

namespace Zaplane\Tests\Integration;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Core\IntegrationLoader;

class IntegrationLoaderTest extends TestCase
{
    /**
     * Test all triggers are valid
     */
    public function testAllTriggersAreValid(): void
    {
        $triggers = IntegrationLoader::getTriggers('wordpress');

        $this->assertNotEmpty($triggers);

        foreach ($triggers as $key => $trigger) {
            $this->assertArrayHasKey('label', $trigger, "Trigger '{$key}' missing label");
            $this->assertArrayHasKey('hook', $trigger, "Trigger '{$key}' missing hook");
            $this->assertNotEmpty($trigger['label']);
            $this->assertNotEmpty($trigger['hook']);

            // Verify modular class exists
            if (isset($trigger['_class'])) {
                $this->assertTrue(
                    class_exists($trigger['_class']),
                    "Trigger '{$key}' class does not exist"
                );
            }
        }
    }

    /**
     * Test all actions are valid
     */
    public function testAllActionsAreValid(): void
    {
        $actions = IntegrationLoader::getActions('wordpress');

        $this->assertNotEmpty($actions);

        foreach ($actions as $key => $action) {
            $this->assertArrayHasKey('label', $action, "Action '{$key}' missing label");
            $this->assertNotEmpty($action['label']);

            if (isset($action['_class'])) {
                $this->assertTrue(
                    class_exists($action['_class']),
                    "Action '{$key}' class does not exist"
                );
            }
        }
    }

    /**
     * Test trigger and action counts meet expectations
     */
    public function testIntegrationCounts(): void
    {
        $triggers = IntegrationLoader::getTriggers('wordpress');
        $actions = IntegrationLoader::getActions('wordpress');

        // Adjust these numbers based on your expected counts
        $this->assertGreaterThan(50, count($triggers), 'Expected more triggers');
        $this->assertGreaterThan(50, count($actions), 'Expected more actions');
    }
}
```

#### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Integration/MyappIntegrationTest.php

# Run specific test method
./vendor/bin/phpunit --filter testExecuteCreatesRecord

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

#### Test Checklist

When testing an integration, verify:

**Triggers:**
- [ ] `build()` returns correct definition with label, hook, _class
- [ ] `get_key()` returns expected snake_case key
- [ ] `get_config_schema()` returns valid field definitions
- [ ] `get_output_schema()` documents all output fields
- [ ] `matches()` filters correctly based on configuration
- [ ] `resolve()` extracts correct data from hook arguments
- [ ] `resolve()` returns `false` for invalid input

**Actions:**
- [ ] `build()` returns correct definition with label, config_schema, _class
- [ ] `get_key()` returns expected snake_case key
- [ ] `get_config_schema()` returns valid field definitions
- [ ] `get_output_schema()` documents all output fields
- [ ] `get_output_ports()` returns correct ports (default: ['main'])
- [ ] `execute()` returns correct response structure
- [ ] `execute()` preserves input data in output
- [ ] `execute()` handles errors with exceptions
- [ ] Conditional actions branch correctly to true/false ports

**Workflow Tests:**
- [ ] Data flows correctly from trigger to actions
- [ ] Multiple actions can chain together
- [ ] Branching works with conditional actions
- [ ] Previous node data is preserved through the chain

---

## ORM

Zaplane includes a lightweight ORM inspired by Laravel's Eloquent.

### Models

#### Defining a Model

```php
<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

class Workflow extends Model
{
    // Table name (auto-derived from class name if not set)
    protected static string $table = 'workflows';

    // Primary key (default: 'id')
    protected static string $primaryKey = 'id';

    // Mass-assignable attributes
    protected static array $fillable = [
        'name',
        'description',
        'status',
        'nodes',
        'edges',
    ];

    // Attributes to hide from serialization
    protected static array $hidden = [
        'password',
        'api_key',
    ];

    // Automatic type casting
    protected static array $casts = [
        'nodes'   => 'array',
        'edges'   => 'array',
        'active'  => 'boolean',
        'options' => 'json',
    ];

    // Enable timestamps (default: true)
    protected static bool $timestamps = true;

    // Timestamp column names (defaults)
    protected static string $createdAt = 'created_at';
    protected static string $updatedAt = 'updated_at';
}
```

#### Creating Records

```php
// Using create()
$workflow = Workflow::create([
    'name'   => 'My Workflow',
    'status' => 'draft',
    'nodes'  => [],
]);

// Using new + save()
$workflow = new Workflow([
    'name'   => 'My Workflow',
    'status' => 'draft',
]);
$workflow->save();
```

#### Retrieving Records

```php
// Find by ID
$workflow = Workflow::find(1);
$workflow = Workflow::findOrFail(1); // Throws exception if not found

// Find multiple by IDs
$workflows = Workflow::findMany([1, 2, 3]);

// Get all records
$workflows = Workflow::all();

// Get first record
$workflow = Workflow::first();

// Query with conditions
$activeWorkflows = Workflow::where('status', 'active')->get();
$workflow = Workflow::where('name', 'My Workflow')->first();
```

#### Updating Records

```php
// Update via model
$workflow = Workflow::find(1);
$workflow->name = 'Updated Name';
$workflow->save();

// Update or create
$workflow = Workflow::updateOrCreate(
    ['name' => 'My Workflow'],              // Search criteria
    ['status' => 'active', 'nodes' => []]   // Values to update/create
);

// First or create
$workflow = Workflow::firstOrCreate(
    ['name' => 'My Workflow'],
    ['status' => 'draft']
);
```

#### Deleting Records

```php
// Delete via model
$workflow = Workflow::find(1);
$workflow->delete();

// Delete by ID(s)
Workflow::destroy(1);
Workflow::destroy(1, 2, 3);
Workflow::destroy([1, 2, 3]);
```

#### Attribute Casting

The `$casts` property automatically converts attributes:

| Cast Type | Description |
|-----------|-------------|
| `int`, `integer` | Integer |
| `float`, `double` | Float |
| `string` | String |
| `bool`, `boolean` | Boolean |
| `array`, `json` | JSON array |
| `datetime` | DateTime |

```php
protected static array $casts = [
    'nodes'     => 'array',    // JSON string ↔ PHP array
    'active'    => 'boolean',  // 1/0 ↔ true/false
    'priority'  => 'integer',  // String ↔ int
    'settings'  => 'json',     // JSON string ↔ PHP array
];
```

---

### Creating New Models

Models are stored in `includes/models/`. Follow these steps to create a new model:

#### 1. Create the Model File

```php
<?php
// includes/models/my-entity.php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

if (!defined('ABSPATH')) exit;

class MyEntity extends Model
{
    // Table name without WordPress prefix (zaplane_ prefix auto-added)
    protected static string $table = 'my_entities';

    // Mass-assignable fields
    protected static array $fillable = [
        'name',
        'type',
        'data',
        'user_id',
    ];

    // Type casting
    protected static array $casts = [
        'id'      => 'integer',
        'user_id' => 'integer',
        'data'    => 'array',
        'active'  => 'boolean',
    ];

    // Custom methods
    public function user()
    {
        return get_user_by('ID', $this->user_id);
    }

    public static function forUser(int $userId): Collection
    {
        return static::where('user_id', $userId)->get();
    }
}
```

#### 2. Create the Migration

```php
<?php
// includes/database/migrations/2024_01_15_000001_create_my_entities_table.php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateMyEntitiesTable extends Migration
{
    public function up(): void
    {
        Schema::create('my_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50);
            $table->json('data')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::drop('my_entities');
    }
}
```

#### 3. Register in Autoloader

Add to `includes/autoload.php`:

```php
'Zaplane\\Models\\MyEntity' => 'models/my-entity.php',
```

---

### WordPress Table Models

You can also create models for WordPress core tables. These models don't use the `zaplane_` prefix.

#### Post Model Example

```php
<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

class Post extends Model
{
    // Use WordPress posts table (no zaplane_ prefix)
    protected static string $table = 'posts';
    protected static bool $usePrefix = false;  // Skip zaplane_ prefix

    protected static string $primaryKey = 'ID';

    protected static array $fillable = [
        'post_title',
        'post_content',
        'post_status',
        'post_type',
        'post_author',
    ];

    protected static array $casts = [
        'ID'          => 'integer',
        'post_author' => 'integer',
    ];

    // Timestamps use different column names
    protected static string $createdAt = 'post_date';
    protected static string $updatedAt = 'post_modified';
}
```

#### User Model Example

```php
<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static bool $usePrefix = false;

    protected static string $primaryKey = 'ID';

    protected static array $fillable = [
        'user_login',
        'user_email',
        'display_name',
    ];

    protected static bool $timestamps = false;  // WP users table has different timestamp handling
}
```

---

### Existing Zaplane Models

The following models are available in `includes/models/`:

| Model | Table | Description |
|-------|-------|-------------|
| `Workflow` | `zaplane_workflows` | Workflow definitions |
| `WorkflowVersion` | `zaplane_workflow_versions` | Versioned workflow graphs |
| `Run` | `zaplane_runs` | Workflow execution runs |
| `NodeRun` | `zaplane_node_runs` | Individual node executions |
| `NodeLog` | `zaplane_node_logs` | Execution logs |
| `Connection` | `zaplane_connections` | Integration credentials |
| `QueueJob` | `zaplane_queue` | Pending async jobs |
| `ExecutionEdge` | `zaplane_execution_edges` | Node execution edges |

---

### Query Builder

The Query Builder provides a fluent interface for database queries.

#### Basic Queries

```php
use Zaplane\Framework\Database\ORM\QueryBuilder;
use Zaplane\Framework\Database\ORM\Schema;

$builder = new QueryBuilder(Schema::getTable('workflows'));

// Select all
$results = $builder->get();

// Select specific columns
$results = $builder->select('id', 'name', 'status')->get();

// First result
$result = $builder->first();
```

#### Where Clauses

```php
// Basic where
$builder->where('status', 'active');
$builder->where('status', '=', 'active');
$builder->where('count', '>', 10);

// Or where
$builder->where('status', 'active')->orWhere('status', 'pending');

// Where in
$builder->whereIn('status', ['active', 'pending']);
$builder->whereNotIn('status', ['deleted']);

// Where null
$builder->whereNull('deleted_at');
$builder->whereNotNull('published_at');

// Where between
$builder->whereBetween('created_at', ['2024-01-01', '2024-12-31']);

// Raw where
$builder->whereRaw('YEAR(created_at) = %d', [2024]);

// Nested where (grouped conditions)
$builder->where(function ($query) {
    $query->where('status', 'active')
          ->orWhere('status', 'pending');
});
```

#### Ordering and Limiting

```php
// Order by
$builder->orderBy('created_at', 'desc');
$builder->orderByDesc('created_at');
$builder->latest();           // Order by created_at DESC
$builder->oldest();           // Order by created_at ASC

// Limit and offset
$builder->limit(10);
$builder->offset(20);
$builder->take(10);           // Alias for limit
$builder->skip(20);           // Alias for offset

// Pagination
$builder->forPage(2, 15);     // Page 2 with 15 per page
```

#### Aggregates

```php
$count = Workflow::count();
$sum = Workflow::where('status', 'active')->sum('priority');
$avg = Workflow::avg('priority');
$max = Workflow::max('priority');
$min = Workflow::min('priority');
```

#### Joins

```php
$builder->join('users', 'workflows.user_id', '=', 'users.id');
$builder->leftJoin('logs', 'workflows.id', '=', 'logs.workflow_id');
$builder->rightJoin('categories', 'workflows.category_id', '=', 'categories.id');
```

#### Grouping

```php
$builder->select('status', new RawExpression('COUNT(*) as count'))
        ->groupBy('status')
        ->having('count', '>', 5)
        ->get();
```

#### Insert, Update, Delete

```php
// Insert
$id = $builder->insert(['name' => 'New', 'status' => 'draft']);

// Update with conditions
$affected = $builder->where('status', 'draft')->update(['status' => 'active']);

// Delete with conditions
$deleted = $builder->where('status', 'deleted')->delete();

// Increment/Decrement
$builder->where('id', 1)->increment('view_count');
$builder->where('id', 1)->decrement('stock', 5);
```

---

### Collections

Collections wrap arrays with powerful manipulation methods.

#### Creating Collections

```php
use Zaplane\Framework\Database\ORM\Collection;

$collection = new Collection([1, 2, 3]);
$collection = Collection::make([1, 2, 3]);
$collection = Collection::wrap($value);

// From model queries (automatic)
$workflows = Workflow::all();  // Returns Collection
```

#### Iteration and Access

```php
$collection->all();            // Get underlying array
$collection->first();          // First item
$collection->last();           // Last item
$collection->get(0);           // Get by key
$collection->count();          // Count items
$collection->isEmpty();        // Check if empty
$collection->isNotEmpty();     // Check if not empty
```

#### Filtering

```php
// Filter with callback
$filtered = $collection->filter(fn($item) => $item->status === 'active');

// Where conditions
$filtered = $collection->where('status', 'active');
$filtered = $collection->where('count', '>', 10);
$filtered = $collection->whereIn('status', ['active', 'pending']);
$filtered = $collection->whereNotIn('status', ['deleted']);
$filtered = $collection->whereNull('deleted_at');
$filtered = $collection->whereNotNull('published_at');
$filtered = $collection->whereBetween('count', [10, 100]);

// Reject (inverse of filter)
$filtered = $collection->reject(fn($item) => $item->status === 'deleted');
```

#### Transformation

```php
// Map
$names = $collection->map(fn($item) => $item->name);

// Pluck single value
$names = $collection->pluck('name');

// Pluck with key
$namesById = $collection->pluck('name', 'id');

// Group by
$grouped = $collection->groupBy('status');

// Key by
$keyed = $collection->keyBy('id');

// Unique
$unique = $collection->unique('email');
```

#### Sorting

```php
$sorted = $collection->sortBy('name');
$sorted = $collection->sortByDesc('created_at');
$sorted = $collection->sort();
$reversed = $collection->reverse();
```

#### Aggregates

```php
$sum = $collection->sum('amount');
$avg = $collection->avg('score');
$min = $collection->min('price');
$max = $collection->max('price');
$median = $collection->median('score');
```

#### Manipulation

```php
$collection->push($item);         // Add to end
$collection->prepend($item);      // Add to beginning
$item = $collection->pop();       // Remove and return last
$item = $collection->shift();     // Remove and return first
$collection->put('key', $value);  // Set by key
$collection->forget('key');       // Remove by key
$value = $collection->pull('key'); // Remove and return by key
```

#### Combining

```php
$merged = $collection->merge($other);
$concatenated = $collection->concat($other);
$flattened = $collection->flatten();
$collapsed = $collection->collapse();
$diff = $collection->diff($other);
$intersect = $collection->intersect($other);
```

#### Slicing

```php
$first10 = $collection->take(10);
$last5 = $collection->take(-5);
$skipped = $collection->skip(5);
$sliced = $collection->slice(5, 10);
$chunks = $collection->chunk(100);
```

#### Conversion

```php
$array = $collection->toArray();
$json = $collection->toJson();
$string = $collection->implode('name', ', ');
$joined = $collection->join(', ', ' and ');
```

---

### Migrations

Migrations manage database schema changes. They are stored in `includes/database/migrations/`.

#### Migration File Naming

Migrations use timestamp-based naming for ordering:

```
YYYY_MM_DD_NNNNNN_description.php

Examples:
2024_01_01_000001_create_workflows_table.php
2024_01_15_000001_add_priority_to_workflows.php
2024_02_01_000001_create_analytics_table.php
```

#### Existing Migrations

| Migration | Description |
|-----------|-------------|
| `2024_01_01_000001_create_workflows_table.php` | Workflow definitions |
| `2024_01_01_000002_create_workflow_versions_table.php` | Versioned graphs |
| `2024_01_01_000003_create_runs_table.php` | Execution runs |
| `2024_01_01_000004_create_node_runs_table.php` | Node executions |
| `2024_01_01_000005_create_execution_edges_table.php` | Edge tracking |
| `2024_01_01_000006_create_queue_table.php` | Async queue |
| `2024_01_01_000007_create_node_logs_table.php` | Execution logs |
| `2024_01_01_000008_create_connections_table.php` | Credentials |

#### Creating a New Table

```php
<?php
// includes/database/migrations/2024_01_15_000001_create_analytics_table.php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class CreateAnalyticsTable extends Migration
{
    public function up(): void
    {
        Schema::create('analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->string('event_type', 50);
            $table->json('event_data')->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->index('workflow_id');
            $table->index('event_type');
            $table->index(['workflow_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::drop('analytics');
    }
}
```

#### Schema Methods

##### Column Types

```php
$table->id();                              // Auto-incrementing BIGINT primary key
$table->bigInteger('amount');              // BIGINT
$table->unsignedBigInteger('user_id');     // Unsigned BIGINT
$table->integer('count');                  // INT
$table->unsignedInteger('count');          // Unsigned INT
$table->tinyInteger('flag');               // TINYINT
$table->smallInteger('rank');              // SMALLINT
$table->boolean('active');                 // TINYINT(1)
$table->string('name');                    // VARCHAR(255)
$table->string('code', 10);                // VARCHAR(10)
$table->char('code', 4);                   // CHAR(4)
$table->text('content');                   // TEXT
$table->mediumText('content');             // MEDIUMTEXT
$table->longText('content');               // LONGTEXT
$table->json('data');                      // LONGTEXT (JSON alias)
$table->datetime('published_at');          // DATETIME
$table->timestamp('created_at');           // DATETIME (timestamp alias)
$table->enum('status', ['a', 'b', 'c']);   // ENUM
$table->decimal('price', 10, 2);           // DECIMAL(10,2)
$table->float('rate');                     // FLOAT
$table->double('amount');                  // DOUBLE
```

##### Column Modifiers

```php
$table->string('email')->nullable();
$table->string('status')->default('pending');
$table->datetime('created_at')->useCurrent();
$table->datetime('updated_at')->useCurrentOnUpdate();
```

##### Indexes

```php
$table->index('email');
$table->index(['first_name', 'last_name']);
$table->unique('email');
$table->unique(['email', 'account_id']);
```

##### Foreign Keys

```php
$table->foreignId('user_id');
$table->foreign('user_id')
      ->references('id')
      ->on('users')
      ->onDelete('cascade');
```

##### Timestamps and Soft Deletes

```php
$table->timestamps();       // created_at and updated_at
$table->softDeletes();      // deleted_at (nullable datetime)
```

#### Altering Tables (Add/Modify Columns)

To alter an existing table, create a new migration:

```php
<?php
// includes/database/migrations/2024_02_01_000001_add_priority_to_workflows.php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class AddPriorityToWorkflows extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            // Add new columns
            $table->unsignedInteger('priority')->default(0);
            $table->string('category', 100)->nullable();
            $table->json('settings')->nullable();

            // Add index for new column
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('priority');
            $table->dropColumn('category');
            $table->dropColumn('settings');
            $table->dropIndex('zaplane_workflows_priority_index');
        });
    }
}
```

#### Common Alter Operations

```php
Schema::table('my_table', function (Blueprint $table) {
    // Add column after specific column
    $table->string('new_column')->after('existing_column');

    // Add column at beginning
    $table->string('first_column')->first();

    // Modify column type/size
    $table->string('name', 500)->change();

    // Rename column
    $table->renameColumn('old_name', 'new_name');

    // Drop column
    $table->dropColumn('unused_column');

    // Drop multiple columns
    $table->dropColumn(['column1', 'column2']);

    // Add composite index
    $table->index(['column1', 'column2'], 'custom_index_name');

    // Drop index
    $table->dropIndex('index_name');
    $table->dropUnique('unique_name');
    $table->dropForeign('foreign_name');
});
```

#### Schema Helpers

```php
// Check if table exists
if (Schema::hasTable('workflows')) {
    // ...
}

// Check if column exists
if (Schema::hasColumn('workflows', 'status')) {
    // ...
}

// Get column list
$columns = Schema::getColumnListing('workflows');

// Drop table
Schema::drop('old_table');
Schema::dropIfExists('old_table');

// Rename table
Schema::rename('old_name', 'new_name');
```

---

## Controllers and API

Zaplane uses WordPress REST API for frontend communication.

### REST API Controllers

Controllers are stored in `includes/api/` and extend `WP_REST_Controller`.

#### Existing Controllers

| Controller | Endpoint | Description |
|------------|----------|-------------|
| `WorkflowsController` | `/zaplane/v1/workflows` | Workflow CRUD |
| `RunController` | `/zaplane/v1/runs` | Execution runs |
| `ConnectionsController` | `/zaplane/v1/connections` | Integration credentials |
| `IntegrationsController` | `/zaplane/v1/integrations` | Available integrations |

### Creating Controllers

```php
<?php
// includes/api/analytics-controller.php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Zaplane\Models\Analytics;

if (!defined('ABSPATH')) exit;

class AnalyticsController extends WP_REST_Controller
{
    protected string $namespace = 'zaplane/v1';
    protected string $rest_base = 'analytics';

    /**
     * Register routes
     */
    public function register_routes(): void
    {
        // GET /zaplane/v1/analytics
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        // GET/PUT/DELETE /zaplane/v1/analytics/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    /**
     * Permission check - require admin capability
     */
    public function permissions_check(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Get all items
     */
    public function get_items($request): WP_REST_Response
    {
        $items = Analytics::orderBy('id', 'desc')->get();
        return rest_ensure_response($items->toArray());
    }

    /**
     * Get single item
     */
    public function get_item($request)
    {
        $item = Analytics::find((int) $request['id']);

        if (!$item) {
            return new WP_Error('not_found', 'Item not found', ['status' => 404]);
        }

        return rest_ensure_response($item->toArray());
    }

    /**
     * Create item
     */
    public function create_item($request): WP_REST_Response
    {
        $item = Analytics::create([
            'workflow_id' => (int) $request['workflow_id'],
            'event_type'  => sanitize_text_field($request['event_type']),
            'event_data'  => $request['event_data'] ?? [],
        ]);

        return rest_ensure_response(['id' => $item->id]);
    }

    /**
     * Update item
     */
    public function update_item($request)
    {
        $item = Analytics::find((int) $request['id']);

        if (!$item) {
            return new WP_Error('not_found', 'Item not found', ['status' => 404]);
        }

        $item->event_type = sanitize_text_field($request['event_type']);
        $item->event_data = $request['event_data'] ?? $item->event_data;
        $item->save();

        return rest_ensure_response($item->toArray());
    }

    /**
     * Delete item
     */
    public function delete_item($request): WP_REST_Response
    {
        $item = Analytics::find((int) $request['id']);

        if ($item) {
            $item->delete();
        }

        return rest_ensure_response(['deleted' => true]);
    }
}
```

#### Register the Controller

In your plugin bootstrap or `includes/api.php`:

```php
add_action('rest_api_init', function () {
    $controller = new \Zaplane\API\AnalyticsController();
    $controller->register_routes();
});
```

### Ajax Handling

For non-REST Ajax requests, use WordPress admin-ajax:

```php
<?php
// includes/ajax/analytics-ajax.php

namespace Zaplane\Ajax;

if (!defined('ABSPATH')) exit;

class AnalyticsAjax
{
    public function __construct()
    {
        add_action('wp_ajax_zaplane_get_analytics', [$this, 'get_analytics']);
        add_action('wp_ajax_zaplane_save_analytics', [$this, 'save_analytics']);
    }

    /**
     * Handle get analytics request
     */
    public function get_analytics(): void
    {
        // Verify nonce
        check_ajax_referer('zaplane_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $workflow_id = (int) ($_GET['workflow_id'] ?? 0);
        $analytics = \Zaplane\Models\Analytics::where('workflow_id', $workflow_id)->get();

        wp_send_json_success($analytics->toArray());
    }

    /**
     * Handle save analytics request
     */
    public function save_analytics(): void
    {
        check_ajax_referer('zaplane_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $item = \Zaplane\Models\Analytics::create([
            'workflow_id' => (int) ($data['workflow_id'] ?? 0),
            'event_type'  => sanitize_text_field($data['event_type'] ?? ''),
            'event_data'  => $data['event_data'] ?? [],
        ]);

        wp_send_json_success(['id' => $item->id]);
    }
}

// Initialize
new AnalyticsAjax();
```

#### JavaScript Usage

```javascript
// Using REST API
fetch('/wp-json/zaplane/v1/analytics', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(data => console.log(data));

// Using Admin Ajax
jQuery.post(ajaxurl, {
    action: 'zaplane_save_analytics',
    nonce: zaplane.nonce,
    workflow_id: 123,
    event_type: 'view'
}, function(response) {
    console.log(response);
});
```

---

## WP-CLI Commands

Zaplane includes WP-CLI commands for development and maintenance tasks.

### Available Commands

| Command | Description |
|---------|-------------|
| `wp zaplane build:integration` | Generate `integrations.json` manifest from registered integrations |
| `wp zaplane make:integration` | Scaffold a new integration with boilerplate code |
| `wp zaplane test:integration <slug>` | Test an integration's triggers and actions |
| `wp zaplane queue:status` | Show status of pending queue jobs |
| `wp zaplane demo` | Run demo/test functionality |

#### Build Integration Manifest

```bash
# Generate integrations.json for frontend
wp zaplane build:integration

# Output:
# 🔨 Building integrations.json manifest...
#   ✓ wordpress (app) - 67 triggers, 91 actions
#   ✓ slack (app) - 1 triggers, 2 actions
#   ✓ storeengine (app) - 14 triggers, 3 actions
# ✅ Integrations manifest built successfully!
```

#### Scaffold New Integration

```bash
# Interactive mode
wp zaplane make:integration

# With options
wp zaplane make:integration --slug=mailchimp --type=external --auth=api_key --name="Mailchimp"

# Output creates: integrations/mailchimp.php
```

#### Test Integration

```bash
# Test all triggers and actions for an integration
wp zaplane test:integration slack

# Output shows validation results
```

### Creating Commands

Commands are stored in `includes/commands/`. Create a new command:

```php
<?php
// includes/commands/cleanup-command.php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Models\Run;
use Zaplane\Models\NodeLog;

if (!defined('ABSPATH')) exit;

class CleanupCommand extends Command
{
    protected string $signature = 'cleanup';
    protected string $description = 'Clean up old execution logs and runs';

    public function handle(array $args, array $assoc_args): void
    {
        $this->info('Starting cleanup...');

        // Get options
        $days = (int) ($assoc_args['days'] ?? 30);
        $dryRun = isset($assoc_args['dry-run']);

        // Find old runs
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $oldRuns = Run::where('created_at', '<', $cutoff)->get();

        $this->line("Found {$oldRuns->count()} runs older than {$days} days");

        if ($dryRun) {
            $this->warning('Dry run - no changes made');
            return;
        }

        // Confirm deletion
        if (!$this->confirm("Delete {$oldRuns->count()} old runs?")) {
            $this->line('Cancelled');
            return;
        }

        // Delete with progress
        $deleted = 0;
        foreach ($oldRuns as $run) {
            // Delete related logs
            NodeLog::where('run_id', $run->id)->delete();
            $run->delete();
            $deleted++;
        }

        $this->success("Deleted {$deleted} runs and associated logs");

        // Show summary table
        $this->table(
            ['Metric', 'Value'],
            [
                ['Runs Deleted', $deleted],
                ['Cutoff Date', $cutoff],
                ['Days', $days],
            ]
        );
    }
}
```

#### Register the Command

In `includes/framework/core/console.php` or your bootstrap:

```php
use Zaplane\Commands\CleanupCommand;

// Register command
$commands = [
    // ... existing commands
    CleanupCommand::class,
];
```

#### Command Helper Methods

The `Command` base class provides:

```php
// Output methods
$this->info('Informational message');     // Regular log
$this->success('Success message');         // Green success
$this->error('Error message');             // Red error
$this->warning('Warning message');         // Yellow warning
$this->line('Plain text');                 // Plain output
$this->line('');                           // Empty line

// Input methods
$answer = $this->ask('Question?');         // Get text input
$confirmed = $this->confirm('Continue?');  // Yes/no confirmation

// Table output
$this->table(
    ['Column 1', 'Column 2'],
    [
        ['Row 1 Col 1', 'Row 1 Col 2'],
        ['Row 2 Col 1', 'Row 2 Col 2'],
    ]
);
```

#### Running Commands

```bash
# Basic usage
wp zaplane cleanup

# With options
wp zaplane cleanup --days=7

# Dry run
wp zaplane cleanup --days=7 --dry-run
```

---

## Configuration System

The configuration system provides centralized settings management with dot notation support.

### Config Helper

Access configuration values anywhere in your code:

```php
use Zaplane\Framework\Config\Config;

// Get the config instance
$config = Config::getInstance();

// Get values with dot notation
$debug = $config->get('app.debug');
$level = $config->get('logging.level', 'info');  // With default

// Set values
$config->set('app.name', 'My Automation');

// Check existence
if ($config->has('api.rate_limit')) {
    // ...
}

// Array access (alternative syntax)
$debug = $config['app.debug'];
$config['app.name'] = 'My App';
```

#### Using in Integrations

```php
use Zaplane\Framework\Config\Config;

class MyIntegration extends ExternalAppIntegration
{
    public static function execute_node(array $node, array $input): array
    {
        $config = Config::getInstance();

        // Check if logging is enabled
        if ($config->get('logging.enabled')) {
            // Log action
        }

        // Get retry settings
        $maxRetries = $config->get('queue.retry_attempts', 3);

        // ...
    }
}
```

### Config Manager

#### Accessing Configuration

```php
use Zaplane\Framework\Config\Config;

$config = Config::getInstance();

// Get value with dot notation
$debug = $config->get('app.debug');
$level = $config->get('logging.level', 'info'); // With default

// Set value
$config->set('app.name', 'My App');

// Check if key exists
if ($config->has('logging.enabled')) {
    // ...
}
```

#### Default Configuration Structure

```php
[
    'app' => [
        'name'     => 'Zaplane',
        'version'  => '1.0.0',
        'debug'    => false,
        'timezone' => 'UTC',
    ],
    'logging' => [
        'enabled'  => true,
        'level'    => 'debug',
        'channel'  => 'file',
        'channels' => [
            'file' => [
                'driver' => 'file',
                'path'   => '/path/to/logs',
                'days'   => 14,
            ],
            'database' => [
                'driver' => 'database',
                'table'  => 'zaplane_logs',
                'days'   => 30,
            ],
        ],
    ],
    'database' => [
        'prefix'  => 'zaplane_',
        'charset' => 'utf8mb4',
        'collate' => 'utf8mb4_unicode_ci',
    ],
    'cache' => [
        'enabled' => true,
        'driver'  => 'transient',
        'prefix'  => 'zaplane_cache_',
        'ttl'     => 3600,
    ],
    'queue' => [
        'driver'         => 'action_scheduler',
        'retry_attempts' => 3,
        'retry_delay'    => 60,
    ],
    'integrations' => [
        'auto_discover'  => true,
        'cache_enabled'  => true,
    ],
    'api' => [
        'namespace'         => 'zaplane/v1',
        'rate_limit'        => 100,
        'rate_limit_window' => 60,
    ],
]
```

#### Loading Configuration Files

```php
// Load single file
$config->loadFile('/path/to/custom.php', 'custom');

// Load directory of config files
$config->loadDirectory('/path/to/config');

// Load from WordPress options
$config->loadFromOptions('zaplane_settings');
```

#### Array Access

The Config class implements `ArrayAccess`:

```php
$config = Config::getInstance();

// Get
$debug = $config['app.debug'];

// Set
$config['app.name'] = 'My App';

// Check
if (isset($config['logging.enabled'])) {
    // ...
}

// Unset
unset($config['cache.prefix']);
```

#### Persisting Configuration

```php
// Save to WordPress options
$config->saveToOption('zaplane_settings');

// Save specific key
$config->saveToOption('zaplane_api_settings', 'api');
```

#### Environment Detection

```php
$env = $config->getEnvironment();  // 'development', 'staging', 'production'

if ($config->isEnvironment('production')) {
    // ...
}

// Get environment-specific value
$value = $config->environment('production', 'cache.ttl', 3600);
```

### Configuration Repository

For scoped configuration access:

```php
use Zaplane\Framework\Config\Config;
use Zaplane\Framework\Config\Repository;

$config = Config::getInstance();
$logging = new Repository($config, 'logging');

// Access within namespace
$level = $logging->get('level');        // Gets logging.level
$logging->set('enabled', true);         // Sets logging.enabled
$all = $logging->all();                 // Gets all logging config

// Create child repository
$fileConfig = $logging->child('channels.file');
$path = $fileConfig->get('path');       // Gets logging.channels.file.path
```

---

## Automation System

The automation system is the core engine that executes workflows. For detailed documentation on how the automation system works, including:

- Trigger event routing
- Node execution flow
- Credential injection
- Rate limiting
- Retry logic with exponential backoff
- Complex workflow scenarios

**See: [Automation System Documentation](./automation-system.md)**

---

## Best Practices

### Integration Development

1. **One file per trigger/action**: Keeps code organized and testable
2. **Use type hints**: PHP 7.4+ supports property type hints
3. **Validate inputs**: Always validate configuration in `execute()`
4. **Return proper responses**: Use `success()` and `branch()` helpers
5. **Document output schema**: Helps users understand available variables

### ORM Usage

1. **Use fillable/guarded**: Protect against mass assignment
2. **Cast types**: Use `$casts` for automatic type conversion
3. **Index frequently queried columns**: Improves performance
4. **Use eager loading patterns**: Avoid N+1 queries

### Configuration

1. **Use Config class**: `Config::getInstance()->get('app.debug')`
2. **Provide defaults**: Always pass a default value when getting config
3. **Group related settings**: Use nested configuration structure
4. **Don't store secrets in config files**: Use WordPress options or environment variables

### Commands

1. **Use descriptive signatures**: `cleanup:logs` not `cl`
2. **Provide dry-run options**: Allow testing without changes
3. **Show progress**: Use `$this->line()` for updates
4. **Confirm destructive actions**: Use `$this->confirm()`

---
