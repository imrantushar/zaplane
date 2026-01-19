# Creating Custom Integrations

Learn how to build custom integrations for the Zaplane workflow automation system.

---

## Overview

Integrations connect Zaplane workflows to external services like Slack, email providers, databases, and APIs. This guide walks you through creating a custom integration from scratch.

---

## Integration Structure

Every integration extends the `IntegrationBase` class and provides:

1. **Metadata** - Name, description, icon, category
2. **Actions** - Things the integration can do (send message, create record, etc.)
3. **Triggers** - Events that start workflows (new email, webhook, schedule, etc.)
4. **Authentication** - How users connect (API key, OAuth, etc.)

---

## Step 1: Create Integration File

Create a new file in `includes/integrations/`:

```php
<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Core\IntegrationBase;

class MyServiceIntegration extends IntegrationBase
{
    public function getSlug(): string
    {
        return 'my-service';
    }

    public function getName(): string
    {
        return 'My Service';
    }

    public function getDescription(): string
    {
        return 'Connect to My Service to automate tasks';
    }

    public function getIcon(): string
    {
        return 'https://example.com/icon.png';
    }

    public function getCategory(): string
    {
        return 'communication'; // or 'productivity', 'crm', 'ecommerce', etc.
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
}
```

---

## Step 2: Define Authentication

### API Key Authentication

```php
public function getAuthType(): string
{
    return 'api_key';
}

public function getAuthFields(): array
{
    return [
        [
            'key' => 'api_key',
            'label' => 'API Key',
            'type' => 'text',
            'required' => true,
            'placeholder' => 'Enter your API key',
            'help' => 'Find your API key in Settings > API'
        ]
    ];
}

public function testConnection(array $credentials): array
{
    $apiKey = $credentials['api_key'] ?? '';

    if (empty($apiKey)) {
        return [
            'success' => false,
            'message' => 'API key is required'
        ];
    }

    try {
        // Test API call
        $response = wp_remote_get('https://api.myservice.com/v1/verify', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return [
                'success' => true,
                'message' => 'Connected successfully',
                'data' => [
                    'account_name' => $body['account']['name'] ?? 'Unknown'
                ]
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid API key'
        ];

    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
```

### OAuth 2.0 Authentication

```php
public function getAuthType(): string
{
    return 'oauth2';
}

public function getOAuthConfig(): array
{
    return [
        'authorize_url' => 'https://myservice.com/oauth/authorize',
        'token_url' => 'https://myservice.com/oauth/token',
        'client_id' => MYSERVICE_CLIENT_ID,
        'client_secret' => MYSERVICE_CLIENT_SECRET,
        'scopes' => ['read', 'write'],
        'redirect_uri' => admin_url('admin.php?page=zaplane-oauth-callback')
    ];
}
```

---

## Step 3: Define Actions

Actions are things the integration can do in a workflow.

```php
public function getActions(): array
{
    return [
        [
            'key' => 'send_message',
            'name' => 'Send Message',
            'description' => 'Send a message to a channel',
            'icon' => 'message',
            'fields' => $this->getSendMessageFields()
        ],
        [
            'key' => 'create_task',
            'name' => 'Create Task',
            'description' => 'Create a new task',
            'icon' => 'check-square',
            'fields' => $this->getCreateTaskFields()
        ]
    ];
}

protected function getSendMessageFields(): array
{
    return [
        [
            'key' => 'channel',
            'label' => 'Channel',
            'type' => 'select',
            'required' => true,
            'options_source' => 'dynamic', // or 'static'
            'options_method' => 'getChannelOptions'
        ],
        [
            'key' => 'message',
            'label' => 'Message',
            'type' => 'textarea',
            'required' => true,
            'placeholder' => 'Enter your message',
            'supports_variables' => true // Allow {{workflow.variable}}
        ],
        [
            'key' => 'attachments',
            'label' => 'Attachments',
            'type' => 'file',
            'required' => false,
            'multiple' => true
        ]
    ];
}

protected function getCreateTaskFields(): array
{
    return [
        [
            'key' => 'title',
            'label' => 'Task Title',
            'type' => 'text',
            'required' => true,
            'supports_variables' => true
        ],
        [
            'key' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
            'required' => false,
            'supports_variables' => true
        ],
        [
            'key' => 'due_date',
            'label' => 'Due Date',
            'type' => 'date',
            'required' => false
        ],
        [
            'key' => 'priority',
            'label' => 'Priority',
            'type' => 'select',
            'required' => false,
            'options' => [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'high', 'label' => 'High']
            ]
        ]
    ];
}
```

---

## Step 4: Implement Action Execution

```php
public function executeAction(string $actionKey, array $input, array $credentials): array
{
    switch ($actionKey) {
        case 'send_message':
            return $this->executeSendMessage($input, $credentials);

        case 'create_task':
            return $this->executeCreateTask($input, $credentials);

        default:
            return [
                'success' => false,
                'error' => 'Unknown action: ' . $actionKey
            ];
    }
}

protected function executeSendMessage(array $input, array $credentials): array
{
    $apiKey = $credentials['api_key'] ?? '';
    $channel = $input['channel'] ?? '';
    $message = $input['message'] ?? '';

    // Validate inputs
    if (empty($channel) || empty($message)) {
        return [
            'success' => false,
            'error' => 'Channel and message are required'
        ];
    }

    try {
        // Make API call
        $response = wp_remote_post('https://api.myservice.com/v1/messages', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'channel' => $channel,
                'text' => $message,
                'attachments' => $input['attachments'] ?? []
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'data' => [
                    'message_id' => $body['id'] ?? null,
                    'channel' => $channel,
                    'timestamp' => $body['timestamp'] ?? current_time('mysql')
                ]
            ];
        }

        return [
            'success' => false,
            'error' => $body['error'] ?? 'Failed to send message',
            'error_code' => $code
        ];

    } catch (\Exception $e) {
        zaplane_log_error('MyService send message failed', [
            'error' => $e->getMessage(),
            'channel' => $channel
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

protected function executeCreateTask(array $input, array $credentials): array
{
    $apiKey = $credentials['api_key'] ?? '';
    $title = $input['title'] ?? '';

    if (empty($title)) {
        return [
            'success' => false,
            'error' => 'Task title is required'
        ];
    }

    try {
        $response = wp_remote_post('https://api.myservice.com/v1/tasks', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'title' => $title,
                'description' => $input['description'] ?? '',
                'due_date' => $input['due_date'] ?? null,
                'priority' => $input['priority'] ?? 'medium'
            ])
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'data' => [
                    'task_id' => $body['id'] ?? null,
                    'title' => $title,
                    'url' => $body['url'] ?? null
                ]
            ];
        }

        return [
            'success' => false,
            'error' => $body['error'] ?? 'Failed to create task'
        ];

    } catch (\Exception $e) {
        zaplane_log_error('MyService create task failed', [
            'error' => $e->getMessage(),
            'title' => $title
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

---

## Step 5: Dynamic Options

For dropdowns that load options from the API:

```php
public function getChannelOptions(array $credentials): array
{
    $apiKey = $credentials['api_key'] ?? '';

    try {
        $response = wp_remote_get('https://api.myservice.com/v1/channels', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $channels = $body['channels'] ?? [];

        return [
            'success' => true,
            'options' => array_map(function($channel) {
                return [
                    'value' => $channel['id'],
                    'label' => $channel['name']
                ];
            }, $channels)
        ];

    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

---

## Step 6: Define Triggers

Triggers start workflows when events occur.

```php
public function getTriggers(): array
{
    return [
        [
            'key' => 'new_message',
            'name' => 'New Message',
            'description' => 'Triggers when a new message is received',
            'icon' => 'message-circle',
            'type' => 'webhook', // or 'poll', 'schedule'
            'fields' => $this->getNewMessageTriggerFields()
        ],
        [
            'key' => 'task_completed',
            'name' => 'Task Completed',
            'description' => 'Triggers when a task is marked complete',
            'icon' => 'check-circle',
            'type' => 'webhook',
            'fields' => $this->getTaskCompletedTriggerFields()
        ]
    ];
}

protected function getNewMessageTriggerFields(): array
{
    return [
        [
            'key' => 'channel',
            'label' => 'Channel',
            'type' => 'select',
            'required' => false,
            'options_source' => 'dynamic',
            'options_method' => 'getChannelOptions',
            'help' => 'Leave empty to trigger on all channels'
        ]
    ];
}

public function setupTrigger(string $triggerKey, array $config, array $credentials): array
{
    if ($triggerKey === 'new_message') {
        return $this->setupNewMessageTrigger($config, $credentials);
    }

    return [
        'success' => false,
        'error' => 'Unknown trigger: ' . $triggerKey
    ];
}

protected function setupNewMessageTrigger(array $config, array $credentials): array
{
    $apiKey = $credentials['api_key'] ?? '';
    $webhookUrl = $config['webhook_url'] ?? ''; // Provided by Zaplane

    try {
        // Register webhook with service
        $response = wp_remote_post('https://api.myservice.com/v1/webhooks', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'url' => $webhookUrl,
                'events' => ['message.created'],
                'channel' => $config['channel'] ?? null
            ])
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success' => true,
            'webhook_id' => $body['id'] ?? null,
            'message' => 'Webhook registered successfully'
        ];

    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

---

## Step 7: Register Integration

The integration is automatically discovered and registered by the `IntegrationLoader`. No manual registration needed!

---

## Step 8: Test Your Integration

### Test Connection

```php
use Zaplane\Integrations\MyServiceIntegration;

$integration = new MyServiceIntegration();

$result = $integration->testConnection([
    'api_key' => 'your-test-api-key'
]);

if ($result['success']) {
    echo "Connected!";
} else {
    echo "Error: " . $result['message'];
}
```

### Test Action

```php
$result = $integration->executeAction('send_message', [
    'channel' => 'general',
    'message' => 'Hello from Zaplane!'
], [
    'api_key' => 'your-test-api-key'
]);

if ($result['success']) {
    echo "Message sent: " . $result['data']['message_id'];
} else {
    echo "Error: " . $result['error'];
}
```

---

## Advanced Features

### Rate Limiting

```php
protected function executeSendMessage(array $input, array $credentials): array
{
    // Check rate limit
    $rateLimitKey = 'myservice_rate_limit_' . md5($credentials['api_key']);
    $lastCall = get_transient($rateLimitKey);

    if ($lastCall && (time() - $lastCall) < 1) {
        return [
            'success' => false,
            'error' => 'Rate limit exceeded. Please wait 1 second between calls.'
        ];
    }

    // Execute action
    $result = $this->makeApiCall($input, $credentials);

    // Update rate limit
    set_transient($rateLimitKey, time(), 60);

    return $result;
}
```

### Retry Logic

```php
protected function makeApiCallWithRetry(string $url, array $args, int $maxRetries = 3): array
{
    $attempt = 0;

    while ($attempt < $maxRetries) {
        $response = wp_remote_post($url, $args);

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);

            // Success
            if ($code >= 200 && $code < 300) {
                return ['success' => true, 'response' => $response];
            }

            // Don't retry client errors
            if ($code >= 400 && $code < 500) {
                return ['success' => false, 'error' => 'Client error: ' . $code];
            }
        }

        $attempt++;

        if ($attempt < $maxRetries) {
            sleep(pow(2, $attempt)); // Exponential backoff
        }
    }

    return [
        'success' => false,
        'error' => 'Max retries exceeded'
    ];
}
```

### Caching

```php
protected function getChannelOptions(array $credentials): array
{
    $cacheKey = 'myservice_channels_' . md5($credentials['api_key']);
    $cached = get_transient($cacheKey);

    if ($cached !== false) {
        return $cached;
    }

    // Fetch from API
    $result = $this->fetchChannelsFromAPI($credentials);

    if ($result['success']) {
        // Cache for 5 minutes
        set_transient($cacheKey, $result, 5 * MINUTE_IN_SECONDS);
    }

    return $result;
}
```

---

## Best Practices

1. **Error Handling**: Always return `['success' => bool, 'error' => string]` format
2. **Logging**: Log errors and important events using `zaplane_log_error()` and `zaplane_log_info()`
3. **Validation**: Validate all inputs before making API calls
4. **Security**: Never log sensitive data (API keys, passwords, tokens)
5. **Timeouts**: Set appropriate timeouts for API calls (default: 30 seconds)
6. **Rate Limiting**: Respect API rate limits with transients or throttling
7. **Caching**: Cache expensive API calls (channel lists, user lists, etc.)
8. **Documentation**: Add clear descriptions and help text to all fields
9. **Testing**: Test connection, actions, and triggers thoroughly
10. **Versioning**: Update version number when making breaking changes

---

## Example: Complete Integration

See `includes/integrations/slack-integration.php` for a complete, production-ready example.

---

## Next Steps

- [Integration Base API](../api/integration-base.md) - Complete API reference
- [Working with Models](working-with-models.md) - Store integration data
- [Testing Guide](writing-tests.md) - Test your integration
- [Debugging Guide](debugging.md) - Debug integration issues

---

**Questions?** [Open an issue](https://github.com/yourusername/zaplane/issues) or [start a discussion](https://github.com/yourusername/zaplane/discussions).
