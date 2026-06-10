# Testing Zaplane

The Zaplane WordPress plugin ships with **~1,644 PHPUnit tests** covering
every integration in the registry plus the workflow engine, utils, and
parity checks. They run against in-memory WordPress mocks — no real WP
install or database is required.

This doc is the entry point. If you want a step-by-step **"how do I write
a test for my new integration"**, jump to
[docs/backend/test-integration.md](docs/backend/test-integration.md).

---

## Quick reference

| Goal | Command |
| --- | --- |
| Run everything | `composer test` |
| Just the smoke (drift detection) | `composer test:smoke` |
| Just the Integrations suite | `composer test:integrations` |
| Just the Utils suite | `composer test:utils` |
| The release-gate suite | `composer test:release` |
| One integration class | `vendor/bin/phpunit --filter SlackTest` |
| One specific method | `vendor/bin/phpunit --filter test_send_message_uses_token` |
| Format / lint | `composer format` / `composer lint` |

> The full suite takes **~8 minutes** because `processIsolation="true"`
> spawns a fresh PHP process per test. This is intentional — see
> [The processIsolation tradeoff](#the-processisolation-tradeoff).

---

## What's in the suite

```
tests/
├── bootstrap.php                    constants, mocks, autoloader
├── WPMocks.php                      in-memory WP fakes (~1,500 lines)
├── WPDBMock.php                     $wpdb fake
├── TestCase.php                     base — resets state per test
├── mocks/                           per-integration global-function stubs
│   ├── wpuserfrontend.php           loaded FIRST (priority mock)
│   ├── ultimatemember.php
│   ├── gamipress.php
│   └── … 5 more
├── stubs/                           class stubs for absent WP plugins
├── Integrations/
│   ├── IntegrationTestCase.php      contract base — 10 inherited tests
│   ├── ParityTest.php               registry-wide drift detection
│   ├── SlackTest.php                one per integration (~75 files)
│   ├── HubspotTest.php
│   ├── …
│   └── _pending/                    parked tests for unwritten integrations
├── Parity/                          golden fixtures (symlinked from cloud)
└── Utils/                           pure-PHP utility tests
phpunit.xml                          config — processIsolation enabled
```

### Suites

`phpunit.xml` declares two `<testsuite>` entries:

| Suite | Path | Purpose |
| --- | --- | --- |
| `Integrations` | `tests/Integrations/` | every integration class + `ParityTest` |
| `Utils` | `tests/Utils/` | pure utility code (`Expression`, formatters, …) |

The `tests/Integrations/_pending/` subdir is **excluded** from `Integrations`
— it parks tests for integrations that don't have a source file yet
(currently `BricksTest`, `CartflowsTest`).

---

## Architecture

### `IntegrationTestCase` — the contract base

Every integration test extends this class. Subclassing alone gets you **10
free contract tests** that fire on every run:

| Test | What it asserts |
| --- | --- |
| `integration_has_slug` | `get_slug()` returns a non-empty string |
| `all_tested_triggers_are_registered` | every event in `getTriggerTests()` is in `get_triggers()` |
| `all_tested_actions_are_registered` | every event in `getActionTests()` is in `get_actions()` |
| `all_triggers_have_labels_and_hooks` | every trigger declares `label` + `hook` |
| `all_actions_have_labels` | every action declares `label` |
| `trigger_config_schemas_are_valid` | every trigger's schema is well-formed |
| `action_config_schemas_are_valid` | every action's schema is well-formed |
| `output_ports_are_valid` | `get_output_ports()` is a non-empty array |
| `triggers_fire_and_return_payload` | every trigger in `getTriggerTests()` returns array \| false |
| `actions_execute_and_return_valid_format` | every action in `getActionTests()` returns `['port', 'data']` |

A bare-bones test file is one method:

```php
class MyIntegrationTest extends IntegrationTestCase {
    protected function getIntegrationClass(): string {
        return MyIntegration::class;
    }
}
```

To exercise specific triggers/actions, override `getTriggerTests()` and
`getActionTests()` with mock args / config arrays.

Full authoring guide: [docs/backend/test-integration.md](docs/backend/test-integration.md).

### `ParityTest` — registry-wide drift detection

[tests/Integrations/ParityTest.php](tests/Integrations/ParityTest.php)
runs **once for the whole registry**, not per-class. It catches the kinds
of drift that hide until production:

1. **No duplicate class entries** in [includes/config/integrations.php](includes/config/integrations.php)
2. **Every registry file exists on disk with the exact case spelled out**
   — guards against `Slack.php` vs `slack.php` Linux-deploy fatals that
   APFS masks on macOS
3. **Every registry class autoloads** — catches missing source files
4. **Every class extends `IntegrationBase`**
5. **`get_slug()` matches the registry key** — catches `'WordPress'` vs
   `'wordpress'` case mismatches
6. **Every integration has a `*Test.php`** in `tests/Integrations/` (or is
   parked under `_pending/`)
7. **Webhook integrations override `verify_webhook_signature()`** — without
   the override, the default `__return_true` makes the endpoint
   unauthenticated
8. **Connection integrations override `test_connection()`** — without the
   override, the create-connection UI can't validate credentials

This is the **smoke** suite. It's tiny, fast, and **must stay green to
merge** (see [CI](#ci)).

### `WPMocks` — the in-memory WordPress

[tests/WPMocks.php](tests/WPMocks.php) is the canonical fake for WordPress
APIs. ~1,500 lines covering:

- Posts (`setPost`/`getPost`/`insertPost`/`updatePost`/`deletePost`)
- Users (`setUser`/`getUser`/`insertUser`) — default user includes
  `first_name`, `last_name`, `nickname` so integrations that read those
  fields don't blow up
- Comments, terms, taxonomies, post types, roles
- Options + transients
- HTTP (`setHttpResponse(array, int $status)` — queues mock responses
  consumed by `wp_remote_*` and the `wp_remote_retrieve_*` helpers)
- REST routes, `WP_User`, `WP_Post`, `WP_Error`
- ~120 free-floating WP functions like `current_time`, `wp_parse_url`,
  `is_plugin_active`, `wp_json_encode`, `add_action`, `apply_filters`

`WP_User` carries `#[\AllowDynamicProperties]` so PHP 8.2+ doesn't
deprecate-warn when integrations set arbitrary properties on it.

`WPMocks::reset()` runs in `TestCase::setUp()` and `tearDown()` — every
test gets a clean store.

### `tests/mocks/<integration>.php` — per-integration stubs

Some integrations need WP plugin classes/functions that don't exist in the
test environment (e.g. WooCommerce, FluentCRM). The files in
[tests/mocks/](tests/mocks/) provide those stubs. They're auto-loaded by
`tests/WPMocks.php` via `glob()`.

> **Important:** these files define globals via
> `if ( ! function_exists( 'foo' ) ) { function foo(...) {...} }`. The
> first file to load wins. **`tests/mocks/wpuserfrontend.php` is forced to
> load first** (it has the richest fixtures); see the priority-load loop
> in `WPMocks.php` near the end of the file.

If you add a new mock file, prefer reading from `\Zaplane\Tests\WPMocks`
or `$GLOBALS['zaplane_wp_posts']` rather than hard-coding fixtures, so
multiple tests can coexist.

### `tests/bootstrap.php`

- Defines `ZAPLANE_TESTING`, `ABSPATH`, plugin path constants
- Requires Composer autoloader, `WPDBMock`, `WPMocks`, `TestCase`,
  `IntegrationTestCase`
- Wires `$wpdb = new \Zaplane\Tests\WPDBMock()` globally
- Loads `includes/autoload.php` (the plugin's own PSR-4 autoloader)

---

## CI

[.github/workflows/test.yml](.github/workflows/test.yml) runs **two jobs**:

### `smoke` (always strict)

Runs `composer test:smoke` (= `ParityTest` only) on PHP 8.2. Catches
case-mismatched files, missing classes, slug drift, unauthenticated
webhooks, integrations without tests. **Must stay green to merge.**

### `phpunit` (matrix, currently `continue-on-error`)

Runs `composer test:release` (Utils + Integrations) on PHP 7.4 / 8.0 / 8.2.
`continue-on-error: true` means red doesn't block merges *yet* — this is
the safety hatch that will be removed once the suite stays green for a
sustained period. As of the most recent run **the full suite is 1644/1644
green** so the flag can be flipped on a quick follow-up.

---

## The processIsolation tradeoff

`phpunit.xml` ships with `processIsolation="true"`. This spawns a fresh
PHP process per test, which:

✅ defeats global-function pollution from `tests/mocks/*.php`<br>
✅ makes every test deterministic regardless of execution order<br>
✅ matches what CI actually measures<br>
❌ takes **~8 minutes for the full 1644-test suite** (vs ~3s without)

For local iteration, **don't run the full suite** — target what you're
changing:

```bash
# Fast: one class
vendor/bin/phpunit --filter SlackTest

# Faster: one method
vendor/bin/phpunit --filter test_send_message_uses_token

# Smoke: catches registry/case/slug drift in <1s
composer test:smoke
```

The full run is for **release confidence** and CI. Iterate with filters.

### Why isolation is needed

Several `tests/mocks/*.php` files (e.g. `gamipress.php`, `learndash.php`,
`ultimatemember.php`, `wpuserfrontend.php`) redefine `get_post`,
`get_userdata`, etc. via `function_exists` guards. Once any one loads,
its definitions stick for the rest of the PHP process. Without isolation,
running `WordpressTest` after `WpuserfrontendTest` means `WordpressTest`
sees `wpuserfrontend.php`'s `get_post` returning `WP_Post` stubs with
`post_type = 'wpuf_post'` — which makes ~30 Wordpress trigger tests fail
on assumed `'post'` types.

Process isolation is the surgical fix. The deeper refactor — making every
per-mock file route through `WPMocks::getPost`/`WPMocks::getUser` first —
is on the roadmap but out of scope for shipping.

---

## Adding a new integration test

Five lines is usually enough:

```php
<?php
namespace Zaplane\Tests\Integrations;
use Zaplane\Integrations\MyThing;

class MyThingTest extends IntegrationTestCase {
    protected function getIntegrationClass(): string {
        return MyThing::class;
    }
}
```

`ParityTest` will fail until this file exists for any integration in the
registry — so adding the stub is enforced.

For trigger/action coverage:

```php
class MyThingTest extends IntegrationTestCase {
    protected function getIntegrationClass(): string {
        return MyThing::class;
    }

    protected function getTriggerTests(): array {
        return [
            'order_created' => [ 9001 ],  // args to resolve_trigger
        ];
    }

    protected function getActionTests(): array {
        return [
            'send_email' => [           // config for execute_node
                'to'      => 'a@b.com',
                'subject' => 'Hi',
            ],
        ];
    }

    // Need a specific post/user/HTTP response in the fixture?
    protected function setupMockData(): void {
        parent::setupMockData();
        WPMocks::setPost( 9001, [
            'post_type'  => 'shop_order',
            'post_title' => 'Order #9001',
        ] );
        WPMocks::setHttpResponse( [ 'ok' => true ], 200 );
    }
}
```

Full guide with all the patterns (mocking forms, dynamic queries, HTTP
sequences): [docs/backend/test-integration.md](docs/backend/test-integration.md).

### Parking an unwritten test

If you're stubbing out an integration before its source file exists, drop
the test under `tests/Integrations/_pending/<Class>Test.php`. PHPUnit
skips that dir but the file remains in the tree.

---

## Common gotchas

### "Undefined property: stdClass::$first_name"

Your integration is reading `$user->first_name` after `get_userdata()`,
and a per-mock file overrode `get_userdata` to return a leaner stdClass.
Either:

1. Defend with `??` defaults: `$user->first_name ?? ''`
2. Use `new \WP_User( $user_id )` instead of `get_userdata( $user_id )` —
   the WPMocks `WP_User` class always returns the `WPMocks::setUser()`
   record with all default fields populated

### "Call to undefined method stdClass::add_role()"

Same root cause: `get_userdata` returned a stdClass. The role-actions
trait in WordPress already falls back to `new \WP_User($id)` via
[role-actions-trait.php](integrations/wordpress/role-actions-trait.php) —
copy that pattern in any code that needs role-mutation methods.

### "Call to undefined function wp_xyz()"

WPMocks doesn't cover it. Add a function to the bottom of
[tests/WPMocks.php](tests/WPMocks.php) inside the existing `if ( !
function_exists(...) )` block. Aim for the smallest viable behaviour —
returning `''`, `[]`, or `null` is usually enough.

### Test passes alone but fails in the full suite

It's global-function pollution from a sibling test's per-mock file.
`processIsolation="true"` in phpunit.xml already prevents this on CI
runs. If you're seeing it locally, check that you didn't remove the
isolation flag.

### Mockery `alias:foo` doesn't override `foo()`

Mockery's `alias:` mock requires the function to be undefined at alias
time. If `WPMocks` or a per-mock file already defined the function (via
`function_exists` guard), the alias is a no-op and your test silently
runs against the real function. Use the `$GLOBALS['zaplane_<name>']`
override hook pattern instead — see [SureformTest.php](tests/Integrations/SureformTest.php)
for the recipe.

---

## Live recipe testing (CLI)

The PHPUnit suite above runs against **mocks**. It proves the integration
*contract* but never proves an integration works against the **real**
dependency plugin (real WooCommerce, real GemCRM) with real data.

The **recipe CLI** is the live counterpart. A recipe is a small JSON file
describing one integration + the trigger/action to fire + input + expected
output. When you run it, the CLI **auto-activates the dependency plugin** if it
isn't active, fires the trigger/action against the real plugin, asserts the
result, then restores plugin state.

```bash
wp zaplane recipe list                 # discovered recipes + plugin active/inactive
wp zaplane recipe run                   # run every recipe under recipes-test/
wp zaplane recipe run woocommerce       # run one integration's recipes
wp zaplane recipe run recipes-test/woocommerce/new-order.json   # one file
wp zaplane recipe run woocommerce --keep-active                 # don't restore plugin state
wp zaplane recipe run woocommerce --e2e                         # real workflow + engine + Run/NodeRun logs
wp zaplane recipe run --fail-fast                               # stop on first failure
wp zaplane recipe generate gemcrm --event=contact_created       # scaffold a starter recipe
wp zaplane recipe generate academy                              # scaffold ALL triggers at once
```

Runs are fast: recipes are grouped by their dependency plugin and each group
shares **one** WP-CLI subprocess (the plugin boots once for all its recipes).
Unfilled `generate` scaffolds (input still has `{{argN}}` placeholders) are
**skipped**, not run, so a freshly generated folder won't show false results.

Recipes live under `recipes-test/<integration>/<name>.json`. The runner
(`Zaplane\Testing\RecipeRunner`) is CLI-agnostic; its interpolation/assertion
logic is unit-tested in `tests/Testing/RecipeRunnerTest.php`.

Auto-activation relies on a **central plugin map** keyed by integration slug —
[includes/config/integration-plugins.php](includes/config/integration-plugins.php)
— so declaring a dependency is a one-line edit, not a change to each integration
file. Full developer guide:
[docs/backend/recipe-testing.md](docs/backend/recipe-testing.md).

### Scaffolding a whole new integration

`wp zaplane make:integration <slug> [--plugin=<basename>] [--trigger=<e>] [--action=<e>]`
generates the integration class (with `get_required_plugins()`), the registry
entry, a PHPUnit stub, and a starter recipe in one shot — then fill the stubs
and `wp zaplane recipe run <slug>`.

---

## Pre-release checklist

Run these in order before tagging a release — fast gates first, live checks last:

```bash
# 1. Mock gate (no WP install needed) — contract + units. CI runs this.
composer test:smoke      # ParityTest only — registry/case/slug drift (<1s, must pass)
composer test:all        # smoke + Utils + Testing + Integrations (~8 min under isolation)

# 2. Live gate (real WP site) — integrations work against real plugins.
wp zaplane recipe generate --all # sync the runnable suite from integration seeders
wp zaplane recipe run            # direct mode — fast, non-zero exit on failure
wp zaplane recipe run --e2e      # full engine: inserts workflow, fires hook, checks Run/NodeRun logs
```

`recipe generate --all` regenerates one bare recipe per **seedable** trigger
(those an integration declares in `get_seedable_triggers()`) — recipes are a
generated artifact synced from the integrations, so you regenerate them at
release time rather than hand-maintaining them. Integrations are the source of
truth: to widen live coverage, add `seed_trigger_args()` cases to the
integration (see the developer guide), not more recipe files.

`composer test:all` is the single mock-side gate (smoke + the full suite).
`wp zaplane recipe run` is the live gate — green here means each integration
actually works end-to-end on a real site, which is what your users experience.
Use `--fail-fast` to stop at the first failure while debugging.
