# Folders & Recipes — Frontend API Guide

## What Changed

The old system mixed folders and recipes together. The new system separates them cleanly:

| | Old | New |
|---|---|---|
| Folders | Held recipes, had sub-folders | Hold **workflows**, no sub-folders |
| Recipes | Lived inside folders | Standalone, no folder. Just a flat paginated list |
| Workflows | No folder concept | Can be assigned to a folder |

**One rule to remember:** Folders are for organizing workflows. Recipes are completely independent and have no folders.

---

## Base URL

```
/wp-json/zaplane/v1
```

All endpoints require an admin session (`manage_options` capability). Include the WP nonce header:

```
X-WP-Nonce: {nonce from ZaplaneGlobal.nonce}
```

---

## Part 1 — Folders

Folders group workflows. A folder has no parent and no children — it is a flat list.

### Folder Object

```json
{
  "id": 3,
  "title": "E-commerce",
  "workflow_count": 5,
  "created_by": 1,
  "created_at": "2024-06-01 10:00:00",
  "updated_at": "2024-06-01 10:00:00"
}
```

---

### Step 1 — List All Folders

**`GET /folders`**

```
GET /wp-json/zaplane/v1/folders
GET /wp-json/zaplane/v1/folders?page=1&per_page=20
```

**Response**
```json
{
  "data": [
    { "id": 1, "title": "Marketing", "workflow_count": 3, ... },
    { "id": 2, "title": "E-commerce", "workflow_count": 7, ... }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 2,
    "total_pages": 1
  }
}
```

Use this to populate the folders sidebar/list on the Folders page.

---

### Step 2 — Create a Folder

**`POST /folders`**

```json
{
  "title": "Marketing Automations"
}
```

**Response** — the created folder object.
```json
{
  "id": 4,
  "title": "Marketing Automations",
  "workflow_count": 0,
  "created_by": 1,
  "created_at": "2024-06-10 09:00:00",
  "updated_at": "2024-06-10 09:00:00"
}
```

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 400 | `missing_title` | `title` field is empty |

---

### Step 3 — Rename a Folder

**`PATCH /folders/{id}`**

```json
{
  "title": "Email Marketing"
}
```

**Response** — updated folder object.

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 404 | `not_found` | Folder does not exist |

---

### Step 4 — Delete a Folder

**`DELETE /folders/{id}`**

The folder **must have zero workflows** assigned to it. If workflows are still in the folder the API returns 409.

**Response**
```json
{ "deleted": true, "id": 4 }
```

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 404 | `not_found` | Folder does not exist |
| 409 | `folder_not_empty` | One or more workflows are still assigned to this folder |

> **UI tip:** Show the count from `workflow_count`. If it is greater than 0, either disable the delete button or show a warning that the user must first move/remove the workflows.

---

### Step 5 — Add a Workflow to a Folder

**`POST /folders/{folder_id}/workflows/{workflow_id}`**

No request body needed.

```
POST /wp-json/zaplane/v1/folders/4/workflows/12
```

**Response** — the updated workflow object with `folder_id` now set.
```json
{
  "id": 12,
  "folder_id": 4,
  "title": "My Workflow",
  "status": "active",
  ...
}
```

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 404 | `not_found` | Folder does not exist |
| 404 | `workflow_not_found` | Workflow does not exist |

---

### Step 6 — Remove a Workflow from a Folder

**`DELETE /folders/{folder_id}/workflows/{workflow_id}`**

```
DELETE /wp-json/zaplane/v1/folders/4/workflows/12
```

**Response**
```json
{ "removed": true, "workflow_id": 12 }
```

The workflow still exists — it just has no folder anymore (`folder_id` becomes `null`).

---

### Step 7 — List Workflows Inside a Folder

**`GET /folders/{id}/workflows`**

```
GET /wp-json/zaplane/v1/folders/4/workflows
GET /wp-json/zaplane/v1/folders/4/workflows?page=1&per_page=20
```

**Response** — identical shape to `GET /workflows`:
```json
{
  "data": [
    {
      "id": 12,
      "folder_id": 4,
      "title": "My Workflow",
      "status": "active",
      "success_runs": 42,
      "failed_runs": 1,
      "created_at": "2024-06-01 10:00:00",
      "updated_at": "2024-06-10 09:00:00"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 1,
    "total_pages": 1
  }
}
```

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 404 | `not_found` | Folder does not exist |

---

## Part 2 — Recipes

Recipes are saved workflow blueprints. They have no folder. They are displayed in a flat paginated list like the workflow list.

**Key rules:**
- A recipe is always created by converting an existing workflow — there is no "blank recipe" creation.
- Converting a recipe back creates a **new** workflow — the recipe is never modified.
- Recipes have no folder. No filtering by folder.

### Recipe Object

```json
{
  "id": 15,
  "title": "Welcome Email Series",
  "description": "Sends a 3-step welcome sequence to new subscribers.",
  "thumbnail_id": 42,
  "thumbnail_url": "https://example.com/wp-content/uploads/thumb.png",
  "created_by": 1,
  "created_at": "2024-06-10 09:00:00",
  "updated_at": "2024-06-10 09:00:00"
}
```

---

### Step 1 — List All Recipes

**`GET /recipes`**

```
GET /wp-json/zaplane/v1/recipes
GET /wp-json/zaplane/v1/recipes?page=1&per_page=20
```

**Response**
```json
{
  "data": [
    {
      "id": 15,
      "title": "Welcome Email Series",
      "description": "Sends a 3-step welcome sequence.",
      "thumbnail_id": 42,
      "thumbnail_url": "https://example.com/wp-content/uploads/thumb.png",
      "created_by": 1,
      "created_at": "2024-06-10 09:00:00",
      "updated_at": "2024-06-10 09:00:00"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 1,
    "total_pages": 1
  }
}
```

---

### Step 2 — Get a Single Recipe

**`GET /recipes/{id}`**

```
GET /wp-json/zaplane/v1/recipes/15
```

**Response** — single recipe object.

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 404 | `not_found` | Recipe does not exist |

---

### Step 3 — Convert a Workflow into a Recipe

> This is the **only** way to create a recipe. There is no "create recipe from scratch" option.

When the user clicks "Save as Recipe" on any workflow (from the workflow list or the workflow editor), open a modal, collect the details, and call this endpoint.

**`POST /workflows/{id}/to-recipe`**

The `{id}` is the **workflow ID** you want to convert.

**What happens internally:**
- The backend reads the workflow's **currently active version** and serializes it into a blueprint (a JSON snapshot of all nodes, edges, and configuration).
- That blueprint is stored as the recipe.
- The **original workflow is not touched** — it keeps running as before.
- The recipe is a completely separate record.

**Request body**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | ✅ | Name for the recipe |
| `description` | string | ❌ | Short description shown on the recipe card |
| `thumbnail_id` | integer | ❌ | WordPress media attachment ID for a thumbnail image |

**Example request**
```json
POST /wp-json/zaplane/v1/workflows/12/to-recipe

{
  "title": "Welcome Email Series",
  "description": "A 3-step onboarding flow for new subscribers.",
  "thumbnail_id": 42
}
```

**Response** — the newly created recipe object.
```json
{
  "id": 15,
  "title": "Welcome Email Series",
  "description": "A 3-step onboarding flow for new subscribers.",
  "thumbnail_id": 42,
  "thumbnail_url": "https://example.com/wp-content/uploads/thumb.png",
  "created_by": 1,
  "created_at": "2024-06-10 09:00:00",
  "updated_at": "2024-06-10 09:00:00"
}
```

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 404 | `workflow_not_found` | Workflow ID does not exist |
| 400 | `missing_title` | `title` field is empty |

**UI modal checklist before calling this endpoint:**
- Text input → `title` (pre-fill with the workflow's current title)
- Textarea → `description` (optional)
- Media/image picker → `thumbnail_id` (optional)
- Submit button calls `POST /workflows/{id}/to-recipe`
- On success: show toast "Recipe created successfully", optionally navigate to the Recipes page

> **Important:** Only workflows that have been saved at least once have an active version. If a workflow is a brand-new draft that has never been saved, the blueprint will be empty. Disable or hide the "Save as Recipe" button for such workflows, or show a message: *"Save the workflow first before converting it to a recipe."*

---

### Step 4 — Update Recipe Metadata

**`PUT /recipes/{id}`**

Use this to rename or update the description/thumbnail after creation.

**Request body** (all fields optional)
| Field | Type | Description |
|-------|------|-------------|
| `title` | string | New recipe name |
| `description` | string | New description |
| `thumbnail_id` | integer\|null | New attachment ID; send `null` to remove thumbnail |

**Example**
```json
{
  "title": "Onboarding Email Flow",
  "description": "Updated description.",
  "thumbnail_id": null
}
```

**Response** — updated recipe object.

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 404 | `not_found` | Recipe does not exist |

---

### Step 5 — Convert a Recipe Back to a Workflow (Recipe → Workflow)

**`POST /recipes/{id}/to-workflow`**

Creates a brand-new **draft** workflow from the recipe blueprint. The recipe is not modified.

**Request body**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | ❌ | Override the workflow title; if omitted the recipe title is used |

**Example**
```json
{
  "title": "Welcome Flow (copy)"
}
```

**Response**
```json
{
  "workflow_id": 27,
  "title": "Welcome Flow (copy)",
  "status": "draft",
  "connections_to_relink": [
    {
      "original_id": 5,
      "app": "mailchimp",
      "name": "My Mailchimp Account",
      "auth_type": "oauth2",
      "status": "active"
    }
  ]
}
```

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 404 | `not_found` | Recipe does not exist |
| 422 | `empty_blueprint` | Recipe blueprint is empty or corrupted |
| 422 | `import_failed` | Blueprint structure is invalid |

> **`connections_to_relink`** — After import, connection IDs inside the workflow nodes are stripped because they point to this site's database records and may not be valid. If this array is non-empty, show the user a warning and prompt them to re-link each connection inside the workflow editor.

---

### Step 6 — Delete a Recipe

**`DELETE /recipes/{id}`**

The original workflow is not affected.

**Response**
```json
{ "deleted": true, "id": 15 }
```

**Errors**
| HTTP | Code | Reason |
|------|------|--------|
| 404 | `not_found` | Recipe does not exist |

---

## Full User Flows

### Flow A — Create a folder and move a workflow into it

```
1. User clicks "New Folder"
   → POST /folders  { "title": "Marketing" }
   ← { "id": 4, "title": "Marketing", "workflow_count": 0, ... }

2. User drags workflow #12 into folder #4
   → POST /folders/4/workflows/12
   ← { "id": 12, "folder_id": 4, "title": "My Workflow", ... }

3. User opens folder to see its workflows
   → GET /folders/4/workflows?page=1&per_page=20
   ← { "data": [...], "pagination": { ... } }
```

---

### Flow B — Convert a workflow into a recipe

```
1. User is on the workflow list (or inside the workflow editor)
   They click "Save as Recipe" on workflow #12

2. Frontend opens a modal:
   ┌─────────────────────────────────┐
   │  Save as Recipe                 │
   │  ─────────────────────────────  │
   │  Recipe Title *                 │
   │  [ Welcome Email Series       ] │
   │                                 │
   │  Description                    │
   │  [ A 3-step onboarding flow.  ] │
   │                                 │
   │  Thumbnail (optional)           │
   │  [ Pick image ]                 │
   │                                 │
   │          [Cancel] [Save Recipe] │
   └─────────────────────────────────┘

3. User clicks "Save Recipe"
   → POST /wp-json/zaplane/v1/workflows/12/to-recipe
      {
        "title": "Welcome Email Series",
        "description": "A 3-step onboarding flow.",
        "thumbnail_id": 42
      }

4. API response (201):
   ← {
       "id": 15,
       "title": "Welcome Email Series",
       "description": "A 3-step onboarding flow.",
       "thumbnail_id": 42,
       "thumbnail_url": "https://...",
       "created_by": 1,
       "created_at": "2024-06-10 09:00:00"
     }

5. Close modal, show success toast: "Recipe saved successfully"
6. The original workflow #12 is unchanged and still works normally
7. The new recipe #15 appears in GET /recipes
```

---

### Flow C — Use a recipe to create a new workflow

```
1. User is on the Recipes page, clicks "Use This Recipe" on recipe #15
   → Show small modal with optional title override

2. User confirms
   → POST /recipes/15/to-workflow  { "title": "Welcome Flow (copy)" }
   ← { "workflow_id": 27, "status": "draft", "connections_to_relink": [...] }

3. If connections_to_relink is non-empty:
   → Show banner: "This workflow has 2 connection(s) that need to be re-linked."

4. Navigate to workflow editor for workflow_id: 27
```

---

### Flow D — Delete a folder (safe delete)

```
1. User clicks delete on folder #4
2. Check workflow_count from the folder object
   - If workflow_count > 0:
     Show warning: "This folder contains 5 workflows. Move them before deleting."
   - If workflow_count === 0:
     Show confirmation dialog

3. User confirms
   → DELETE /folders/4
   ← { "deleted": true, "id": 4 }
```

---

## Error Response Shape

All errors follow the standard WordPress REST format:

```json
{
  "code": "folder_not_empty",
  "message": "Cannot delete a folder that still has workflows assigned. Move or remove the workflows first.",
  "data": { "status": 409 }
}
```

Always read `response.data.message` and display it directly to the user — all messages are safe to show as-is.
