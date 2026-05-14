# Recipes & Recipe Folders — Frontend Implementation Guide

## Overview

The Recipe system lets an admin convert any workflow into a reusable **recipe** (a saved blueprint) and later convert a recipe back into a brand-new workflow. Recipes are organized in **folders** (nested, unlimited depth).

Key rules:
- Recipes can only be created by converting a workflow — there is no "create recipe from scratch" button.
- A recipe cannot run on its own — it must be converted to a workflow first.
- Every conversion creates a **new** entity; the original (workflow or recipe) is never deleted or modified.
- Folders hold **recipes only** — workflows are not organized by folders.

---

## Base URL

```
/wp-json/zaplane/v1
```

All endpoints require the user to be logged in as an administrator (`manage_options` capability).

---

## Recipe Folders

### Data Shape

```json
{
  "id": 3,
  "title": "E-commerce",
  "parent_id": null,
  "created_by": 1,
  "created_at": "2024-06-01 10:00:00",
  "updated_at": "2024-06-01 10:00:00",
  "children": [
    {
      "id": 7,
      "title": "Order Flows",
      "parent_id": 3,
      "created_by": 1,
      "created_at": "2024-06-01 10:05:00",
      "updated_at": "2024-06-01 10:05:00",
      "children": []
    }
  ]
}
```

The `GET /recipe-folders` endpoint always returns the **full nested tree** — there is no pagination on folders.

---

### GET `/recipe-folders`

Returns the complete folder tree.

**Response**
```json
{
  "folders": [
    {
      "id": 1,
      "title": "Marketing",
      "parent_id": null,
      "children": [...]
    }
  ]
}
```

---

### POST `/recipe-folders`

Create a new folder.

**Request body**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | ✅ | Folder name |
| `parent_id` | integer\|null | ❌ | Parent folder ID; omit or send `null` for root |

**Request**
```json
{
  "title": "Email Campaigns",
  "parent_id": 1
}
```

**Response** — the created folder object (without `children`, since it is brand-new).

**Errors**
| Code | Reason |
|------|--------|
| `400 missing_title` | `title` is empty |
| `404 parent_not_found` | `parent_id` does not exist |

---

### PUT `/recipe-folders/{id}`

Rename a folder and/or move it to a different parent.

**Request body** (all fields optional)
| Field | Type | Description |
|-------|------|-------------|
| `title` | string | New folder name |
| `parent_id` | integer\|null | New parent; send `null` to move to root |

**Request**
```json
{
  "title": "Email Automations",
  "parent_id": null
}
```

**Errors**
| Code | Reason |
|------|--------|
| `404 not_found` | Folder does not exist |
| `404 parent_not_found` | New `parent_id` does not exist |
| `400 circular_reference` | Tried to move a folder into itself or one of its own sub-folders |

---

### DELETE `/recipe-folders/{id}`

Delete a folder. **The folder must be empty** — no recipes and no sub-folders.

**Response**
```json
{ "deleted": true, "id": 5 }
```

**Errors**
| Code | Reason |
|------|--------|
| `404 not_found` | Folder does not exist |
| `409 folder_not_empty` | Folder still contains recipes or sub-folders |

> **UI tip:** Disable the delete button when the folder has contents, or show a confirmation that asks the user to move/delete the contents first.

---

## Recipes

### Data Shape

The recipe object returned by all endpoints **never includes the blueprint JSON** (it would be too large). The blueprint is only used internally during conversions.

```json
{
  "id": 12,
  "folder_id": 3,
  "title": "Welcome Email Series",
  "description": "Sends a 3-step welcome sequence to new subscribers.",
  "thumbnail_id": 42,
  "thumbnail_url": "https://example.com/wp-content/uploads/2024/thumb.png",
  "created_by": 1,
  "created_at": "2024-06-01 12:00:00",
  "updated_at": "2024-06-01 12:00:00"
}
```

---

### GET `/recipes`

List recipes, optionally filtered by folder.

**Query parameters**
| Param | Type | Description |
|-------|------|-------------|
| `folder_id` | integer | Show only recipes in this folder. Omit to show all recipes across all folders. |

**Examples**
```
GET /wp-json/zaplane/v1/recipes               → all recipes
GET /wp-json/zaplane/v1/recipes?folder_id=3   → only recipes in folder 3
```

**Response**
```json
{
  "recipes": [
    { "id": 12, "title": "Welcome Email Series", ... },
    { "id": 13, "title": "Abandoned Cart Flow", ... }
  ]
}
```

---

### GET `/recipes/{id}`

Get a single recipe (metadata only, no blueprint).

**Response** — recipe object (see shape above).

**Errors**
| Code | Reason |
|------|--------|
| `404 not_found` | Recipe does not exist |

---

### PUT `/recipes/{id}`

Update recipe metadata. The blueprint is **not** changeable after creation.

**Request body** (all fields optional)
| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Recipe name |
| `description` | string | Short description |
| `thumbnail_id` | integer\|null | WP attachment ID for thumbnail; `null` to remove |
| `folder_id` | integer\|null | Move to a different folder; `null` to unfile |

**Request**
```json
{
  "title": "Updated Name",
  "folder_id": 7
}
```

**Errors**
| Code | Reason |
|------|--------|
| `404 not_found` | Recipe does not exist |
| `404 folder_not_found` | Target `folder_id` does not exist |

---

### DELETE `/recipes/{id}`

Permanently delete a recipe. The workflow it was created from is not affected.

**Response**
```json
{ "deleted": true, "id": 12 }
```

---

## Conversions

### POST `/workflows/{id}/to-recipe` — Workflow → Recipe

Converts a workflow into a recipe. The workflow's **active version** is serialized and stored as the recipe blueprint. **The original workflow is not modified.**

**Request body**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | ✅ | Name for the new recipe |
| `description` | string | ❌ | Short description |
| `thumbnail_id` | integer | ❌ | WP attachment ID |
| `folder_id` | integer | ❌ | Place the recipe directly into a folder |

**Request**
```json
{
  "title": "Welcome Email Series",
  "description": "Sends a 3-step welcome sequence.",
  "thumbnail_id": 42,
  "folder_id": 3
}
```

**Response** — the newly created recipe object.
```json
{
  "id": 15,
  "folder_id": 3,
  "title": "Welcome Email Series",
  "description": "Sends a 3-step welcome sequence.",
  "thumbnail_id": 42,
  "thumbnail_url": "https://example.com/wp-content/uploads/2024/thumb.png",
  "created_by": 1,
  "created_at": "2024-06-10 09:00:00",
  "updated_at": "2024-06-10 09:00:00"
}
```

**Errors**
| Code | Reason |
|------|--------|
| `404 workflow_not_found` | Workflow does not exist |
| `400 missing_title` | `title` is empty |
| `404 folder_not_found` | `folder_id` does not exist |

---

### POST `/recipes/{id}/to-workflow` — Recipe → Workflow

Converts a recipe back into a brand-new workflow. The new workflow is created as a **draft** so the user can review and activate it. **The recipe is not modified.**

**Request body**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | ❌ | Override the workflow title; if omitted the recipe title is used |

**Request**
```json
{
  "title": "My Welcome Series (copy)"
}
```

**Response**
```json
{
  "workflow_id": 27,
  "title": "My Welcome Series (copy)",
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

> **`connections_to_relink`** — The imported workflow has all its connection IDs stripped (they point to this site's connection records, which may not exist on the target installation). Show the user this list and prompt them to re-link each one in the workflow editor.

**Errors**
| Code | Reason |
|------|--------|
| `404 not_found` | Recipe does not exist |
| `422 empty_blueprint` | Recipe blueprint is empty or corrupted |
| `422 import_failed` | Blueprint structure is invalid |

---

## Suggested UI Flows

### "Save as Recipe" button (on Workflow list / detail)

1. User clicks **"Save as Recipe"** on a workflow row.
2. Open a modal:
   - Text input: **Recipe name** (pre-filled with workflow title)
   - Textarea: **Description** (optional)
   - Media uploader: **Thumbnail** (optional)
   - Folder picker tree (built from `GET /recipe-folders`)
3. On submit → `POST /workflows/{id}/to-recipe`
4. Show success toast: *"Recipe created successfully."*

### Folder picker tree

- Fetch `GET /recipe-folders` once when the modal opens.
- Render the `children` array recursively as a collapsible tree.
- Allow selecting a single folder or no folder (root).
- Include a **"+ New Folder"** inline action that calls `POST /recipe-folders` and refreshes the tree.

### Recipe Library page

- Top-level layout: folder tree on the left, recipe grid on the right.
- Clicking a folder calls `GET /recipes?folder_id={id}`.
- Each recipe card has:
  - Thumbnail (or default icon)
  - Title + description
  - **"Convert to Workflow"** button → opens a small modal to optionally rename, then calls `POST /recipes/{id}/to-workflow`
  - **"Move"** button → re-opens the folder picker, then calls `PUT /recipes/{id}` with new `folder_id`
  - **"Delete"** button → calls `DELETE /recipes/{id}`

### "Convert to Workflow" flow

1. User clicks **"Convert to Workflow"** on a recipe card.
2. Small modal: optional title override (pre-filled with recipe title).
3. On confirm → `POST /recipes/{id}/to-workflow`
4. If `connections_to_relink` is non-empty, show a warning banner:
   > *"This workflow uses [N] connection(s) that need to be re-linked. Open the workflow editor to re-connect them."*
5. Navigate to the workflow editor for the new `workflow_id`.

---

## Error Handling

All error responses follow the standard WordPress REST API format:

```json
{
  "code": "folder_not_empty",
  "message": "Cannot delete a folder that still contains recipes or sub-folders.",
  "data": { "status": 409 }
}
```

Check `response.ok` (or HTTP status) before reading the body. Display `message` to the user directly — all messages are safe to show.
