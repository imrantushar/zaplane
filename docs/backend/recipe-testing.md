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

# Scaffold a starter recipe for an integration's trigger/action
wp zaplane recipe generate gemcrm --event=contact_created --kind=trigger
```

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

Add your own without touching core:

```php
add_filter( 'zaplane_recipe_factories', function ( array $f ) {
    $f['create_gem_contact'] = function ( array $args ) {
        $contact = \GemCrm\Database\Models\Contact::create( [ 'email' => $args['email'] ?? 'r@example.test' ] );
        return [ 'contact_id' => $contact->id ];
    };
    return $f;
} );
```

---

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

## Running in CI

```bash
wp zaplane recipe run            # exits non-zero on any failure
```

Activate heavy plugins once and pass `--keep-active` if you don't want the
per-recipe activate/restore churn:

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
