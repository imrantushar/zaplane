# Recipes and group recipes

A **recipe** is a saved workflow blueprint. Using one creates a new draft workflow.

A **group recipe** holds several workflows. Its setup lets an admin pick which ones
to create, switch some of their steps on or off, and fill in a few values; the
workflows are then created together in a new folder.

Every endpoint below is under `zaplane/v1` and needs `manage_options`.

---

## Recipes

### Shape

```json
{
  "id": 11,
  "type": "group",
  "slug": "woocommerce-customer-lifecycle",
  "title": "WooCommerce Customer Lifecycle",
  "description": "Set up your store's customer emails in one go…",
  "thumbnail_id": null,
  "thumbnail_url": null,
  "integration_icons": ["woo.svg", "crm.svg", "delay"],
  "created_by": 0,
  "created_at": "2026-09-13 08:20:00",
  "updated_at": "2026-09-13 08:20:00",
  "workflows": [{ "key": "abandoned_cart", "title": "Recover abandoned carts" }]
}
```

- `type` is `workflow` (creates one workflow) or `group`. Only a group has `workflows`.
- `slug` is set on recipes that ship with the plugin (see [Seeding](#seeding)) and is
  null on recipes saved from a workflow.
- The blueprint itself is never returned.

### Endpoints

| Method | Route | What it does |
|---|---|---|
| GET | `/recipes?page=&per_page=&type=` | Recipes by title. `type` is `workflow` or `group`. `per_page` is at most 100. |
| GET | `/recipes/{id}` | One recipe. |
| PUT | `/recipes/{id}` | Changes `title`, `description` or `thumbnail_id`. |
| DELETE | `/recipes/{id}` | Deletes it. A shipped recipe stays deleted through updates. |
| POST | `/workflows/{id}/to-recipe` | Saves a workflow's active version as a recipe. Takes `title` (required), `description`, `thumbnail_id`. |
| POST | `/recipes/{id}/to-workflow` | Creates a draft workflow from a recipe, with an optional `title`. Returns `workflow_id` and `connections_to_relink`. A group recipe answers `400 group_recipe`. |
| GET | `/recipes/{id}/setup` | What a group recipe's setup asks. |
| POST | `/recipes/{id}/setup` | Creates a group recipe's workflows. |
| POST | `/folders/{id}/status` | Turns every workflow in a folder on, or pauses them. |

---

## Group recipe setup

### GET `/recipes/{id}/setup`

```json
{
  "id": 11,
  "title": "WooCommerce Customer Lifecycle",
  "description": "…",
  "folder_title": "WooCommerce Customer Lifecycle",
  "workflows": [
    {
      "key": "win_back",
      "title": "Win back inactive customers",
      "description": "Reaches out to customers who haven't ordered for a while.",
      "default": true,
      "icons": ["woo.svg", "crm.svg", "delay"],
      "options": [
        { "key": "coupon", "label": "Follow up with a personal coupon a week later", "description": "", "default": true }
      ]
    }
  ],
  "values": [
    {
      "key": "coupon_percent",
      "type": "number",
      "label": "Coupon discount",
      "description": "The discount on every coupon these emails send.",
      "default": 15,
      "min": 1,
      "max": 100,
      "suffix": "%",
      "required": false,
      "used_by": [
        { "workflow": "thank_you_coupon", "option": null },
        { "workflow": "win_back", "option": "coupon" }
      ]
    }
  ],
  "apps": [
    {
      "slug": "woocommerce",
      "name": "WooCommerce",
      "icon": "woo.svg",
      "category": "app",
      "requires_connection": false,
      "plugin_active": true,
      "workflows": ["abandoned_cart", "win_back"]
    }
  ],
  "folders": [{ "id": 4, "title": "WooCommerce Customer Lifecycle" }]
}
```

- A value matters only while a step that reads it will be created: its workflow is on,
  and its `option` is null or switched on. Only those values are checked when the
  workflows are created.
- An app with `requires_connection` needs a connection picked. `plugin_active` is false
  when a plugin the app needs isn't active; the workflows are still created.
- `folders` are earlier setups of this recipe, newest first.

### POST `/recipes/{id}/setup`

```json
{
  "workflows": { "abandoned_cart": true, "thank_you_coupon": false },
  "options": { "abandoned_cart": { "last_chance": false } },
  "values": { "coupon_percent": 20 },
  "connections": { "slack": 3 },
  "folder_title": "Customer emails",
  "activate": false
}
```

Anything left out takes the group's default. The workflows are created as drafts in a
new folder, and a folder title that is already taken gets a number. With `activate`,
each workflow is then turned on if it passes the checks any workflow must pass to go
live; one that doesn't stays a draft and gives the reason in `error`.

```json
{
  "folder": { "id": 4, "title": "Customer emails" },
  "workflows": [
    { "key": "abandoned_cart", "id": 31, "title": "Recover abandoned carts", "status": "draft", "error": null }
  ]
}
```

| Error | When |
|---|---|
| `400 invalid_setup` | Nothing is picked, a value is out of range, or a picked connection doesn't exist |
| `400 not_a_group` | The recipe creates a single workflow |
| `404 not_found` | The recipe doesn't exist |

### POST `/folders/{id}/status`

`{ "status": "active" }` turns on every workflow in the folder; `{ "status": "paused" }`
pauses the ones that are on. It returns `workflows: [{ id, title, status, error }]`. A
workflow that can't go live keeps its status and gives the reason.

---

## A group recipe's blueprint

```json
{
  "folder": "WooCommerce Customer Lifecycle",
  "values": [
    { "key": "coupon_percent", "type": "number", "label": "Coupon discount", "default": 15, "min": 1, "max": 100, "suffix": "%" }
  ],
  "workflows": [
    {
      "key": "win_back",
      "title": "Win back inactive customers",
      "description": "…",
      "default": true,
      "layout": "LR",
      "graph": { "nodes": [], "edges": [] },
      "options": [
        { "key": "coupon", "label": "Follow up with a personal coupon a week later", "default": true, "nodes": ["3", "4", "5"] }
      ]
    }
  ]
}
```

- `options[].nodes` are the steps that exist only while the option is on. When it is
  off they are removed, and a line that ran through them is joined to the step after
  them. No other step may read a removed step's output:
  `RecipeGroupBuilder::dangling_references()` finds one, and
  `CustomerLifecycleGroupTest` builds every combination of the shipped group to check.
- A step reads a value as `{{setup.key}}`. It is written in when the workflows are
  created, so a saved workflow never contains one.

`Zaplane\Services\RecipeGroupBuilder` works out what to create, on arrays alone.
`Zaplane\Services\RecipeGroupService` reads the site (apps, plugins, connections) and
writes the folder and workflows.

---

## Seeding

Recipes that ship with the plugin are seeded on install and on every update, through
`Zaplane\Database\Seeders\RecipeSeeding::save( $slug, $attributes )`:

- A recipe is found by its slug, so renaming it doesn't add a second copy. One seeded
  before slugs existed is matched once by its title, and only if `created_by` is 0.
- Its blueprint and icons are written again, so a fix reaches sites that already have
  it. Its title and description are left alone.
- Deleting it adds the slug to the `zaplane_dismissed_recipes` option, and it isn't
  seeded again.
