# Integration Testing Guide

## Overview

Each integration has a dedicated test class in `tests/Integrations/`. Tests run against in-memory WordPress mocks (`WPMocks`) — no database or WordPress install required. Run them with:

```bash
vendor/bin/phpunit
```

---

## Directory Structure

```
tests/
  bootstrap.php                    ← defines constants, loads mocks + autoloader
  WPMocks.php                      ← in-memory mocks for all WP functions and classes
  WPDBMock.php                     ← mock for $wpdb
  TestCase.php                     ← base PHPUnit test case (resets mocks on each test)
  Integrations/
    IntegrationTestCase.php        ← base class for all integration tests
    WordpressTest.php              ← full example — posts, users, comments, roles, terms
    AcademyTest.php                ← example for trigger-only / external-class integrations
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
use Zaplane\Tests\WPMocks;

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
| `makeTriggerNode(string $event, array $config = [])` | Builds a trigger node. `$config` maps to `$node['data']['config']` — this is how "selected course", "selected quiz", "target percentage" etc. are passed to `resolve_trigger()`. |

### HTTP mocking

| Method | Description |
|---|---|
| `mockHttp(array $body, int $status = 200)` | Queues a fake HTTP response. The next call to `wp_remote_request`, `wp_remote_post`, or `wp_remote_get` returns this instead of making a real request. Call once per HTTP request the action makes. |

HTTP responses are consumed in order (FIFO). Call `mockHttp()` multiple times for actions that make multiple requests (e.g. `send_dm` opens a channel then sends a message — two calls needed).

---

## Auto-Contract Tests

Every class that extends `IntegrationTestCase` automatically inherits these tests. They run without writing any code.

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

Override `getActionTests()` or `getTriggerTests()` to have the base class run format checks automatically:

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
        // wp_login checks instanceof \WP_User — pass null for the bulk runner
        // (returns false, which is valid). Use makeWpUser() in hand-written tests.
        'wp_login'      => [ 'admin', null ],
    ];
}
```

> **Important — `WP_User` in `getTriggerTests()`:** `getTriggerTests()` is called at
> test-collection time, before `WPMocks.php` has finished loading. Any code that
> instantiates `\WP_User` there will cause a fatal "Class not found" error. Always
> pass `null` for user arguments in the bulk runner and instantiate `\WP_User` only
> inside individual test method bodies.

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

## Trigger-Only / External-Class Integrations

Some integrations (e.g. WPForms, Academy LMS) expose only triggers and no actions, or fire trigger args that are domain objects rather than simple scalars. Two patterns apply.

### Pattern A — node reads config from `data.config` (standard)

Use `makeTriggerNode($event, $config)` as normal. The second argument becomes `$node['data']['config']` and is used for filter values like "selected course" or "target percentage":

```php
// Academy: node configured for course 42 — enrollments in other courses return false.
$result = Academy::resolve_trigger(
    $this->makeTriggerNode( 'user_enroll_course', [ 'course_id' => '42' ] ),
    [ 42, 7 ]   // course_id, enroll_id
);
```

### Pattern B — node reads properties directly (non-standard)

When the trigger reads from the node array itself (not `data.config`), build the node manually:

```php
private function makeWpformsNode( string $event, string $formId = 'any' ): array {
    return [
        'type'    => 'trigger',
        'event'   => $event,
        'form_id' => $formId,
        'data'    => [ 'app' => 'wpforms', 'event' => $event ],
    ];
}
```

### Domain object arguments

Triggers that fire with domain objects (quiz attempts, form entries, etc.) need a helper to build those objects:

```php
// Academy — build a quiz attempt object
private function makeAttempt( array $overrides = [] ): object {
    return (object) array_merge( [
        'quiz_id'        => 10,
        'user_id'        => 1,
        'earned_marks'   => 8,
        'total_marks'    => 10,
        'attempt_status' => 'attempt_ended',
    ], $overrides );
}

// Use it
$result = Academy::resolve_trigger(
    $this->makeTriggerNode( 'quiz_target', [ 'quiz_id' => 'any', 'target_percentage' => '70' ] ),
    [ $this->makeAttempt( [ 'earned_marks' => 8, 'total_marks' => 10 ] ) ]
);
```

---

## Actions That Return `error` Port

Some actions validate preconditions (e.g. `delete_post` checks the post is already in trash before deleting). When such an action legitimately returns `error`, **remove it from `getActionTests()`** — the bulk runner only accepts `main` port. Write a dedicated hand-written test instead and assert both ports are acceptable:

```php
// Do NOT put this in getActionTests() — it returns 'error' when post is not in trash.
public function test_action_delete_post_returns_valid_response(): void {
    $result = Wordpress::execute_node(
        $this->makeActionNode( 'delete_post', [ 'post_id' => 1, 'post_type' => 'post' ] ),
        []
    );

    $this->assertArrayHasKey( 'port', $result );
    $this->assertContains( $result['port'], [ 'main', 'error' ] );
}
```

Similarly, **remove from `getTriggerTests()`** any trigger that calls an unstubbed WP function. Add it to the hand-written section with a contract-only assertion (`array|false`) instead.

---

## WP Function Mocks Available

`WPMocks` provides in-memory implementations for the following. All are guarded with `function_exists` / `class_exists` checks so adding real WordPress later won't conflict.

### Posts
`wp_insert_post`, `wp_update_post`, `wp_delete_post`, `wp_trash_post`, `wp_untrash_post`,
`get_post`, `get_posts`, `get_post_type`, `get_post_status`, `get_post_types`,
`get_post_meta`, `update_post_meta`, `delete_post_meta`, `set_post_thumbnail`,
`get_post_permalink`, `register_post_type`, `add_post_type_support`, `post_type_exists`

### Users
`wp_insert_user`, `wp_update_user`, `wp_delete_user`, `get_userdata`, `get_user_by`,
`get_users`, `get_user_meta`, `update_user_meta`, `delete_user_meta`,
`get_current_user_id`, `wp_get_current_user`, `current_user_can`, `user_can`,
`wp_authenticate`, `wp_logout`, `wp_set_password`, `get_password_reset_key`, `wp_mail`

### `WP_User` class
The `WP_User` stub has full role and capability methods:
`add_role()`, `remove_role()`, `set_role()`, `add_cap()`, `remove_cap()`, `has_cap()`.
`get_userdata()` returns a real `WP_User` instance (not a plain `stdClass`).

### Comments
`wp_insert_comment`, `wp_update_comment`, `wp_delete_comment`, `wp_trash_comment`,
`wp_untrash_comment`, `wp_set_comment_status`, `wp_spam_comment`, `wp_unspam_comment`,
`get_comment`, `get_comment_meta`

### Terms & Taxonomy
`wp_insert_term`, `wp_update_term`, `wp_delete_term`, `get_term`, `get_terms`,
`wp_set_object_terms`, `wp_get_object_terms`,
`register_taxonomy`, `unregister_taxonomy`, `taxonomy_exists`, `get_object_taxonomies`

### Roles
`add_role`, `remove_role`, `get_role`, `wp_roles`

### Options
`get_option`, `update_option`, `add_option`, `delete_option`

### Media / Attachments
`wp_delete_attachment`, `wp_get_attachment_url`, `wp_count_attachments`,
`wp_generate_attachment_metadata`, `wp_update_attachment_metadata`,
`media_sideload_image`, `get_post_mime_type`, `set_post_thumbnail`

### HTTP
`wp_remote_request`, `wp_remote_post`, `wp_remote_get`,
`wp_remote_retrieve_body`, `wp_remote_retrieve_response_code`

### Date / Time
`current_time`, `wp_timezone_string`, `get_gmt_from_date`

### Multisite / Blog
`get_home_url`, `get_blog_option`, `switch_to_blog`, `restore_current_blog`, `get_current_blog_id`

### Misc
`wp_json_encode`, `wp_strip_all_tags`, `wp_parse_args`, `absint`,
`sanitize_text_field`, `sanitize_title`, `sanitize_key`, `esc_html`, `esc_attr`,
`is_wp_error`, `get_avatar_url`, `wp_mkdir_p`, `wp_generate_password`

### Classes
`WP_Error`, `WP_User`, `WP_Query`, `WP_REST_Response`, `WP_REST_Controller`, `WP_REST_Server`

### Pre-seeded mock data

`parent::setupMockData()` seeds:
- Posts `1`, `2`, `3` (status `publish`, type `post`)
- Users `1`, `2`
- Comment `1`

Extend `setupMockData()` to add your own fixtures:

```php
protected function setupMockData(): void {
    parent::setupMockData();

    // Extra attachment post
    WPMocks::setPost( 10, [
        'post_title'     => 'My Image',
        'post_status'    => 'inherit',
        'post_type'      => 'attachment',
        'post_mime_type' => 'image/jpeg',
    ] );

    // Extra user
    WPMocks::setUser( 5, [ 'user_email' => 'custom@example.com' ] );

    // Seed a term so term triggers have something to return
    WPMocks::setTerm( 5, [
        'term_id'  => 5,
        'name'     => 'PHP',
        'slug'     => 'php',
        'taxonomy' => 'category',
    ] );
}
```

---

## Rules

- One test class per integration, named `{IntegrationName}Test.php` in `tests/Integrations/`
- Test method names start with `test_`
- Always cover both the success path and at least one failure path per action/trigger
- Use `mockHttp()` for any integration that calls an external API — never make real HTTP calls
- Call `mockHttp()` once per HTTP request the action makes, in order
- Do not override `setUp()` for test-specific state — use `setupMockData()` for shared fixtures or set up inline inside the test method
- Do not instantiate `\WP_User` inside `getTriggerTests()` — only inside test method bodies (see note above)
- Actions that return `error` port under normal conditions must not go in `getActionTests()` — write a dedicated hand-written test
- Triggers that call a WP function not in `WPMocks` must not go in `getTriggerTests()` — add the stub to `WPMocks.php` first, then add the trigger

---

## Quick Reference — Common Patterns

```php
// ── Happy path action ─────────────────────────────────────────────────────────
$result = MyIntegration::execute_node(
    $this->makeActionNode( 'action_name', [ 'key' => 'value' ] ),
    []
);
$this->assertEquals( 'main', $result['port'] );
$this->assertArrayHasKey( 'some_key', $result['data'] );

// ── Sad path action (assert 'error' port) ─────────────────────────────────────
$result = MyIntegration::execute_node(
    $this->makeActionNode( 'action_name', [ 'key' => 'bad_value' ] ),
    []
);
$this->assertEquals( 'error', $result['port'] );

// ── Action that may return either port ───────────────────────────────────────
$this->assertContains( $result['port'], [ 'main', 'error' ] );

// ── Happy path trigger ────────────────────────────────────────────────────────
$result = MyIntegration::resolve_trigger(
    $this->makeTriggerNode( 'trigger_name' ),
    [ $arg1, $arg2 ]
);
$this->assertIsArray( $result );
$this->assertEquals( 'expected_value', $result['field'] );

// ── Sad path trigger ──────────────────────────────────────────────────────────
$result = MyIntegration::resolve_trigger(
    $this->makeTriggerNode( 'trigger_name' ),
    [ null ]    // missing required arg
);
$this->assertFalse( $result );

// ── Trigger with filter config ────────────────────────────────────────────────
// Config = specific ID → only matching events pass through
$result = MyIntegration::resolve_trigger(
    $this->makeTriggerNode( 'trigger_name', [ 'item_id' => '99' ] ),
    [ 42 ]   // fired with ID 42 — should be filtered out
);
$this->assertFalse( $result );

// Config = 'any' → all events pass through
$result = MyIntegration::resolve_trigger(
    $this->makeTriggerNode( 'trigger_name', [ 'item_id' => 'any' ] ),
    [ 42 ]
);
$this->assertIsArray( $result );

// ── Trigger contract (array|false, never null, never throws) ─────────────────
$this->assertThat(
    $result,
    $this->logicalOr( $this->isType( 'array' ), $this->identicalTo( false ) )
);

// ── Pre-seeding mock data for a specific test ─────────────────────────────────
WPMocks::setPost( 99, [ 'post_status' => 'trash', 'post_type' => 'post' ] );
WPMocks::setUser( 10, [ 'user_email' => 'admin@example.com' ] );
WPMocks::setTerm( 5,  [ 'name' => 'PHP', 'taxonomy' => 'category' ] );
```