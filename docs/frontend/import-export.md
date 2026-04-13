# Import & Export

Zaplane ships two REST endpoints that let you back up, migrate, and clone workflows across installations.

---

## Endpoints

| Method | URL | Purpose |
|--------|-----|---------|
| `POST` | `/wp-json/zaplane/v1/export` | Export one, several, or all workflows |
| `POST` | `/wp-json/zaplane/v1/import` | Import a previously exported payload |

Both endpoints require the caller to be logged in as an administrator (`manage_options` capability).

---

## Export

### Request

```
POST /wp-json/zaplane/v1/export
Content-Type: application/json
```

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `workflow_ids` | `integer[]` | No | — | IDs of workflows to export. **Omit the field (or send an empty array) to export every workflow on the site.** |
| `versions` | `string` | No | `"all"` | `"all"` exports every saved version. `"active"` exports only the currently active version of each workflow. |
| `include_runs` | `boolean` | No | `false` | When `true`, each version also contains its full run history (runs + node runs). |

#### Examples

**Export all workflows (no runs, all versions)**
```json
{}
```

**Export all workflows including run history**
```json
{
  "include_runs": true
}
```

**Export specific workflows, active version only**
```json
{
  "workflow_ids": [3, 7, 12],
  "versions": "active"
}
```

**Export a single workflow with all history**
```json
{
  "workflow_ids": [5],
  "versions": "all",
  "include_runs": true
}
```

### Response structure

```jsonc
{
  "format_version": "1.0",        // bump this when the schema changes
  "exported_at": "2026-03-18 10:00:00",
  "workflows": [
    {
      "title": "Lead Nurture",
      "name": "lead-nurture",
      "status": "active",         // original status; always imported as "draft"
      "layout": "LR",
      "versions": [ /* see Versions object */ ],
      "connections": [ /* see Connections object */ ]
    }
  ]
}
```

#### Versions object

```jsonc
{
  "graph_json": {
    "nodes": [ /* React Flow nodes */ ],
    "edges": [ /* React Flow edges */ ]
  },
  "graph_hash": "sha256-of-graph-json",
  "is_active": true,
  "version_number": 4,
  "created_at": "2026-02-01 09:15:00",
  "runs": [ /* only present when include_runs=true, see Runs object */ ]
}
```

#### Connections object

Connections are included for reference only. **Credentials are never exported.**

```jsonc
{
  "original_id": 5,          // the ID on the source site (used for reference only)
  "app": "gmail",
  "name": "Company Gmail",
  "auth_type": "oauth2",     // "api_key" | "oauth2" | "basic"
  "status": "active"
}
```

> The `connection_id` field inside each node's `data` object is **nulled out** during import. The `connections` array tells the user which connections to recreate and re-link on the destination site.

#### Runs object (`include_runs: true` only)

```jsonc
{
  "trigger_data": { /* original trigger payload */ },
  "status": "completed",    // "running" | "completed" | "failed"
  "start_node_key": 1,
  "target_node_key": null,
  "is_test": false,
  "started_at": "2026-02-10 08:00:00",
  "finished_at": "2026-02-10 08:00:03",
  "last_error": null,
  "node_runs": [
    {
      "_id": 234,                    // original DB ID — used to rebuild parent links on import
      "node_key": 1,
      "parent_node_run_id": null,    // original parent ID (resolved on import via _id map)
      "iteration": null,
      "status": "completed",
      "input_json": { /* ... */ },
      "output_json": { /* ... */ },
      "attempts": 1,
      "started_at": "2026-02-10 08:00:00",
      "finished_at": "2026-02-10 08:00:01"
    }
  ]
}
```

---

## Import

### Request

The endpoint accepts **two content types** — use whichever fits your UI:

---

#### Option A — JSON file upload (`multipart/form-data`)

Send the exported `.json` file as a form field named **`file`**.

```
POST /wp-json/zaplane/v1/import
Content-Type: multipart/form-data

file=@path/to/export.json
```

cURL example:
```bash
curl -X POST https://example.com/wp-json/zaplane/v1/import \
  -H "X-WP-Nonce: <nonce>" \
  -F "file=@/path/to/export.json"
```

JavaScript `fetch` example:
```js
const formData = new FormData();
formData.append('file', jsonFile); // jsonFile is a File object from <input type="file">

await fetch('/wp-json/zaplane/v1/import', {
  method: 'POST',
  headers: { 'X-WP-Nonce': wpApiSettings.nonce },
  body: formData,
});
```

Rules for file uploads:
- Field name must be `file`
- File extension must be `.json`
- The file content must be valid JSON matching the export schema

**Upload error codes**

| Code | Meaning |
|------|---------|
| `upload_error` | PHP upload failed (size limit, partial upload, etc.) — message describes the specific error |
| `invalid_file_type` | File extension is not `.json` |
| `file_read_error` | Server could not read the temporary file |
| `invalid_json` | File content is not valid JSON |

---

#### Option B — Raw JSON body (`application/json`)

POST the export payload directly as the request body.

```
POST /wp-json/zaplane/v1/import
Content-Type: application/json
```

```jsonc
{
  "format_version": "1.0",
  "exported_at": "2026-03-18 10:00:00",   // informational, not validated
  "workflows": [ /* one or more workflow objects from the export */ ]
}
```

---

There are no additional parameters — the import always creates new workflows and never overwrites existing ones.

### What happens during import

1. **A new workflow is created** for each entry in `workflows[]`. The `status` is always set to `"draft"` regardless of what the export says — the user activates it manually when ready.
2. **All versions are recreated** in the order they appear in the payload. The `graph_json` is imported as-is (with connection IDs stripped — see below).
3. **The graph hash is recalculated** from the imported (stripped) graph so it stays consistent on the destination site.
4. **An active version is guaranteed.** If none of the imported versions had `is_active: true`, the last imported version is automatically activated.
5. **Connections are stripped from nodes.** Every `connection_id` inside node `data` is set to `null`. The `connections[]` array in the payload is returned back to the caller as `connections_to_relink` so the frontend can guide the user to re-link or recreate them.
6. **Runs are imported if present.** Node run parent/child hierarchy is rebuilt via a two-pass algorithm using the `_id` reference keys in the exported node runs.

### Response

```jsonc
{
  "imported": 2,
  "failed": 0,
  "results": [
    {
      "workflow_id": 42,             // new ID on this site
      "title": "Lead Nurture",
      "versions_imported": 3,
      "versions": [
        {
          "original_hash": "abc123...",
          "new_id": 87,
          "is_active": false
        },
        {
          "original_hash": "def456...",
          "new_id": 88,
          "is_active": true           // this version was activated
        }
      ],
      "connections_to_relink": [
        {
          "original_id": 5,
          "app": "gmail",
          "name": "Company Gmail",
          "auth_type": "oauth2",
          "status": "active"
        }
      ]
    }
  ],
  "errors": [
    {
      "index": 2,                    // 0-based position in the workflows array
      "title": "Broken Workflow",
      "error": "Workflow versions data is missing."
    }
  ]
}
```

> Errors are **per-workflow** and non-fatal. A single bad workflow in the payload does not prevent the others from being imported.

---

## Connection handling

Connections contain credentials that must never leave the site (encryption keys differ per installation). The export/import flow handles this as follows:

| Stage | What happens |
|-------|-------------|
| **Export** | The `connections[]` array includes `app`, `name`, `auth_type`, and `status`. Encrypted credentials are **excluded**. |
| **Node graph** | Node `data.connection_id` values are included in the exported graph unchanged (they reflect IDs on the source site). |
| **Import** | Every `connection_id` inside node data is set to `null`. The workflow is fully importable but actions requiring a connection will fail until re-linked. |
| **After import** | The `connections_to_relink` array in the response tells the frontend which connections the user needs to create or select. |

The recommended UI flow is:

1. Show the user the `connections_to_relink` list.
2. For each entry, let them pick an existing connection (`GET /zaplane/v1/connections?app=gmail`) or create a new one.
3. Update the relevant nodes in the workflow graph with the new `connection_id`.
4. Save the workflow (`PUT /zaplane/v1/workflows/{id}`).
5. Activate the workflow (`POST /zaplane/v1/workflows/{id}/versions/{version_id}/activate`).

---

## Format version

The export payload carries a `format_version` field (currently `"1.0"`). The import endpoint rejects payloads whose `format_version` is **higher** than the version understood by the current installation, returning:

```json
{
  "code": "unsupported_format_version",
  "message": "Export format version 2.0 is not supported by this installation.",
  "data": { "status": 400 }
}
```

---

## Full payload examples

### Minimal export (no runs, active version only)

**Request**
```json
{
  "workflow_ids": [1],
  "versions": "active"
}
```

**Response**
```json
{
  "format_version": "1.0",
  "exported_at": "2026-03-18 10:00:00",
  "workflows": [
    {
      "title": "New Lead Alert",
      "name": "new-lead-alert",
      "status": "active",
      "layout": "LR",
      "versions": [
        {
          "graph_json": {
            "nodes": [
              {
                "id": "1",
                "type": "trigger",
                "data": {
                  "app": "wordpress",
                  "event": "user_register",
                  "name": "New User Registered"
                },
                "position": { "x": 0, "y": 0 }
              },
              {
                "id": "2",
                "type": "action",
                "data": {
                  "app": "gmail",
                  "event": "send_email",
                  "name": "Send Welcome Email",
                  "connection_id": 5,
                  "config": {
                    "to": "{{1.email}}",
                    "subject": "Welcome!",
                    "body": "Hi {{1.display_name}}, welcome aboard."
                  }
                },
                "position": { "x": 300, "y": 0 }
              }
            ],
            "edges": [
              { "id": "e1-2", "source": "1", "target": "2" }
            ]
          },
          "graph_hash": "a3f8c...",
          "is_active": true,
          "version_number": 2,
          "created_at": "2026-03-01 12:00:00"
        }
      ],
      "connections": [
        {
          "original_id": 5,
          "app": "gmail",
          "name": "Company Gmail",
          "auth_type": "oauth2",
          "status": "active"
        }
      ]
    }
  ]
}
```

### Importing the above payload

**Request** — POST the export response body as-is.

**Response**
```json
{
  "imported": 1,
  "failed": 0,
  "results": [
    {
      "workflow_id": 99,
      "title": "New Lead Alert",
      "versions_imported": 1,
      "versions": [
        {
          "original_hash": "a3f8c...",
          "new_id": 203,
          "is_active": true
        }
      ],
      "connections_to_relink": [
        {
          "original_id": 5,
          "app": "gmail",
          "name": "Company Gmail",
          "auth_type": "oauth2",
          "status": "active"
        }
      ]
    }
  ],
  "errors": []
}
```

After import, node `"2"` in the new workflow has `connection_id: null`. The user must create or select a Gmail connection and update the node before activating.
