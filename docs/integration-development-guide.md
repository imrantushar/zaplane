# Zaplane Integration Development Guide

This guide teaches you how to build integrations for Zaplane. There are two types:

1. **External App Integration** — connects to third-party services (Slack, Mailchimp, Stripe, etc.)
2. **WordPress Plugin Integration** — hooks into WordPress plugins (Gravity Forms, WooCommerce, ACF, etc.)

---

## Table of Contents

- [Quick Start](#quick-start)
- [Architecture Overview](#architecture-overview)
- [External App Integration](#external-app-integration)
  - [Scaffolding](#scaffolding-an-external-app)
  - [Authentication](#authentication)
  - [Defining Actions](#defining-actions)
  - [Defining Triggers](#defining-triggers)
  - [Executing Actions](#executing-actions)
  - [Resolving Triggers](#resolving-triggers)
  - [Testing Connection](#testing-connection)
  - [Full Example: Mailchimp](#full-example-mailchimp)
- [WordPress Plugin Integration](#wordpress-plugin-integration)
  - [Scaffolding](#scaffolding-a-wordpress-plugin)
  - [Using Helper Methods](#using-helper-methods)
  - [Full Example: Gravity Forms](#full-example-gravity-forms)
- [Config Schema (UI Fields)](#config-schema-ui-fields)
  - [Field Types](#field-types)
  - [Dynamic Fields](#dynamic-fields)
  - [Builder Pattern (Optional)](#builder-pattern-optional)
- [Variable Substitution](#variable-substitution)
- [ExecutionContext](#executioncontext)
- [API Request Helper](#api-request-helper)
- [Rate Limiting](#rate-limiting)
- [Error Handling](#error-handling)
- [Retry Mechanism](#retry-mechanism)
- [Logging](#logging)
- [Database Migrations](#database-migrations)
- [CLI Commands](#cli-commands)
- [Testing Your Integration](#testing-your-integration)
- [File Conventions](#file-conventions)
- [Checklist Before Submitting](#checklist-before-submitting)

---

## Quick Start

The fastest way to create an integration:

```bash
# Scaffold a new external app integration
wp zaplane make:integration --slug=mailchimp --type=external --auth=api_key

# Scaffold a new WordPress plugin integration
wp zaplane make:integration --slug=gravity-forms --type=wordpress
```

This generates a ready-to-edit file in `integrations/`. No registry editing needed — Zaplane auto-discovers it.

After writing your code:

```bash
# Validate your integration
wp zaplane test:integration mailchimp

# Rebuild the frontend manifest
wp zaplane build:integration
```

---

## Architecture Overview

```
Workflow Execution Flow:

  WordPress Hook fires
        |
        v
  Automation Engine (automation.php)
        |
        v
  resolve_trigger() — your integration extracts data from the hook
        |
        v
  Creates a Run + NodeRun records
        |
        v
  For each action node in the graph:
    1. Engine injects connection credentials automatically
    2. Engine resolves {{variables}} in your config automatically
    3. Engine attaches ExecutionContext
    4. Engine calls YOUR execute_node()
    5. Engine handles retries, logging, error handling
        |
        v
  Output flows to the next node in the graph
```

**What the engine handles for you:**
- Credential decryption and injection
- Variable substitution (`{{post.post_title}}`)
- Retry with exponential backoff (3 attempts by default)
- Logging to `node_logs` table
- Rate limiting per integration
- Queue management via Action Scheduler

**What you implement:**
- `get_triggers()` / `get_actions()` — define what your integration can do
- `resolve_trigger()` — extract data when a WordPress hook fires
- `execute_node()` — call your API or perform your action
- `test_connection()` — verify credentials work (external apps only)

---

## External App Integration

Use this for any service that requires an API connection: Slack, Mailchimp, Stripe, Trello, SendGrid, etc.

### Scaffolding an External App

```bash
wp zaplane make:integration --slug=sendgrid --type=external --auth=api_key
```

This creates `integrations/sendgrid.php` extending `ExternalAppIntegration`.

### Authentication

Your integration must declare its auth type. The engine uses this to show the correct UI fields and manage credentials.

**API Key Auth** (most common):

```php
public static function get_auth_type(): string {
    return 'api_key';
}

public static function get_auth_fields(?string $auth_type = null): array {
    return [
        'api_key' => [
            'type'        => 'password',
            'label'       => 'API Key',
            'placeholder' => 'SG.xxxxxxxxxxxx',
            'required'    => true,
            'help'        => 'Find this at Settings > API Keys in your SendGrid dashboard',
        ],
    ];
}
```

**OAuth 2.0 Auth:**

```php
public static function get_auth_type(): string {
    return 'oauth2';
}

public static function get_auth_fields(?string $auth_type = null): array {
    return [
        'client_id' => [
            'type'     => 'text',
            'label'    => 'Client ID',
            'required' => true,
        ],
        'client_secret' => [
            'type'     => 'password',
            'label'    => 'Client Secret',
            'required' => true,
        ],
    ];
}

public static function get_oauth_scopes(): array {
    return ['read', 'write'];
}

public static function get_oauth_auth_url(string $redirect_uri, string $state, array $creds = []): ?string {
    $params = [
        'client_id'     => $creds['client_id'] ?? '',
        'redirect_uri'  => $redirect_uri,
        'state'         => $state,
        'response_type' => 'code',
        'scope'         => implode(' ', static::get_oauth_scopes()),
    ];
    return 'https://app.example.com/oauth/authorize?' . http_build_query($params);
}

public static function exchange_oauth_code(string $code, string $redirect_uri, array $creds = []): array {
    $response = wp_remote_post('https://app.example.com/oauth/token', [
        'body' => [
            'grant_type'    => 'authorization_code',
            'client_id'     => $creds['client_id'] ?? '',
            'client_secret' => $creds['client_secret'] ?? '',
            'code'          => $code,
            'redirect_uri'  => $redirect_uri,
        ],
    ]);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return [
        'access_token'  => $body['access_token'] ?? '',
        'refresh_token' => $body['refresh_token'] ?? '',
        'expires_in'    => $body['expires_in'] ?? 3600,
    ];
}

public static function refresh_oauth_token(string $refresh_token): array {
    // Similar to exchange_oauth_code but with grant_type=refresh_token
}
```

**Basic Auth:**

```php
public static function get_auth_type(): string {
    return 'basic';
}

public static function get_auth_fields(?string $auth_type = null): array {
    return [
        'username' => ['type' => 'text', 'label' => 'Username', 'required' => true],
        'password' => ['type' => 'password', 'label' => 'Password', 'required' => true],
    ];
}
```

**Both (API Key + OAuth):**

```php
public static function get_auth_type(): string {
    return 'both';
}

public static function get_available_auth_types(): array {
    return [
        'oauth2'  => ['label' => 'OAuth 2.0', 'description' => 'Recommended'],
        'api_key' => ['label' => 'API Key', 'description' => 'Use a token directly'],
    ];
}

public static function get_auth_fields(?string $auth_type = null): array {
    if ($auth_type === 'oauth2') {
        return ['client_id' => [...], 'client_secret' => [...]];
    }
    if ($auth_type === 'api_key') {
        return ['api_key' => [...]];
    }
    // Return all if no specific type requested
    return ['oauth2' => [...], 'api_key' => [...]];
}
```

### Defining Actions

Actions are operations your integration can perform (send email, create record, etc.).

```php
public static function get_actions(): array {
    return [
        'send_email'     => ['label' => 'Send Email'],
        'add_contact'    => ['label' => 'Add Contact to List'],
        'create_campaign'=> ['label' => 'Create Campaign'],
    ];
}
```

Each action needs a config schema to define the UI fields:

```php
public static function get_action_config_schema(string $action): array {
    if ($action === 'send_email') {
        return [
            'to'      => ['type' => 'text', 'label' => 'To Email', 'required' => true],
            'subject' => ['type' => 'text', 'label' => 'Subject', 'required' => true],
            'body'    => ['type' => 'textarea', 'label' => 'Email Body', 'required' => true],
        ];
    }

    if ($action === 'add_contact') {
        return [
            'list_id' => ['type' => 'text', 'label' => 'List ID', 'required' => true],
            'email'   => ['type' => 'text', 'label' => 'Email', 'required' => true],
            'name'    => ['type' => 'text', 'label' => 'Name'],
        ];
    }

    return [];
}
```

### Defining Triggers

Triggers are events that start a workflow. For external apps, they typically use webhooks.

```php
public static function get_triggers(): array {
    return [
        'email_opened' => [
            'label' => 'Email Opened',
            'hook'  => 'sendgrid_webhook_email_opened', // WordPress hook name
        ],
        'email_bounced' => [
            'label' => 'Email Bounced',
            'hook'  => 'sendgrid_webhook_email_bounced',
        ],
    ];
}
```

The `hook` value is the WordPress action that gets registered. When that action fires, the engine calls your `resolve_trigger()`.

### Executing Actions

This is where you call your API. The engine has already:
- Injected credentials at `$node['_connection_credentials']`
- Resolved `{{variables}}` in config values
- Attached an `ExecutionContext` at `$node['_context']`

```php
public static function execute_node(array $node, array $input): array {
    $action = $node['config']['action'] ?? $node['data']['event'] ?? '';
    $config = $node['config']['data'] ?? $node['data']['config'] ?? [];
    $creds  = $node['_connection_credentials'] ?? [];

    if ($action === 'send_email') {
        return self::action_send_email($config, $input, $creds);
    }

    if ($action === 'add_contact') {
        return self::action_add_contact($config, $input, $creds);
    }

    return ['port' => 'main', 'data' => $input];
}

private static function action_send_email(array $config, array $input, array $creds): array {
    $result = self::api_request('POST', 'https://api.sendgrid.com/v3/mail/send', [
        'personalizations' => [['to' => [['email' => $config['to']]]]],
        'from'    => ['email' => 'noreply@example.com'],
        'subject' => $config['subject'],
        'content' => [['type' => 'text/html', 'value' => $config['body']]],
    ], $creds);

    return [
        'port' => 'main',
        'data' => array_merge($input, [
            'email_sent'    => true,
            'email_to'      => $config['to'],
            'email_subject' => $config['subject'],
        ]),
    ];
}
```

**Return format** — always return this structure:

```php
return [
    'port' => 'main',   // Output port name. Use 'main' for standard flow.
    'data' => [...],     // Data passed to the next node. Always merge with $input.
];
```

### Resolving Triggers

When a WordPress hook fires, the engine calls your `resolve_trigger()` to extract meaningful data.

```php
public static function resolve_trigger(array $node, array $args) {
    $event = $node['event'] ?? '';

    if ($event === 'email_opened') {
        $webhook_data = $args[0] ?? [];
        return [
            'email'     => $webhook_data['email'] ?? '',
            'timestamp' => $webhook_data['timestamp'] ?? '',
            'campaign'  => $webhook_data['sg_message_id'] ?? '',
        ];
    }

    return false; // Return false to skip (don't start a workflow run)
}
```

**Important:** Return `false` to indicate the event should be ignored (e.g., doesn't match filters). Return an array to start a workflow run with that data as the trigger payload.

### Testing Connection

Implement a lightweight API call that verifies the credentials work.

```php
public static function test_connection(array $credentials): array {
    try {
        $response = self::api_request('GET', 'https://api.sendgrid.com/v3/user/profile', [], $credentials);

        return [
            'success' => true,
            'message' => 'Connected as ' . ($response['username'] ?? 'Unknown'),
            'details' => [
                'username' => $response['username'] ?? '',
                'email'    => $response['email'] ?? '',
            ],
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'details' => [],
        ];
    }
}
```

### Full Example: Mailchimp

```php
<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\ExternalAppIntegration;

if (!defined('ABSPATH')) exit;

class Mailchimp extends ExternalAppIntegration {

    public static function get_slug(): string { return 'mailchimp'; }
    public static function get_name(): string { return 'Mailchimp'; }
    public static function get_icon(): string { return 'mailchimp'; }

    public static function get_auth_type(): string { return 'api_key'; }

    public static function get_auth_fields(?string $auth_type = null): array {
        return [
            'api_key' => [
                'type'        => 'password',
                'label'       => 'API Key',
                'placeholder' => 'xxxxxxxx-us1',
                'required'    => true,
                'help'        => 'Account > Extras > API Keys',
            ],
        ];
    }

    /**
     * Mailchimp API keys contain the data center: key-us1
     */
    private static function get_base_url(array $creds): string {
        $key = $creds['api_key'] ?? '';
        $dc  = substr(strrchr($key, '-'), 1) ?: 'us1';
        return "https://{$dc}.api.mailchimp.com/3.0";
    }

    protected static function build_auth_header(array $credentials): ?string {
        // Mailchimp uses Basic auth with any username + API key as password
        return 'Basic ' . base64_encode('zaplane:' . ($credentials['api_key'] ?? ''));
    }

    public static function test_connection(array $credentials): array {
        try {
            $result = self::api_request('GET', self::get_base_url($credentials) . '/', [], $credentials);
            return [
                'success' => true,
                'message' => 'Connected to account: ' . ($result['account_name'] ?? ''),
                'details' => $result,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'details' => []];
        }
    }

    public static function get_actions(): array {
        return [
            'add_subscriber' => ['label' => 'Add Subscriber'],
            'remove_subscriber' => ['label' => 'Remove Subscriber'],
        ];
    }

    public static function get_action_config_schema(string $action): array {
        if ($action === 'add_subscriber') {
            return [
                'list_id' => ['type' => 'text', 'label' => 'List/Audience ID', 'required' => true],
                'email'   => ['type' => 'text', 'label' => 'Email Address', 'required' => true],
                'fname'   => ['type' => 'text', 'label' => 'First Name'],
                'lname'   => ['type' => 'text', 'label' => 'Last Name'],
            ];
        }
        if ($action === 'remove_subscriber') {
            return [
                'list_id' => ['type' => 'text', 'label' => 'List/Audience ID', 'required' => true],
                'email'   => ['type' => 'text', 'label' => 'Email Address', 'required' => true],
            ];
        }
        return [];
    }

    public static function execute_node(array $node, array $input): array {
        $action = $node['config']['action'] ?? $node['data']['event'] ?? '';
        $config = $node['config']['data'] ?? $node['data']['config'] ?? [];
        $creds  = $node['_connection_credentials'] ?? [];
        $base   = self::get_base_url($creds);

        if ($action === 'add_subscriber') {
            $hash = md5(strtolower($config['email']));
            $result = self::api_request('PUT',
                "{$base}/lists/{$config['list_id']}/members/{$hash}",
                [
                    'email_address' => $config['email'],
                    'status_if_new' => 'subscribed',
                    'merge_fields'  => [
                        'FNAME' => $config['fname'] ?? '',
                        'LNAME' => $config['lname'] ?? '',
                    ],
                ],
                $creds
            );
            return [
                'port' => 'main',
                'data' => array_merge($input, [
                    'mailchimp_id'     => $result['id'] ?? '',
                    'mailchimp_status' => $result['status'] ?? '',
                ]),
            ];
        }

        if ($action === 'remove_subscriber') {
            $hash = md5(strtolower($config['email']));
            self::api_request('DELETE',
                "{$base}/lists/{$config['list_id']}/members/{$hash}",
                [],
                $creds
            );
            return [
                'port' => 'main',
                'data' => array_merge($input, ['mailchimp_removed' => true]),
            ];
        }

        return ['port' => 'main', 'data' => $input];
    }

    public static function get_rate_limit(): int {
        return 10; // Mailchimp allows 10 requests/second, we limit per minute
    }
}
```

---

## WordPress Plugin Integration

Use this for any WordPress plugin that runs locally: Gravity Forms, ACF, WooCommerce, Contact Form 7, etc.

No external connection or credentials needed.

### Scaffolding a WordPress Plugin

```bash
wp zaplane make:integration --slug=gravity-forms --type=wordpress
```

Creates `integrations/gravity-forms.php` extending `WordPressPluginIntegration`.

### Using Helper Methods

`WordPressPluginIntegration` provides pre-built data resolvers:

```php
// Get full post data from a post ID
$payload = self::resolve_post($post_id);
// Returns: ['post_id', 'post_title', 'post_content', 'post_excerpt', 'post_type',
//           'post_status', 'post_author', 'post_date', 'permalink']

// Get full user data from a user ID
$payload = self::resolve_user($user_id);
// Returns: ['user_id', 'user_login', 'user_email', 'display_name',
//           'first_name', 'last_name', 'roles', 'registered']

// Get comment data
$payload = self::resolve_comment($comment_id);

// Get term data
$payload = self::resolve_term($term_id, 'category');

// Get attachment/media data
$payload = self::resolve_attachment($attachment_id);
```

### Full Example: Gravity Forms

```php
<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\WordPressPluginIntegration;

if (!defined('ABSPATH')) exit;

class GravityForms extends WordPressPluginIntegration {

    public static function get_slug(): string { return 'gravity-forms'; }
    public static function get_name(): string { return 'Gravity Forms'; }
    public static function get_icon(): string { return 'gravity-forms'; }

    public static function get_triggers(): array {
        return [
            'form_submitted' => [
                'label' => 'Form Submitted',
                'hook'  => 'gform_after_submission',
            ],
            'form_field_validated' => [
                'label' => 'Form Validation Failed',
                'hook'  => 'gform_validation',
            ],
        ];
    }

    public static function get_trigger_config_schema(string $trigger): array {
        if ($trigger === 'form_submitted') {
            return [
                [
                    'key'      => 'form_id',
                    'label'    => 'Form',
                    'type'     => 'select',
                    'dynamic'  => [
                        'integration' => 'gravity-forms',
                        'query'       => 'forms',
                        'select'      => ['id', 'title'],
                    ],
                    'required' => false, // Empty = any form
                ],
            ];
        }
        return [];
    }

    public static function resolve_trigger(array $node, array $args) {
        $event = $node['event'] ?? '';

        if ($event === 'form_submitted') {
            $entry = $args[0] ?? null;
            $form  = $args[1] ?? null;
            if (!$entry || !$form) return false;

            // Filter by form_id if configured
            $config_form_id = $node['config']['data']['form_id'] ?? '';
            if ($config_form_id && (string) $form['id'] !== (string) $config_form_id) {
                return false; // Skip — doesn't match the configured form
            }

            return [
                'entry_id'   => $entry['id'] ?? '',
                'form_id'    => $form['id'] ?? '',
                'form_title' => $form['title'] ?? '',
                'fields'     => $entry,
                'source_url' => $entry['source_url'] ?? '',
                'user_ip'    => $entry['ip'] ?? '',
                'created_at' => $entry['date_created'] ?? '',
            ];
        }

        return false;
    }

    public static function get_actions(): array {
        return [
            'create_entry' => ['label' => 'Create Form Entry'],
        ];
    }

    public static function get_action_config_schema(string $action): array {
        if ($action === 'create_entry') {
            return [
                ['key' => 'form_id', 'label' => 'Form ID', 'type' => 'expression', 'required' => true],
                ['key' => 'field_values', 'label' => 'Field Values (JSON)', 'type' => 'textarea', 'required' => true],
            ];
        }
        return [];
    }

    public static function execute_node(array $node, array $input): array {
        $config = $node['data']['config'] ?? [];
        $event  = $node['data']['event'] ?? '';

        if ($event === 'create_entry') {
            if (!function_exists('GFAPI')) {
                throw new \Exception('Gravity Forms is not active');
            }
            $field_values = json_decode($config['field_values'] ?? '{}', true);
            $field_values['form_id'] = (int) ($config['form_id'] ?? 0);

            $entry_id = \GFAPI::add_entry($field_values);
            if (is_wp_error($entry_id)) {
                throw new \Exception('Failed to create entry: ' . $entry_id->get_error_message());
            }

            return [
                'port' => 'main',
                'data' => array_merge($input, [
                    'gf_entry_id' => $entry_id,
                    'gf_form_id'  => $config['form_id'],
                ]),
            ];
        }

        return ['port' => 'main', 'data' => $input];
    }

    public static function get_dynamic_queries(): array {
        return [
            'forms' => [self::class, 'query_forms'],
        ];
    }

    public static function query_forms(): array {
        if (!function_exists('GFAPI')) return [];
        $forms = \GFAPI::get_forms();
        return array_map(fn($f) => ['id' => $f['id'], 'title' => $f['title']], $forms);
    }
}
```

---

## Config Schema (UI Fields)

Config schemas define what fields appear in the workflow editor UI when a user configures a trigger or action.

### Field Types

| Type | Description | Example |
|---|---|---|
| `text` | Single-line text input | Email, URL, Name |
| `textarea` | Multi-line text input | Message body, JSON |
| `password` | Masked text input | API keys, tokens |
| `number` | Numeric input | Count, limit |
| `boolean` | Toggle/checkbox | Enable/disable flags |
| `select` | Dropdown single-select | Post type, status |
| `multiselect` | Dropdown multi-select | Features, tags |
| `expression` | Text input with variable picker | Dynamic values like `{{post.post_title}}` |
| `datetime` | Date and time picker | Schedule dates |

### Field Definition Structure

```php
[
    'key'         => 'email',              // Required. Internal key.
    'label'       => 'Email Address',      // Required. Display label.
    'type'        => 'text',               // Required. Field type.
    'required'    => true,                 // Optional. Default: false.
    'placeholder' => 'user@example.com',   // Optional. Placeholder text.
    'help'        => 'The recipient email', // Optional. Help text.
    'default'     => '',                   // Optional. Default value.
    'options'     => [                     // For select/multiselect only.
        ['label' => 'Draft', 'value' => 'draft'],
        ['label' => 'Publish', 'value' => 'publish'],
    ],
    'dynamic'     => [                     // For dynamic select options.
        'integration' => 'wordpress',
        'query'       => 'post_types',
        'select'      => ['name', 'label'],
    ],
]
```

### Dynamic Fields

Dynamic fields load their options from a query method at runtime. Define the query in your integration:

```php
public static function get_dynamic_queries(): array {
    return [
        'lists' => [self::class, 'query_lists'],
    ];
}

public static function query_lists(): array {
    // Return array of options
    return [
        ['id' => 'list_1', 'name' => 'Newsletter'],
        ['id' => 'list_2', 'name' => 'Customers'],
    ];
}
```

Reference it in your config schema:

```php
'list_id' => [
    'type'    => 'select',
    'label'   => 'List',
    'dynamic' => [
        'integration' => 'your-slug',
        'query'       => 'lists',
        'select'      => ['id', 'name'],  // [value_field, label_field]
    ],
]
```

### Builder Pattern (Optional)

For a more fluent API, use the builder classes:

```php
use Zaplane\Framework\Classes\TriggerDefinition;
use Zaplane\Framework\Classes\ActionDefinition;

// In get_triggers():
return array_merge(
    TriggerDefinition::make('form_submitted', 'Form Submitted')
        ->hook('gform_after_submission')
        ->description('Fires when a Gravity Forms submission is completed')
        ->output(['entry_id' => 'integer', 'form_id' => 'integer', 'fields' => 'object'])
        ->configField('form_id', 'select', 'Form', dynamic: [
            'integration' => 'gravity-forms',
            'query' => 'forms',
            'select' => ['id', 'title'],
        ])
        ->toArray(),
);

// In get_actions():
return array_merge(
    ActionDefinition::make('create_entry', 'Create Form Entry')
        ->description('Creates a new entry in a Gravity Forms form')
        ->field('form_id', 'expression', 'Form ID', required: true)
        ->field('field_values', 'textarea', 'Field Values (JSON)', required: true)
        ->output(['gf_entry_id' => 'integer'])
        ->toArray(),
);
```

Both approaches produce the same result. Use whichever you prefer. The raw array approach is simpler for small integrations; the builder pattern is better for integrations with many triggers/actions.

---

## Variable Substitution

The engine automatically resolves `{{variable}}` placeholders in all config fields before your `execute_node()` is called.

**You do NOT need to implement variable substitution.** It is handled by `DataMapper`.

Examples of what users can enter in config fields:

```
Hello {{post_title}}, your post was published!
User email: {{user_email}}
Nested access: {{post.post_title}}
Deep nesting: {{order.billing.email}}
```

If a variable cannot be resolved, the original `{{token}}` is kept as-is.

---

## ExecutionContext

Every node execution receives an `ExecutionContext` object at `$node['_context']`. This provides structured access to runtime data:

```php
public static function execute_node(array $node, array $input): array {
    $context = $node['_context'] ?? null;

    if ($context) {
        // Access data
        $run_id = $context->runId();
        $input_data = $context->input();
        $creds = $context->credentials();
        $config = $context->config();

        // Get specific values
        $email = $context->get('user.email', 'default@example.com');
        $list_id = $context->configValue('list_id');

        // Logging (writes to node_logs table)
        $context->info('Starting API call to Mailchimp');
        $context->warning('No first name provided, using default');
        $context->error('API returned 429 Too Many Requests');
        $context->debug('Request body: ' . json_encode($body));
    }

    // You can still use the traditional approach:
    $creds = $node['_connection_credentials'] ?? [];
    $config = $node['config']['data'] ?? [];

    // ... your logic
}
```

**ExecutionContext is optional.** The traditional `$node` array approach still works. Use whichever you prefer.

---

## API Request Helper

`ExternalAppIntegration` provides a built-in `api_request()` helper:

```php
$result = self::api_request(
    'POST',                                    // HTTP method
    'https://api.example.com/v1/contacts',     // Full URL
    ['email' => 'user@example.com'],           // Body (auto JSON-encoded)
    $credentials,                               // Decrypted credentials
    ['X-Custom-Header' => 'value'],            // Extra headers (optional)
    ['timeout' => 60]                          // Extra wp_remote options (optional)
);
```

**What it does automatically:**
- Builds the Authorization header from credentials (Bearer token, Basic auth)
- JSON-encodes the body
- Handles WP_Error responses
- Throws exceptions on HTTP 4xx/5xx with the error message from the API
- Returns the decoded JSON response body

**Override auth header format** — if your API uses a non-standard auth scheme:

```php
protected static function build_auth_header(array $credentials): ?string {
    // Mailchimp uses Basic auth with API key as password
    return 'Basic ' . base64_encode('anystring:' . ($credentials['api_key'] ?? ''));
}
```

---

## Rate Limiting

Declare a rate limit to prevent hitting API quotas:

```php
public static function get_rate_limit(): int {
    return 60; // Maximum 60 requests per minute. 0 = unlimited.
}
```

The engine tracks request counts per integration per minute using WordPress transients. When the limit is reached, the node execution is deferred and retried automatically.

---

## Error Handling

Throw exceptions from `execute_node()` when something goes wrong. The engine catches them and handles retries.

```php
public static function execute_node(array $node, array $input): array {
    $creds = $node['_connection_credentials'] ?? [];

    if (empty($creds)) {
        throw new \Exception('No credentials available for ' . static::get_name());
    }

    $response = self::api_request('POST', self::API_URL . '/send', $body, $creds);

    if (empty($response['id'])) {
        throw new \Exception('API did not return an ID');
    }

    return ['port' => 'main', 'data' => array_merge($input, $response)];
}
```

**Do not** catch exceptions and return success. Let them propagate so the engine can log and retry.

---

## Retry Mechanism

The engine automatically retries failed nodes:

- **Default:** 3 attempts
- **Backoff:** Exponential — 30s, 60s, 120s
- **After all retries fail:** Node is marked as `failed` permanently

The engine handles this entirely. You just throw exceptions and the retry logic kicks in.

If your action is **not idempotent** (e.g., creating a record that should not be duplicated), add your own duplicate check:

```php
private static function action_create_record(array $config, array $input, array $creds): array {
    // Check if record already exists to prevent duplicates on retry
    $existing = self::api_request('GET', self::API_URL . '/records?email=' . $config['email'], [], $creds);

    if (!empty($existing['items'])) {
        return ['port' => 'main', 'data' => array_merge($input, $existing['items'][0])];
    }

    $result = self::api_request('POST', self::API_URL . '/records', $config, $creds);
    return ['port' => 'main', 'data' => array_merge($input, $result)];
}
```

---

## Logging

Logs are written to the `node_logs` table automatically by the engine at key points:
- Node execution started
- Node executed successfully
- Node failed (with attempt count)
- Retry scheduled
- Node permanently failed

You can add your own logs using the `ExecutionContext`:

```php
$context = $node['_context'] ?? null;
if ($context) {
    $context->info('Sending email to ' . $config['to']);
    $context->debug('API response: ' . json_encode($response));
}
```

Or directly via the NodeRun (less common):

```php
// The NodeRun is accessible via context
$context->nodeRun()->log('info', 'Custom log message');
```

---

## Database Migrations

Zaplane uses a Laravel-style migration system. Migrations live in `includes/database/migrations/`.

### Creating Migrations

```bash
# Create a new table
wp zaplane make:migration create_tags_table --create=tags

# Alter an existing table (auto-detected from name)
wp zaplane make:migration add_description_to_workflows --table=workflows

# The command auto-detects intent from the name:
#   create_*       → CREATE TABLE template
#   add_*_to_*     → ALTER TABLE template
#   remove_*_from_* → ALTER TABLE template
#   drop_*         → DROP TABLE template
```

### Writing Alter Migrations

```php
use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

class AddDescriptionToWorkflows extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('category', 50)->nullable();
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->dropColumn('category');
        });
    }
}
```

### Modifying a Column

Use `change()` to modify an existing column's type or attributes:

```php
Schema::table('runs', function (Blueprint $table) {
    $table->text('status')->change();  // change varchar to text
});
```

### Dropping Indexes and Foreign Keys

```php
Schema::table('runs', function (Blueprint $table) {
    $table->dropForeign('fk_runs_workflow_id');
    $table->dropUnique('uq_runs_hash');
});
```

### Running Migrations

```bash
wp zaplane migrate          # Run pending migrations
wp zaplane migrate:status   # Show migration status
wp zaplane migrate:fresh    # Drop all tables and re-run all migrations
```

---

## CLI Commands

| Command | Description |
|---|---|
| `wp zaplane make:integration` | Scaffold a new integration file |
| `wp zaplane make:migration <name>` | Create a new migration file |
| `wp zaplane migrate` | Run pending migrations |
| `wp zaplane migrate:status` | Show migration status |
| `wp zaplane migrate:fresh` | Drop all and re-migrate |
| `wp zaplane test:integration <slug>` | Validate integration structure |
| `wp zaplane test:integration --all` | Validate all integrations |
| `wp zaplane test:integration <slug> --test-connection=<id>` | Test a specific connection |
| `wp zaplane build:integration` | Rebuild the frontend JSON manifest |
| `wp zaplane queue:status` | Show queue health and job stats |

---

## Testing Your Integration

### 1. Syntax check

```bash
php -l integrations/your-integration.php
```

### 2. Structural validation

```bash
wp zaplane test:integration your-slug
```

This checks:
- `get_slug()` matches the registered slug
- `get_name()` is not empty
- Auth methods are implemented if `requires_connection()` is true
- All triggers have a `hook` field
- All actions have a `label` field
- `resolve_trigger()` is overridden if triggers exist
- `execute_node()` is overridden if actions exist

### 3. Connection test (external apps only)

Create a connection in the Zaplane admin UI, then:

```bash
wp zaplane test:integration your-slug --test-connection=1
```

### 4. End-to-end test

1. Create a workflow in the Zaplane admin
2. Add your trigger/action node
3. Configure it
4. Activate the workflow
5. Trigger the event (publish a post, submit a form, etc.)
6. Check the Logs page for execution results

---

## File Conventions

| Convention | Example |
|---|---|
| **File location** | `integrations/your-slug.php` |
| **File name** | Kebab-case matching the slug: `gravity-forms.php` |
| **Class name** | PascalCase: `GravityForms` |
| **Namespace** | `Zaplane\Integrations` |
| **Slug** | Lowercase, hyphen-separated: `gravity-forms` |
| **Base class** | `ExternalAppIntegration` or `WordPressPluginIntegration` |

The autoloader converts kebab-case filenames to PascalCase class names automatically. The integration loader auto-discovers files in the `integrations/` directory.

---

## Checklist Before Submitting

- [ ] File is in `integrations/` directory with kebab-case filename
- [ ] Class extends `ExternalAppIntegration` or `WordPressPluginIntegration`
- [ ] `get_slug()` returns a unique, lowercase slug
- [ ] `get_name()` returns a human-readable name
- [ ] `get_icon()` returns an icon identifier
- [ ] **External apps only:**
  - [ ] `get_auth_type()` returns correct type
  - [ ] `get_auth_fields()` returns field definitions
  - [ ] `test_connection()` makes a real API call
- [ ] All triggers have both `label` and `hook` fields
- [ ] All actions have a `label` field
- [ ] `get_action_config_schema()` returns fields for each action
- [ ] `resolve_trigger()` is implemented if triggers exist
- [ ] `execute_node()` is implemented if actions exist
- [ ] `execute_node()` always returns `['port' => 'main', 'data' => [...]]`
- [ ] Errors are thrown as exceptions (not swallowed)
- [ ] `php -l` passes with no syntax errors
- [ ] `wp zaplane test:integration your-slug` passes
- [ ] `wp zaplane build:integration` rebuilds successfully
