# Trigger Testing — Frontend Guide

Trigger testing uses the **exact same two endpoints** as action testing. No new endpoint was added. The only difference is what you put in `target_node` and `input` when calling `execute-node`.

---

## Flow Overview

Test top-to-bottom, same as actions:

1. User clicks **Test** on the trigger node → call `execute-node` with the trigger node + simulated hook args
2. Backend calls `resolve_trigger()`, stores the payload as a test NodeRun
3. User clicks **Test** on a downstream action node → `condition-variables` now returns **trigger variables + previous action variables**

---

## Step 1 — Test a trigger node

### `POST /zaplane/v1/execute-node`

#### Request

```json
{
  "workflow_id": 1,
  "workflow_version_id": 1,
  "workflow_hash": "a461bf77bc4e4d...",
  "target_node": {
    "id": "2",
    "type": "trigger",
    "data": {
      "app": "wordpress",
      "event": "publish_post",
      "icon": "wordpress.svg",
      "name": "WordPress",
      "hook": "publish_post",
      "config": { "post_type": "post" }
    }
  },
  "input": [1]
}
```

#### What changed vs action requests

| Field | Action node | Trigger node |
|---|---|---|
| `target_node.type` | `"action"` | `"trigger"` |
| `input` | merged into config as expression values | **positional hook args** passed to `resolve_trigger()` |

`input` must be a **JSON array** that matches what WordPress would fire on the hook. The values are positional, matching the `do_action()` call order.

#### `input` reference by trigger event

| Integration | Event | `input` format |
|---|---|---|
| WordPress | `publish_post` | `[post_id]` |
| WordPress | `save_post` | `[post_id, post_object, is_update]` |
| WordPress | `post_updated` | `[post_id, post_after, post_before]` |
| WordPress | `user_register` | `[user_id]` |
| WordPress | `profile_update` | `[user_id, old_user_data]` |
| WordPress | `wp_login` | `["username", user_object]` |
| WordPress | `comment_post` | `[comment_id, comment_approved]` |
| GemCRM | `contact_created` | `[contact_id, contact_data_array]` |
| GemCRM | `contact_updated` | `[contact_id, contact_data_array]` |
| GemCRM | `tag_attached` | `[contact_id, tag_id]` |
| Action Scheduler | `on_interval` | `[workflow_id, config_array]` |
| Action Scheduler | `on_cron` | `[workflow_id, config_array]` |

For any integration not listed, check the `resolve_trigger()` method in the integration file — the args order matches `$args[0]`, `$args[1]`, etc.

#### Response — trigger fired

```json
{
  "status": "success",
  "code": "SUCCESS",
  "data": {
    "run_id": 5,
    "node_run_id": 8,
    "node": {
      "id": 2,
      "app": "wordpress",
      "event": "publish_post",
      "label": null
    },
    "input": [1],
    "output": {
      "post_id": 1,
      "title": "Hello World",
      "status": "publish",
      "type": "post",
      "author_id": 1,
      "date": "2026-04-26 10:00:00"
    }
  }
}
```

> **Note:** Trigger `output` is a **flat object** (the resolved payload), not wrapped in `{ port, data }` like action output.

#### Response — trigger filtered out

When `resolve_trigger()` returns `false` — e.g. wrong post type, missing required field — the output is an empty object:

```json
{
  "status": "success",
  "code": "SUCCESS",
  "data": {
    "run_id": 5,
    "node_run_id": 8,
    "node": { "id": 2, "app": "wordpress", "event": "publish_post", "label": null },
    "input": [1],
    "output": {}
  }
}
```

Detect filtered-out state with:

```js
const isFilteredOut = Object.keys(data.output).length === 0;
```

Show the user a message like _"Trigger would not fire for this data — check your trigger configuration."_

---

## Step 2 — Fetch condition variables (no change)

`condition-variables` request is **unchanged**. After the trigger test run is stored, trigger variables automatically appear in the response alongside action variables.

### `POST /zaplane/v1/condition-variables`

```json
{
  "workflow_id": 1,
  "workflow_version_id": 1,
  "workflow_hash": "a461bf77bc4e4d...",
  "target_node_key": "13",
  "graph": {
    "nodes": [
      {
        "id": "2",
        "type": "trigger",
        "data": { "app": "wordpress", "event": "publish_post", "hook": "publish_post", "config": {} }
      },
      {
        "id": "13",
        "type": "action",
        "data": { "app": "wordpress", "event": "get_users", "config": {} }
      }
    ],
    "edges": [{ "id": "e2-13", "source": "2", "target": "13" }]
  }
}
```

#### Response — trigger variables now included

```json
{
  "status": "success",
  "code": "SUCCESS",
  "data": [
    {
      "node_id": 2,
      "node_name": "WordPress",
      "node_event": "publish_post",
      "variables": [
        { "key": "post_id",  "type": "integer", "sample": 1 },
        { "key": "title",    "type": "string",  "sample": "Hello World" },
        { "key": "status",   "type": "string",  "sample": "publish" },
        { "key": "type",     "type": "string",  "sample": "post" },
        { "key": "author_id","type": "integer", "sample": 1 }
      ]
    },
    {
      "node_id": 13,
      "node_name": "WordPress",
      "node_event": "get_users",
      "variables": [
        { "key": "port",          "type": "string",  "sample": "main" },
        { "key": "data.users",    "type": "array",   "sample": "1 items" },
        { "key": "data.users[].ID","type": "integer","sample": 1 }
      ]
    }
  ]
}
```

Trigger node variables will always appear **first** (closest to the start of the graph walk).

---

## UI Checklist

- [ ] Show a **Test** button on trigger nodes (same as action nodes)
- [ ] When the trigger node has no previous test run, show _"No test data yet — click Test to simulate"_
- [ ] Send `input` as a JSON array, not an object — use `Object.values(sampleArgs)` if building from a form
- [ ] Detect filtered-out response (`output === {}`) and display a warning
- [ ] After a successful trigger test, proceed to test downstream action nodes as normal — trigger variables will be available in the expression picker via `condition-variables`
- [ ] Display trigger output shape in `TestDetails` using the same variable list UI as actions
