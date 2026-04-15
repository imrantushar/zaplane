# Zaplane API Reference - New & Updated Endpoints

Base URL: `/wp-json/zaplane/v1`

All REST API endpoints require `manage_options` capability (WordPress admin).
All AJAX endpoints require nonce verification via `security` parameter.

---

## Table of Contents

1. [Pagination Standard](#pagination-standard)
2. [Dashboard APIs](#1-dashboard-apis)
3. [Workflows (List - Paginated)](#2-list-workflows-updated)
4. [Workflow Versions (Paginated)](#3-workflow-versions-updated)
5. [Workflow Runs (Paginated + Node Count)](#4-workflow-runs-updated)
6. [Connections (Paginated)](#5-connections-updated)
7. [AJAX - Update Workflow Name](#6-ajax-update-workflow-name-new)
8. [AJAX - Update Workflow Layout](#7-ajax-update-workflow-layout-new)
9. [Workflow Model Changes](#8-workflow-model-changes)

---

## Pagination Standard

All paginated list endpoints now follow the same pattern:

### Query Parameters

| Parameter  | Type   | Default | Max | Description          |
|------------|--------|---------|-----|----------------------|
| `page`     | number | `1`     | -   | Current page number  |
| `per_page` | number | `20`    | 100 | Items per page       |

### Response Wrapper

Every paginated endpoint returns this structure:

```json
{
  "data": [ ... ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 150,
    "total_pages": 8
  }
}
```

### Frontend Usage

```ts
// TypeScript interface
interface PaginatedResponse<T> {
  data: T[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

// Example fetch
const res = await apiFetch({
  path: '/zaplane/v1/workflows?page=2&per_page=10',
});
// res.data = [...workflows]
// res.pagination = { page: 2, per_page: 10, total: 47, total_pages: 5 }
```

---

## 1. Dashboard APIs

### `GET /zaplane/v1/dashboard/top-workflows`

Returns the top 10 workflows sorted by total run count (descending).

**Parameters:** None

**Response:**

```json
{
  "top_workflows": [
    {
      "workflow_id": 1,
      "title": "My Automation Flow",
      "status": "active",
      "total_runs": 42,
      "success_runs": 38,
      "failed_runs": 4
    },
    {
      "workflow_id": 5,
      "title": "Email Notification",
      "status": "active",
      "total_runs": 27,
      "success_runs": 25,
      "failed_runs": 2
    }
  ]
}
```

**Field Details:**

| Field          | Type   | Description                                  |
|----------------|--------|----------------------------------------------|
| `workflow_id`  | number | Workflow ID                                  |
| `title`        | string | Workflow display title                       |
| `status`       | string | `"active"` \| `"paused"` \| `"draft"`        |
| `total_runs`   | number | Total number of runs across all statuses     |
| `success_runs` | number | Runs with status `"completed"`               |
| `failed_runs`  | number | Runs with status `"failed"`                  |

**Notes:**
- Counts are based on the active version's runs only
- Workflows with no runs are excluded
- Maximum 10 results returned

---

## 2. List Workflows (Updated)

### `GET /zaplane/v1/workflows`

**Previously:** Returned a flat array of workflows.
**Now:** Returns paginated response with run counts per workflow.

**Parameters:**

| Parameter  | Type   | Default | Description     |
|------------|--------|---------|-----------------|
| `page`     | number | `1`     | Page number     |
| `per_page` | number | `20`    | Items per page  |

**Response:**

```json
{
  "data": [
    {
      "id": 12,
      "user_id": 1,
      "title": "Post Published Notifier",
      "name": "post-published-notifier",
      "status": "active",
      "layout": "horizontal",
      "created_at": "2025-01-15 10:30:00",
      "updated_at": "2025-02-20 14:22:00",
      "success_runs": 38,
      "failed_runs": 4
    },
    {
      "id": 11,
      "user_id": 1,
      "title": "User Registration Flow",
      "name": "user-registration-flow",
      "status": "draft",
      "layout": "vertical",
      "created_at": "2025-01-10 08:00:00",
      "updated_at": "2025-01-10 08:00:00",
      "success_runs": 0,
      "failed_runs": 0
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 47,
    "total_pages": 3
  }
}
```

**Workflow Object Fields:**

| Field           | Type   | Description                                          |
|-----------------|--------|------------------------------------------------------|
| `id`            | number | Workflow ID                                          |
| `user_id`       | number | Owner user ID                                        |
| `title`         | string | Display title                                        |
| `name`          | string | URL-friendly slug                                    |
| `status`        | string | `"active"` \| `"paused"` \| `"draft"`                |
| `layout`        | string | `"horizontal"` \| `"vertical"` (NEW)                 |
| `created_at`    | string | MySQL datetime                                       |
| `updated_at`    | string | MySQL datetime                                       |
| `success_runs`  | number | Completed runs for active version (NEW)              |
| `failed_runs`   | number | Failed runs for active version (NEW)                 |

**Breaking Change:** Response is no longer a flat array. It's now `{ data: [...], pagination: {...} }`.

---

## 3. Workflow Versions (Updated)

### `GET /zaplane/v1/workflows/{id}/versions`

**Previously:** Returned a flat array.
**Now:** Paginated.

**Parameters:**

| Parameter  | Type   | Default | Description     |
|------------|--------|---------|-----------------|
| `page`     | number | `1`     | Page number     |
| `per_page` | number | `20`    | Items per page  |

**Response:**

```json
{
  "data": [
    {
      "id": 45,
      "graph_hash": "a1b2c3d4e5f6...",
      "is_active": 1,
      "created_at": "2025-02-20 14:22:00"
    },
    {
      "id": 44,
      "graph_hash": "f6e5d4c3b2a1...",
      "is_active": 0,
      "created_at": "2025-02-18 09:15:00"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 12,
    "total_pages": 1
  }
}
```

**Version Object Fields:**

| Field        | Type   | Description                        |
|--------------|--------|------------------------------------|
| `id`         | number | Version ID                         |
| `graph_hash` | string | SHA-256 hash of the graph JSON     |
| `is_active`  | number | `1` if active, `0` if inactive     |
| `created_at` | string | MySQL datetime                     |

**Breaking Change:** Response is no longer a flat array.

---

## 4. Workflow Runs (Updated)

### `GET /zaplane/v1/workflows/{id}/runs`

**Previously:** Returned a flat array, max 100 items, no node count.
**Now:** Paginated, includes `node_runs_count` per run.

**Parameters:**

| Parameter  | Type   | Default | Description     |
|------------|--------|---------|-----------------|
| `page`     | number | `1`     | Page number     |
| `per_page` | number | `20`    | Items per page  |

**Response:**

```json
{
  "data": [
    {
      "id": 301,
      "status": "completed",
      "started_at": "2025-02-24 10:00:05",
      "finished_at": "2025-02-24 10:00:08",
      "last_error": null,
      "node_runs_count": 4
    },
    {
      "id": 300,
      "status": "failed",
      "started_at": "2025-02-24 09:30:00",
      "finished_at": "2025-02-24 09:30:03",
      "last_error": "API rate limit exceeded",
      "node_runs_count": 2
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 156,
    "total_pages": 8
  }
}
```

**Run Object Fields:**

| Field              | Type        | Description                                 |
|--------------------|-------------|---------------------------------------------|
| `id`               | number      | Run ID                                      |
| `status`           | string      | `"running"` \| `"completed"` \| `"failed"`  |
| `started_at`       | string      | MySQL datetime                              |
| `finished_at`      | string/null | MySQL datetime, null if still running        |
| `last_error`       | string/null | Error message if failed                      |
| `node_runs_count`  | number      | Number of node executions in this run (NEW)  |

**Breaking Change:** Response is no longer a flat array.

**When workflow not found or has no active version:**
```json
{
  "data": [],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 0,
    "total_pages": 0
  }
}
```

---

## 5. Connections (Updated)

### `GET /zaplane/v1/connections`

**Previously:** Returned `{ connections: [...] }`.
**Now:** Returns `{ data: [...], pagination: {...} }`.

**Parameters:**

| Parameter  | Type   | Default | Description                          |
|------------|--------|---------|--------------------------------------|
| `app`      | string | -       | Optional. Filter by integration app  |
| `page`     | number | `1`     | Page number                          |
| `per_page` | number | `20`    | Items per page                       |

**Response:**

```json
{
  "data": [
    {
      "id": 5,
      "user_id": 1,
      "app": "google_sheets",
      "name": "My Google Account",
      "auth_type": "oauth2",
      "status": "active",
      "oauth_expires_at": "2025-02-24 12:00:00",
      "last_used_at": "2025-02-24 10:30:00",
      "last_tested_at": "2025-02-20 15:00:00",
      "last_test_status": "success",
      "created_at": "2025-01-05 09:00:00",
      "updated_at": "2025-02-24 10:30:00"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 8,
    "total_pages": 1
  }
}
```

**Connection Object Fields:**

| Field                | Type        | Description                                    |
|----------------------|-------------|------------------------------------------------|
| `id`                 | number      | Connection ID                                  |
| `user_id`            | number      | Owner user ID                                  |
| `app`                | string      | Integration slug (e.g. `"google_sheets"`)      |
| `name`               | string      | User-given display name                        |
| `auth_type`          | string      | `"api_key"` \| `"oauth2"` \| `"basic"`         |
| `status`             | string      | `"active"` \| `"inactive"`                     |
| `oauth_expires_at`   | string/null | OAuth token expiry datetime                    |
| `last_used_at`       | string/null | Last time credentials were used                |
| `last_tested_at`     | string/null | Last test datetime                             |
| `last_test_status`   | string/null | `"success"` \| `"failed"` \| null              |
| `created_at`         | string      | MySQL datetime                                 |
| `updated_at`         | string      | MySQL datetime                                 |

**Breaking Change:** Response key changed from `connections` to `data`, added `pagination`.

---

## 6. AJAX: Update Workflow Name (New)

Renames a workflow's title and auto-generates the slug.

### Request

```
POST /wp-admin/admin-ajax.php
```

| Parameter  | Type   | Required | Description                |
|------------|--------|----------|----------------------------|
| `action`   | string | Yes      | `"zaplane/update_workflow_name"` |
| `security` | string | Yes      | Nonce (`zaplane_nonce`)     |
| `id`       | number | Yes      | Workflow ID                |
| `title`    | string | Yes      | New workflow title         |

### Example Request

```js
jQuery.ajax({
  url: ajaxurl,
  method: 'POST',
  data: {
    action: 'zaplane/update_workflow_name',
    security: zaplane.nonce,
    id: 12,
    title: 'My New Workflow Name',
  },
});

// Or with apiFetch / fetch:
const formData = new FormData();
formData.append('action', 'zaplane/update_workflow_name');
formData.append('security', zaplane.nonce);
formData.append('id', '12');
formData.append('title', 'My New Workflow Name');

fetch(ajaxurl, { method: 'POST', body: formData });
```

### Success Response

```json
{
  "success": true,
  "data": {
    "id": 12,
    "title": "My New Workflow Name",
    "name": "my-new-workflow-name",
    "message": "Name updated successfully"
  }
}
```

### Error Response

```json
{
  "success": false,
  "data": "Workflow not found"
}
```

**Response Fields:**

| Field     | Type   | Description                                     |
|-----------|--------|-------------------------------------------------|
| `id`      | number | Workflow ID                                     |
| `title`   | string | Updated display title                           |
| `name`    | string | Auto-generated slug from title (`sanitize_title`) |
| `message` | string | Success message                                  |

---

## 7. AJAX: Update Workflow Layout (New)

Updates the layout direction for a workflow's canvas.

### Request

```
POST /wp-admin/admin-ajax.php
```

| Parameter  | Type   | Required | Description                    |
|------------|--------|----------|--------------------------------|
| `action`   | string | Yes      | `"zaplane/update_workflow_layout"` |
| `security` | string | Yes      | Nonce (`zaplane_nonce`)         |
| `id`       | number | Yes      | Workflow ID                    |
| `layout`   | string | Yes      | `"horizontal"` or `"vertical"` |

### Example Request

```js
jQuery.ajax({
  url: ajaxurl,
  method: 'POST',
  data: {
    action: 'zaplane/update_workflow_layout',
    security: zaplane.nonce,
    id: 12,
    layout: 'vertical',
  },
});
```

### Success Response

```json
{
  "success": true,
  "data": {
    "id": 12,
    "layout": "vertical",
    "message": "Layout updated successfully"
  }
}
```

### Error Responses

**Invalid layout value:**
```json
{
  "success": false,
  "data": "Layout must be horizontal or vertical"
}
```

**Workflow not found:**
```json
{
  "success": false,
  "data": "Workflow not found"
}
```

**Response Fields:**

| Field     | Type   | Description                              |
|-----------|--------|------------------------------------------|
| `id`      | number | Workflow ID                              |
| `layout`  | string | Updated layout: `"horizontal"` \| `"vertical"` |
| `message` | string | Success message                           |

---

## 8. Workflow Model Changes

### New `layout` Column

The `workflows` table now has a `layout` column.

| Column   | Type        | Default        | Values                          |
|----------|-------------|----------------|---------------------------------|
| `layout` | varchar(20) | `"horizontal"` | `"horizontal"` \| `"vertical"` |

This field is included in all workflow responses (`GET /workflows`, `GET /workflows/{id}`, etc.).

---

## Migration Notes for Frontend

### Breaking Changes Summary

| Endpoint                              | Before                    | After                           |
|---------------------------------------|---------------------------|---------------------------------|
| `GET /workflows`                      | `[...workflows]`          | `{ data: [...], pagination }` |
| `GET /workflows/{id}/versions`        | `[...versions]`           | `{ data: [...], pagination }` |
| `GET /workflows/{id}/runs`            | `[...runs]`               | `{ data: [...], pagination }` |
| `GET /connections`                    | `{ connections: [...] }`  | `{ data: [...], pagination }` |

### New Fields Added

| Endpoint              | New Fields                                |
|-----------------------|-------------------------------------------|
| `GET /workflows`      | `success_runs`, `failed_runs`, `layout`   |
| `GET /workflows/{id}` | `layout`                                  |
| `GET /workflows/{id}/runs` | `node_runs_count` per run            |

### New Endpoints

| Endpoint                               | Method | Description                     |
|----------------------------------------|--------|---------------------------------|
| `/zaplane/v1/dashboard/top-workflows`  | GET    | Top 10 workflows by run count   |
| AJAX `zaplane/update_workflow_name`    | POST   | Rename workflow                 |
| AJAX `zaplane/update_workflow_layout`  | POST   | Change layout direction         |

### Quick Migration Guide

```ts
// BEFORE - workflows list
const workflows = await apiFetch({ path: '/zaplane/v1/workflows' });
workflows.forEach(w => console.log(w.title));

// AFTER - workflows list
const res = await apiFetch({ path: '/zaplane/v1/workflows?page=1&per_page=20' });
res.data.forEach(w => console.log(w.title, w.success_runs, w.failed_runs));
console.log(`Page ${res.pagination.page} of ${res.pagination.total_pages}`);
```

```ts
// BEFORE - connections
const res = await apiFetch({ path: '/zaplane/v1/connections' });
res.connections.forEach(c => console.log(c.name));

// AFTER - connections
const res = await apiFetch({ path: '/zaplane/v1/connections?page=1&per_page=20' });
res.data.forEach(c => console.log(c.name));
```

```ts
// BEFORE - runs
const runs = await apiFetch({ path: `/zaplane/v1/workflows/${id}/runs` });
runs.forEach(r => console.log(r.status));

// AFTER - runs
const res = await apiFetch({ path: `/zaplane/v1/workflows/${id}/runs?page=1&per_page=20` });
res.data.forEach(r => console.log(r.status, `${r.node_runs_count} nodes`));
```
