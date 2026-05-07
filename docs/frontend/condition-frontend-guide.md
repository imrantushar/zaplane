# Frontend Guide: Condition & Filter Nodes

## Quick Summary

| Feature    | Node Type    | App Slug    | Action Key | Outputs         | Behavior                                      |
|------------|-------------|-------------|------------|-----------------|-----------------------------------------------|
| Condition  | `condition` | `condition` | `if`       | `true`, `false` | Two branches - one for true, one for false     |
| Filter     | `filter`    | `filter`    | `filter`   | `main`          | One output - continues if true, STOPS if false |

---

## Real-World Example

### Workflow: "Notify Editor When Post Published"

```
[Trigger: Post Published] --> [Filter: Only "news" category] --> [Condition: Check author role]
                                     |                              |            |
                                     |                          (true)       (false)
                                     |                              |            |
                                  STOPS if                    [Send Slack]  [Send Email
                                  not "news"                   to #editors   to author]
```

**Filter** = Gate. Pass or stop. No branching.
**Condition** = Fork. Two paths based on result.

---

## 1. Filter Node

### What It Does
Filter checks conditions. If ALL conditions pass, data flows to the next node. If conditions fail, execution **stops completely** - no nodes after the filter will run.

### Node Structure in React Flow

```json
{
  "id": "2",
  "type": "filter",
  "position": { "x": 400, "y": 100 },
  "data": {
    "app": "filter",
    "action": "filter",
    "label": "Filter",
    "config": {
      "conditions": {
        "logic": "AND",
        "conditions": [
          {
            "left": "{{1.post_type}}",
            "operator": "==",
            "right": "post"
          },
          {
            "left": "{{1.post_status}}",
            "operator": "==",
            "right": "publish"
          }
        ]
      }
    }
  }
}
```

### Filter Edges
Filter has only ONE output handle: `main`. Connect it like any regular action node.

```json
{
  "id": "e2-3",
  "source": "2",
  "target": "3"
}
```

No `sourceHandle` needed since there's only one output.

### What Happens When Filter Runs
- **Conditions pass**: Data flows through to the next connected node(s).
- **Conditions fail**: Execution stops. No downstream nodes run. The run completes normally (not failed).

---

## 2. Condition Node

### What It Does
Condition evaluates conditions and sends data down ONE of TWO branches:
- **true branch**: Conditions passed
- **false branch**: Conditions failed

Both branches can have additional nodes connected.

### Node Structure in React Flow

```json
{
  "id": "3",
  "type": "condition",
  "position": { "x": 600, "y": 100 },
  "data": {
    "app": "condition",
    "action": "if",
    "label": "If Condition",
    "config": {
      "conditions": {
        "logic": "AND",
        "conditions": [
          {
            "left": "{{1.post_author}}",
            "operator": "==",
            "right": "admin"
          }
        ]
      }
    }
  }
}
```

### Condition Edges - IMPORTANT
Condition has TWO output handles: `true` and `false`. Each edge MUST specify `sourceHandle`.

```json
[
  {
    "id": "e3-4",
    "source": "3",
    "target": "4",
    "sourceHandle": "true"
  },
  {
    "id": "e3-5",
    "source": "3",
    "target": "5",
    "sourceHandle": "false"
  }
]
```

**If you forget `sourceHandle`**, the backend will send data down ALL edges from the condition, which breaks the if/else logic.

---

## 3. Condition Config (Same for Both Filter & Condition)

Both node types use the exact same condition configuration structure stored at `node.data.config.conditions`.

### Simple: Single Condition

```json
{
  "logic": "AND",
  "conditions": [
    {
      "left": "{{1.post_status}}",
      "operator": "==",
      "right": "publish"
    }
  ]
}
```

### Multiple Conditions with AND

All must be true:

```json
{
  "logic": "AND",
  "conditions": [
    {
      "left": "{{1.post_status}}",
      "operator": "==",
      "right": "publish"
    },
    {
      "left": "{{1.post_type}}",
      "operator": "==",
      "right": "post"
    }
  ]
}
```

### Multiple Conditions with OR

At least one must be true:

```json
{
  "logic": "OR",
  "conditions": [
    {
      "left": "{{1.post_author}}",
      "operator": "==",
      "right": "admin"
    },
    {
      "left": "{{1.post_author}}",
      "operator": "==",
      "right": "editor"
    }
  ]
}
```

### Nested Groups (Advanced)

Mix AND/OR with nesting:

```json
{
  "logic": "AND",
  "conditions": [
    {
      "left": "{{1.post_status}}",
      "operator": "==",
      "right": "publish"
    },
    {
      "logic": "OR",
      "conditions": [
        {
          "left": "{{1.post_type}}",
          "operator": "==",
          "right": "post"
        },
        {
          "left": "{{1.post_type}}",
          "operator": "==",
          "right": "page"
        }
      ]
    }
  ]
}
```

This means: `status == publish AND (type == post OR type == page)`

---

## 4. Available Operators

| Operator       | Value           | Description                        |
|---------------|-----------------|-------------------------------------|
| Equals        | `==`            | Left equals Right                   |
| Not Equals    | `!=`            | Left does not equal Right           |
| Greater Than  | `>`             | Left > Right (numeric)              |
| Less Than     | `<`             | Left < Right (numeric)              |
| Greater/Equal | `>=`            | Left >= Right (numeric)             |
| Less/Equal    | `<=`            | Left <= Right (numeric)             |
| Contains      | `contains`      | Left string contains Right string   |
| Not Contains  | `not_contains`  | Left string does NOT contain Right  |
| Starts With   | `starts_with`   | Left string starts with Right       |
| Ends With     | `ends_with`     | Left string ends with Right         |
| Is Empty      | `is_empty`      | Left is empty (ignores Right)       |
| Is Not Empty  | `is_not_empty`  | Left is not empty (ignores Right)   |

**Note**: For `is_empty` and `is_not_empty`, the `right` field is ignored but should still be present in the JSON (can be empty string `""`).

---

## 5. Expression Syntax

Expressions reference data from previous nodes using the pattern: `{{nodeId.path}}`

| Expression             | Resolves To                         |
|------------------------|-------------------------------------|
| `{{1.post_title}}`     | Post title from node 1              |
| `{{1.post_status}}`    | Post status from node 1             |
| `{{3.id}}`             | ID from node 3's output             |
| `{{1.meta.color}}`     | Nested: `output.meta.color`         |

The `nodeId` is the React Flow node `id` (the number in the graph). The `path` uses dot notation to traverse nested objects.

**Static values** (no `{{}}`) are used as-is:
- `"publish"` - literal string
- `"42"` - literal string (compared as string unless both sides are numeric)

---

## 6. Fetching Variables for the Condition/Filter UI

When the user opens a condition or filter node to configure it, you need to show them what variables are available from previous nodes.

### API Call

```
GET /wp-json/zaplane/v1/condition-variables?workflow_hash={hash}&target_node_key={nodeId}
```

- `workflow_hash`: The current workflow version hash (from the graph API response)
- `target_node_key`: The ID of the condition/filter node being configured

### Response

```json
{
  "status": "success",
  "code": "SUCCESS",
  "data": [
    {
      "node_id": 1,
      "variables": [
        { "key": "ID", "type": "integer", "sample": 42 },
        { "key": "post_title", "type": "string", "sample": "Hello World" },
        { "key": "post_status", "type": "string", "sample": "publish" },
        { "key": "post_author", "type": "string", "sample": "1" }
      ]
    },
    {
      "node_id": 3,
      "variables": [
        { "key": "success", "type": "boolean", "sample": true },
        { "key": "message", "type": "string", "sample": "Email sent" }
      ]
    }
  ]
}
```

Each item in `data` represents a previous node. The `variables` array shows what that node outputs. Use these to build the expression picker in the UI.

To build an expression from a variable: `{{node_id.key}}` e.g. `{{1.post_title}}`

**Important**: Variables only appear if the workflow has been run at least once (test run or real run). If `variables` is empty for a node, show the node but indicate "Run this node first to see available variables".

---

## 7. Saving the Graph

When saving the workflow, send the full graph (nodes + edges) via PUT:

```
PUT /wp-json/zaplane/v1/workflows/{id}
Content-Type: application/json

{
  "nodes": [
    {
      "id": "1",
      "type": "trigger",
      "position": { "x": 100, "y": 100 },
      "data": {
        "app": "wordpress",
        "action": "publish_post",
        "label": "Post Published",
        "config": { "post_type": "post" }
      }
    },
    {
      "id": "2",
      "type": "filter",
      "position": { "x": 350, "y": 100 },
      "data": {
        "app": "filter",
        "action": "filter",
        "label": "Filter",
        "config": {
          "conditions": {
            "logic": "AND",
            "conditions": [
              {
                "left": "{{1.post_status}}",
                "operator": "==",
                "right": "publish"
              }
            ]
          }
        }
      }
    },
    {
      "id": "3",
      "type": "condition",
      "position": { "x": 600, "y": 100 },
      "data": {
        "app": "condition",
        "action": "if",
        "label": "Check Author",
        "config": {
          "conditions": {
            "logic": "AND",
            "conditions": [
              {
                "left": "{{1.post_author}}",
                "operator": "==",
                "right": "1"
              }
            ]
          }
        }
      }
    },
    {
      "id": "4",
      "type": "action",
      "position": { "x": 900, "y": 50 },
      "data": {
        "app": "slack",
        "action": "send_message",
        "label": "Notify Slack",
        "config": { "channel": "#news", "message": "{{1.post_title}}" }
      }
    },
    {
      "id": "5",
      "type": "action",
      "position": { "x": 900, "y": 250 },
      "data": {
        "app": "wordpress",
        "action": "send_email",
        "label": "Email Author",
        "config": { "to": "author@site.com" }
      }
    }
  ],
  "edges": [
    { "id": "e1-2", "source": "1", "target": "2" },
    { "id": "e2-3", "source": "2", "target": "3" },
    { "id": "e3-4", "source": "3", "target": "4", "sourceHandle": "true" },
    { "id": "e3-5", "source": "3", "target": "5", "sourceHandle": "false" }
  ]
}
```

---

## 8. UI Implementation Checklist

### Filter Node UI
- [ ] Same condition builder UI as Condition (AND/OR groups, left/operator/right rows)
- [ ] Single output handle at the bottom (no true/false split)
- [ ] Visual indicator that it's a gate (e.g. funnel icon, "Only continue if..." label)
- [ ] When dragging from filter, only ONE edge comes out

### Condition Node UI
- [ ] Same condition builder UI as Filter
- [ ] TWO output handles: one labeled "True" (green), one labeled "False" (red)
- [ ] When dragging from condition, user picks which handle (true or false)
- [ ] Each edge MUST set `sourceHandle: "true"` or `sourceHandle: "false"`

### Condition Builder (Shared Component)
- [ ] Add condition row button (left + operator + right)
- [ ] Remove condition row button (X)
- [ ] AND/OR toggle for each group
- [ ] Add nested group button
- [ ] Left value: text input with variable picker dropdown (from condition-variables API)
- [ ] Operator: dropdown select
- [ ] Right value: text input with variable picker (or plain text for static values)
- [ ] For `is_empty` / `is_not_empty` operators: hide the right value field

---

## 9. Key Differences at a Glance

```
FILTER (Gate)                          CONDITION (Fork)
+------------------+                   +------------------+
|    Filter Node   |                   |  Condition Node  |
|                  |                   |                  |
| post_type == post|                   | author == admin  |
+--------+---------+                   +----+--------+----+
         |                                  |        |
      [main]                            [true]   [false]
         |                                  |        |
    Next Action                        Action A  Action B
    (or stops if                       (runs if   (runs if
     conditions fail)                   true)      false)
```
