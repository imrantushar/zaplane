# Recipe Testing — Developer Guide

A **recipe** is a small JSON file that runs one integration's trigger or action
against the **real** dependency plugin (real WooCommerce, real GemCRM, …) with
real data, and asserts the output. It's the live counterpart to the mocked
PHPUnit `IntegrationTestCase` suite — see [../../TESTING.md](../../TESTING.md)
for the overview.

| | Mocked PHPUnit suite | Recipe CLI |
| --- | --- | --- |
| Runs against | in-memory WP mocks | a real WP site + real plugins |
| Proves | the integration contract | it actually works end-to-end |
| Speed | fast | slower (boots WP per recipe) |
| Use for | every commit / CI gate | pre-release confidence, debugging a live integration |

---

## Quickstart

```bash
# See what recipes exist and whether their dependency plugins are active
wp zaplane recipe list

# Run everything, one integration, or one file
wp zaplane recipe run
wp zaplane recipe run woocommerce
wp zaplane recipe run recipes-test/woocommerce/new-order.json

# Scaffold recipes
wp zaplane recipe generate gemcrm --event=contact_created   # one event
wp zaplane recipe generate academy                          # ALL triggers at once
wp zaplane recipe generate woocommerce --kind=all           # all triggers AND actions
```

`generate` writes one `recipes-test/<integration>/<event>.json` per event.
Omit `--event` to scaffold **every** registered trigger (or action, or `--kind=all`
for both) in one go; existing files are skipped, not overwritten.

Recipes live in `recipes-test/<integration>/<name>.json`. A non-zero exit on any
failure makes `recipe run` CI-friendly.

---

## How a run works

For each recipe, the CLI:

1. **Resolves required plugins** — the recipe's `required_plugins` **plus** the
   integration's `get_required_plugins()` (which reads the central map, below).
2. **Activates** any that are inactive, remembering which it switched on.
3. **Fires the recipe in a fresh WP-CLI subprocess.** This is important: heavy
   plugins like WooCommerce initialise on `plugins_loaded`/`init`, which have
   already fired by the time we could activate them mid-request. Running the
   fire in a child process lets the plugin boot normally. Inside it:
   seed data via the factory → interpolate `{{vars}}` → call `resolve_trigger`
   / `execute_node` → evaluate `expect`.
4. **Restores** plugin state (deactivates whatever it activated) unless you pass
   `--keep-active`.

The engine lives in `Zaplane\Testing\RecipeRunner`
([../../includes/testing/recipe-runner.php](../../includes/testing/recipe-runner.php));
its interpolation/assertion logic is unit-tested in
[../../tests/Testing/RecipeRunnerTest.php](../../tests/Testing/RecipeRunnerTest.php).

---

## Plugin dependencies — the central map

You do **not** add the dependency on each integration class. There's one map:
[../../includes/config/integration-plugins.php](../../includes/config/integration-plugins.php),
keyed by integration **slug**:

```php
return [
    'woocommerce' => [ 'woocommerce/woocommerce.php' ],
    'gemcrm'      => [ 'gemcrm/gemcrm.php' ],
    'learndash'   => [ 'sfwd-lms/sfwd_lms.php' ],
    // …
];
```

`IntegrationBase::get_required_plugins()` reads this map by slug, so adding a
dependency for any of the ~90 integrations is a **one-line edit** here — no
touching integration files.

- **API / SaaS integrations** (Slack, Mailchimp, Gmail, Telegram, Discord, HTTP,
  Jotform, Typeform, Zoom, …) have **no** local plugin — leave them out of the
  map (they resolve to `[]`).
- Find an installed plugin's exact basename with
  `wp plugin list --fields=name,file`.
- Need conditional/dynamic logic? Override `get_required_plugins()` on the
  integration class, or hook the `zaplane_integration_required_plugins`
  ( `$basenames, $slug` ) filter.
- A recipe can also set its own `required_plugins`; it's merged with the map.

---

## Recipe schema

```json
{
  "name": "woocommerce-new-order",
  "integration": "woocommerce",
  "required_plugins": [],
  "setup": { "factory": "create_wc_order", "args": { "total": 50, "status": "processing" } },
  "node": {
    "kind": "trigger",
    "event": "new_order",
    "config": {},
    "input": ["{{order_id}}"]
  },
  "expect": {
    "not_false": true,
    "has_keys": ["order_id", "status", "total"],
    "data": { "status": "processing" }
  }
}
```

| Field | Meaning |
| --- | --- |
| `integration` | Registry slug (must match `get_slug()`). |
| `required_plugins` | Extra basenames to activate; merged with the central map. Usually `[]`. |
| `setup.factory` | Named data factory (see below). Omit if no seed data is needed. |
| `setup.args` | Args passed to the factory. |
| `node.kind` | `trigger` → `resolve_trigger`; `action` → `execute_node`. |
| `node.event` | The trigger/action id from `get_triggers()`/`get_actions()`. |
| `node.config` | Action params / trigger filters. |
| `node.input` | Triggers: **positional hook args**. Actions: the input payload. |
| `node.then` | (E2E only, optional) downstream action chain — array of `{ "app", "event", "config" }`. |
| `expect` | Assertions (all optional, all must hold). |

### `expect` assertions

- `not_false` — output must not be `false`/`null` (catches filtered-out triggers
  and failed actions).
- `port` — (actions) expected output port, e.g. `"main"`.
- `has_keys` — keys that must exist. Triggers: the flat return array. Actions:
  `output.data`.
- `data` — partial match (recursive); scalars compare loosely so JSON `50`
  matches WooCommerce's `"50.00"`.
- `equals` — exact match of the whole payload.

---

## Seeding data without writing a factory (`setup.action`)

Most CRM/commerce integrations already have an **action** that creates the entity
their trigger fires on (WooCommerce has `create_order`, `create_customer`,
`create_product`, …). Point `setup.action` at it and the runner runs that action
to seed data — **no custom factory code at all**. Its output `data` becomes your
vars (dot-paths supported):

```json
"setup": { "action": { "app": "woocommerce", "event": "create_order", "config": { "status": "processing" } } },
"node":  { "kind": "trigger", "event": "new_order", "input": ["{{order.order_id}}"] }
```

Use `setup.factory` (below) only when the integration has **no** suitable action
(e.g. Academy) or you need a PHP object passed as a hook arg in `--e2e` mode.

## Variables and factories

A `setup.factory` seeds real data and returns a flat variable map. Reference it
with `{{name}}` in `input`/`config`. A token that is the **entire** string keeps
its native type (`"{{order_id}}"` → `42`); inline tokens substitute as strings
(`"Order #{{order_id}}"` → `"Order #42"`).

Built-in factories (`Zaplane\Testing\RecipeFactories`):

| Factory | Returns |
| --- | --- |
| `create_post` | `post_id` |
| `create_user` | `user_id`, `user_login`, `user_email` |
| `create_wc_order` | `order_id`, `total`, `status` |

Add your own without touching core. Integration-specific factories live **next
to their recipes** in `recipes-test/<integration>/factories.php`, which the
runner auto-loads — so a recipe folder is self-contained (recipes + the data
they need). Example `recipes-test/academy/factories.php`:

```php
<?php
add_filter( 'zaplane_recipe_factories', function ( array $f ) {
    $f['create_academy_enrollment'] = function ( array $args ) {
        $course_id = wp_insert_post( [ 'post_type' => 'academy_courses', 'post_status' => 'publish', 'post_title' => 'Recipe Course' ], true );
        $user_id   = wp_insert_user( [ 'user_login' => 'u' . uniqid(), 'user_email' => uniqid() . '@example.test', 'user_pass' => wp_generate_password() ] );
        $enroll_id = \Academy\Helper::do_enroll( (int) $course_id, (int) $user_id );
        return [ 'course_id' => (int) $course_id, 'user_id' => (int) $user_id, 'enroll_id' => (int) $enroll_id ];
    };
    return $f;
} );
```

Then the recipe references those vars:

```json
"setup": { "factory": "create_academy_enrollment", "args": {} },
"input": ["{{course_id}}", "{{enroll_id}}", "{{user_id}}"]
```

This is the **correct** way to fill a scaffold — real data, real assertions.
(Leaving `{{argN}}` placeholders makes the recipe SKIP; using a bare literal like
`$arg0` makes it *false-pass* with garbage, since only `{{var}}` tokens are
interpolated.)

---

## What `generate` scaffolds for you

For a trigger that reads hook args, `recipe generate` writes **both** the recipe
**and** a matching stub factory in `recipes-test/<integration>/factories.php`,
already wired together — so you never hand-write the factory boilerplate:

```jsonc
// recipes-test/academy/user_enroll_course.json
"setup": { "factory": "create_academy_user_enroll_course", "args": {} },
"node":  { "input": ["{{arg0}}", "{{arg1}}", "{{arg2}}"] }
```

```php
// recipes-test/academy/factories.php  (auto-created)
$factories['create_academy_user_enroll_course'] = function ( array $args ) {
    // TODO: create the real data, delete the throw, return arg0..argN.
    throw new \Zaplane\Testing\RecipeSkip( "Stub factory … — implement it" );
    return [ 'arg0' => null, 'arg1' => null, 'arg2' => null ];
};
```

Until you implement it the recipe **SKIPs** (not fails) with a clear message
pointing at the file. Your only job: replace the TODO body with the 2–4 lines
that create the data and return the `argN` values. That's the whole workflow —
`generate`, fill the body, run.

## Filling in `input` after `generate`

`recipe generate` only **scaffolds** a recipe — it can't know what data your
trigger needs, so it leaves `setup.factory` empty and pre-fills `node.input`
with one `{{argN}}` placeholder per positional hook arg the trigger reads (it
detects these from the trigger's `resolve_trigger`; if it can't tell, `input`
stays `[]` and you add them yourself). A generated trigger looks like:

```json
"setup": { "factory": "", "args": {} },
"node": { "kind": "trigger", "event": "user_enroll_course", "input": ["{{arg0}}", "{{arg1}}", "{{arg2}}"] }
```

Those placeholders are **not** real values yet — replace each one. To find what
each arg is, read the event's `case` in the integration's `resolve_trigger()`;
e.g. Academy's `user_enroll_course` (hook `academy/course/after_enroll`) reads
`$args[0]=course_id`, `$args[1]=enroll_id`, `$args[2]=user_id`. Then fill `input`
one of two ways:

**Literal values** — ids that already exist on the site:

```json
"input": [12, 34, 1]
```

**Dynamic values** — seed real data with a `setup.factory` that returns variables, and reference them with `{{var}}`:

```json
"setup": { "factory": "create_academy_enrollment", "args": {} },
"input": ["{{course_id}}", "{{enroll_id}}", "{{user_id}}"]
```

Register the factory once via the `zaplane_recipe_factories` filter (see
*Variables and factories*); it creates a course + enrollment and returns
`[ 'course_id' => …, 'enroll_id' => …, 'user_id' => … ]`.

> In `--e2e` mode `input` must be the FULL hook signature (every listener runs),
> so include all args the real `do_action` fires, not just the ones the recipe
> asserts on.

## Adding a recipe for an existing integration

1. Make sure the dependency is in
   [config/integration-plugins.php](../../includes/config/integration-plugins.php)
   (one line; skip for API integrations).
2. Scaffold:

   ```bash
   wp zaplane recipe generate woocommerce --event=new_order --kind=trigger
   ```

3. Fill in `setup` (factory + args), real `input`, and `expect`. Add a factory
   via the `zaplane_recipe_factories` filter if you need new seed data.
4. Run it: `wp zaplane recipe run woocommerce`.

## Scaffolding a brand-new integration

`make:integration` generates everything at once — the integration class, the
registry entry, the central plugin-map entry, a PHPUnit stub, and a starter
recipe:

```bash
wp zaplane make:integration acmecrm --plugin=acme-crm/acme-crm.php \
    --trigger=lead_created --action=create_lead --name="Acme CRM"
```

Then fill in the generated stubs and `wp zaplane recipe run acmecrm`.

---

## End-to-end mode (`--e2e`) — real workflow + engine

By default `recipe run` calls `resolve_trigger()`/`execute_node()` directly. With
`--e2e` it runs the **whole engine**, exactly like production:

1. inserts a real **active workflow** (trigger node → optional action chain),
2. fires the integration's **actual WordPress hook** (`do_action`),
3. lets the engine create real **Run + NodeRun** log records and walk the graph,
4. asserts against those logs, and
5. tears the temporary workflow/run back down.

By default the temp workflow + run are **deleted** after the assertion, so nothing
lingers in the admin UI. Pass **`--keep-workflow`** to keep them for inspection —
the workflow is left **paused** (visible under Workflows, with its run/logs, but it
won't fire on real site events).

```bash
wp zaplane recipe run woocommerce --e2e
```

```text
▸ woocommerce-new-order
  workflow #8 inserted (woocommerce.new_order, hook: woocommerce_new_order)
  run #3 started
    node #1 (woocommerce.new_order) [completed] -> {"order_id":119,"status":"processing",...}
  run #3 finished: completed
```

Notes:

- **E2E only applies to trigger recipes.** Action recipes are skipped (`SKIP`)
  because they aren't fired by a hook.
- **`input` must match the real hook signature.** A live `do_action` runs *every*
  listener on that hook (other plugins too), so pass all the args WordPress fires,
  not just what `resolve_trigger` needs — e.g. `woocommerce_new_order` fires
  `[$order_id, $order]`, so the recipe uses `"input": ["{{order_id}}", "{{order}}"]`.
  Factories expose objects for this (`create_wc_order` returns `order`,
  `create_post` returns `post`). The extra args are harmless in direct mode.
- **Multi-node workflows:** add a `node.then` array of action specs
  (`{ "app", "event", "config" }`) to test a trigger → action chain; each node's
  output is logged.

## Running in CI / before release

```bash
composer test:all                # mock gate: smoke + Utils + Testing + Integrations
wp zaplane recipe run            # live gate: exits non-zero on any failure
wp zaplane recipe run --e2e      # optional: full engine + Run/NodeRun logs
```

Performance & flags:

- Recipes are **grouped by dependency plugin** and each group shares one
  subprocess — the plugin boots once for all of its recipes, not once per recipe.
- Unfilled `generate` scaffolds (input still has `{{argN}}`) are **skipped**.
- `--fail-fast` stops at the first failure.
- `--keep-active` skips the activate/restore churn when you've pre-activated the
  plugin yourself:

```bash
wp plugin activate woocommerce
wp zaplane recipe run woocommerce --keep-active
```

---

## Troubleshooting

- **`Could not activate required plugin 'x/x.php'`** — the basename in the map
  (or recipe) is wrong or the plugin isn't installed. Confirm with
  `wp plugin list --fields=name,file`.
- **Trigger returns `false` / `not_false` fails** — the trigger filtered the
  event out (wrong post type, missing field). Check the `config` filters and the
  factory's output against the integration's `resolve_trigger`.
- **`get_order() on null` or similar half-initialised errors** — only happens if
  a heavy plugin is fired in-process; the CLI already fires in a subprocess, so
  this shouldn't occur via `recipe run`. If you call `RecipeRunner::run()`
  directly in your own code, pre-activate the plugin first.
- **A recipe passes alone but the suite is slow** — each recipe boots WP in a
  child process; that's expected. Filter to one integration while iterating.
