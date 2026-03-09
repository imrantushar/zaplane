# Integration Testing Guide

## Overview

Each integration has a dedicated test class in `tests/Integrations/`. Tests run against in-memory WordPress mocks (`WPMocks`) — no database setup required. Run them with:

```bash
vendor/bin/phpunit
```

---

## Directory Structure

```
tests/
  bootstrap.php                    ← defines constants, loads mocks + autoloader
  WPMocks.php                      ← in-memory mocks for all WP functions
  WPDBMock.php                     ← mock for $wpdb
  TestCase.php                     ← base PHPUnit test case (resets mocks on each test)
  Integrations/
    IntegrationTestCase.php        ← base class for all integration tests
    SlackTest.php
    WpformsTest.php
    ...
  Utils/
    VariableExtractorTest.php
phpunit.xml                        ← PHPUnit config
```

---

## Writing a Test Class

Every integration test class extends `IntegrationTestCase` and implements one required method:

```php
<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wordpress;

class WordpressTest extends IntegrationTestCase {

    protected function getIntegrationClass(): string {
        return Wordpress::class;
    }

    public function test_create_post(): void {
        $node   = $this->makeActionNode( 'create_post', [
            'post_title'  => 'Hello World',
            'post_status' => 'draft',
            'post_type'   => 'post',
        ] );
        $result = Wordpress::execute_node( $node, [] );

        $this->assertEquals( 'main', $result['port'] );
        $this->assertArrayHasKey( 'post_id', $result['data'] );
    }

    public function test_trigger_user_register(): void {
        $node   = $this->makeTriggerNode( 'user_register' );
        $result = Wordpress::resolve_trigger( $node, [ 1 ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'user_id', $result );
    }
}
```

---

## Available Helpers

### Node builders

| Method | Description |
|---|---|
| `makeActionNode(string $event, array $config = [], array $credentials = [])` | Builds an action node array. Pass `$credentials` for integrations that require a connection (e.g. Slack). |
| `makeTriggerNode(string $event, array $config = [])` | Builds a trigger node array with the correct `app`, `event`, and `hook` keys. |

### HTTP mocking

| Method | Description |
|---|---|
| `mockHttp(array $body, int $status = 200)` | Queues a fake HTTP response. The next call to `wp_remote_request`, `wp_remote_post`, or `wp_remote_get` returns this instead of making a real request. Call once per HTTP request the action makes. |

HTTP responses are consumed in order (FIFO). Call `mockHttp()` multiple times for actions that make multiple requests (e.g. `send_dm` opens a channel then sends a message — two calls needed).

---

## Auto-Contract Tests

Every test class that extends `IntegrationTestCase` automatically inherits these tests. They run without writing any code.

| Test method | What it catches |
|---|---|
| `integration_has_slug` | `get_slug()` returns an empty string |
| `all_triggers_have_labels_and_hooks` | Trigger definition missing `label` or `hook` key |
| `all_actions_have_labels` | Action definition missing `label` key |
| `trigger_config_schemas_are_valid` | Trigger schema not an array, or fields missing `key`/`type` |
| `action_config_schemas_are_valid` | Action schema not an array, or fields missing `key`/`type` |
| `output_ports_are_valid` | `get_output_ports()` returns empty or non-array |
| `all_tested_triggers_are_registered` | A trigger in `getTriggerTests()` is not in `get_triggers()` |
| `all_tested_actions_are_registered` | An action in `getActionTests()` is not in `get_actions()` |
| `triggers_fire_and_return_payload` | Runs all entries in `getTriggerTests()` and checks return is `array\|false` |
| `actions_execute_and_return_valid_format` | Runs all entries in `getActionTests()` and checks `port` + `data` keys exist |

### Bulk-testing actions and triggers

Override `getActionTests()` or `getTriggerTests()` to have the base class run format checks automatically against a list of events:

```php
protected function getActionTests(): array {
    return [
        'create_post' => [ 'post_title' => 'Test', 'post_status' => 'draft', 'post_type' => 'post' ],
        'trash_post'  => [ 'post_id' => 1 ],
    ];
}

protected function getTriggerTests(): array {
    return [
        'user_register' => [ 1 ],
        'wp_login'      => [ 'admin', (object) [ 'ID' => 1 ] ],
    ];
}
```

---

## Testing External API Integrations (Slack, Stripe, etc.)

Integrations that call external APIs use `wp_remote_post` / `wp_remote_request` internally. Use `mockHttp()` to return a controlled response without making real network calls.

```php
class SlackTest extends IntegrationTestCase {

    private array $credentials = [ 'access_token' => 'xoxb-test-token' ];

    protected function getIntegrationClass(): string {
        return Slack::class;
    }

    public function test_send_message_succeeds(): void {
        $this->mockHttp( [ 'ok' => true, 'ts' => '111.222', 'channel' => 'C123' ] );

        $node   = $this->makeActionNode( 'send_message', [ 'channel' => '#general', 'text' => 'Hi' ], $this->credentials );
        $result = Slack::execute_node( $node, [] );

        $this->assertEquals( 'main', $result['port'] );
        $this->assertEquals( '111.222', $result['data']['slack_message_ts'] );
    }

    public function test_send_message_throws_on_api_error(): void {
        $this->mockHttp( [ 'ok' => false, 'error' => 'channel_not_found' ] );

        $this->expectException( \Exception::class );
        $this->expectExceptionMessageMatches( '/channel_not_found/' );

        $node = $this->makeActionNode( 'send_message', [ 'channel' => '#bad', 'text' => 'Hi' ], $this->credentials );
        Slack::execute_node( $node, [] );
    }
}
```

**Actions that make multiple HTTP requests** (e.g. `send_dm` opens a channel then posts): call `mockHttp()` once per request in order.

```php
$this->mockHttp( [ 'ok' => true, 'channel' => [ 'id' => 'D123' ] ] ); // conversations.open
$this->mockHttp( [ 'ok' => true, 'ts' => '111.222', 'channel' => 'D123' ] ); // chat.postMessage
```

---

## Trigger-Only Integrations (WPForms, etc.)

Some integrations expose only triggers and no actions. When the trigger reads properties directly from the node (not from `data.config`), build the node manually instead of using `makeTriggerNode()`:

```php
class WpformsTest extends IntegrationTestCase {

    protected function getIntegrationClass(): string {
        return Wpforms::class;
    }

    private function makeWpformsNode( string $event, string $formId = 'any' ): array {
        return [
            'type'    => 'trigger',
            'event'   => $event,
            'form_id' => $formId,
            'data'    => [ 'app' => 'wpforms', 'event' => $event ],
        ];
    }

    public function test_trigger_returns_payload_for_any_form(): void {
        $node   = $this->makeWpformsNode( 'form_submitted', 'any' );
        $result = Wpforms::resolve_trigger( $node, [ $fields, $entry, $formData ] );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
    }
}
```

---

## WP Function Mocks Available

`WPMocks` provides in-memory implementations for:

- Posts: `wp_insert_post`, `wp_update_post`, `wp_delete_post`, `wp_trash_post`, `wp_untrash_post`, `get_post`, `get_posts`
- Users: `wp_insert_user`, `wp_update_user`, `wp_delete_user`, `get_user_by`, `get_userdata`
- Comments: `wp_insert_comment`, `wp_delete_comment`, `get_comment`
- Options: `get_option`, `update_option`, `delete_option`
- HTTP: `wp_remote_request`, `wp_remote_post`, `wp_remote_get`, `wp_remote_retrieve_body`, `wp_remote_retrieve_response_code`
- Misc: `wp_strip_all_tags`, `wp_json_encode`, `is_wp_error`, `wp_generate_password`

Pre-seed mock data in `setupMockData()` (called automatically before each test):

```php
protected function setupMockData(): void {
    parent::setupMockData(); // loads default posts 1-3, users 1-2, comment 1
    WPMocks::setPost( 10, [ 'post_title' => 'My Post', 'post_status' => 'publish' ] );
    WPMocks::setUser( 5,  [ 'user_email' => 'custom@example.com' ] );
}
```

---

## Rules

- One test class per integration, named `{IntegrationName}Test.php` in `tests/Integrations/`
- Test method names start with `test_`
- Always cover both the success path and at least one failure path per action
- Use `mockHttp()` for any integration that calls an external API — never make real HTTP calls
- Call `mockHttp()` once per HTTP request the action makes, in order
- Do not override `setUp()` for test-specific state — use `setupMockData()` for shared mock data or set up inline inside the test
