# Connections API

## Base URL
```
/zaplane/v1/connections
```

## Authentication
All endpoints require WordPress login. Include `X-WP-Nonce` header.

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/connections` | List all connections |
| GET | `/connections?app=slack` | List Slack connections only |
| POST | `/connections` | Create connection (API Key type) |
| GET | `/connections/{id}` | Get single connection |
| PUT | `/connections/{id}` | Update connection |
| DELETE | `/connections/{id}` | Delete connection |
| POST | `/connections/{id}/test` | Test connection |
| GET | `/connections/auth-fields/{app}` | Get auth type & fields |
| POST | `/connections/oauth/init` | Start OAuth flow |

---

## Slack Integration

### Step 1: Check Auth Type
```javascript
GET /connections/auth-fields/slack
```

**Response:**
```json
{
  "app": "slack",
  "auth_type": "oauth2",
  "requires_connection": true,
  "auth_fields": []
}
```

Since `auth_type` is `oauth2`, use OAuth flow (not manual credentials).

---

### Step 2: Start OAuth Flow
```javascript
POST /connections/oauth/init
Content-Type: application/json

{
  "app": "slack",
  "name": "My Slack Workspace"
}
```

**Response:**
```json
{
  "auth_url": "https://slack.com/oauth/v2/authorize?client_id=xxx&scope=chat:write,channels:read,users:read,im:write&redirect_uri=https://yoursite.com/wp-json/zaplane/v1/connections/oauth/callback&state=abc123",
  "state": "abc123"
}
```

---

### Step 3: Open Popup & Handle Callback

```javascript
// Start OAuth
async function connectSlack(connectionName) {
  // 1. Get auth URL from backend
  const response = await fetch('/wp-json/zaplane/v1/connections/oauth/init', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
      app: 'slack',
      name: connectionName
    })
  });

  const { auth_url } = await response.json();

  // 2. Open popup window
  const popup = window.open(auth_url, 'slack_oauth', 'width=600,height=700');

  // 3. Listen for callback message
  return new Promise((resolve, reject) => {
    const handler = (event) => {
      if (event.data?.type === 'zaplane_oauth_callback') {
        window.removeEventListener('message', handler);
        const { success, message, connection_id } = event.data.data;

        if (success) {
          resolve({ connection_id, message });
        } else {
          reject(new Error(message));
        }
      }
    };

    window.addEventListener('message', handler);

    // Timeout after 5 minutes
    setTimeout(() => {
      window.removeEventListener('message', handler);
      reject(new Error('OAuth timeout'));
    }, 300000);
  });
}

// Usage
try {
  const { connection_id } = await connectSlack('Work Slack');
  console.log('Connected! ID:', connection_id);
  // Refresh connections list
} catch (error) {
  console.error('OAuth failed:', error.message);
}
```

---

### Step 4: List User's Slack Connections
```javascript
GET /connections?app=slack
```

**Response:**
```json
{
  "connections": [
    {
      "id": 1,
      "app": "slack",
      "name": "Work Slack",
      "auth_type": "oauth2",
      "status": "active",
      "last_test_status": "success",
      "last_tested_at": "2026-01-10 10:00:00",
      "created_at": "2026-01-05 08:00:00"
    }
  ]
}
```

---

### Step 5: Test Connection
```javascript
POST /connections/1/test
```

**Response:**
```json
{
  "success": true,
  "message": "Connected to workspace: Acme Corp",
  "details": {
    "team": "Acme Corp",
    "team_id": "T12345",
    "user": "bot",
    "user_id": "U12345"
  }
}
```

---

### Step 6: Delete Connection
```javascript
DELETE /connections/1
```

**Response:**
```json
{
  "deleted": true,
  "id": 1
}
```

---

## Using Connection in Workflow Node

When user selects a Slack action in workflow builder, show connection dropdown. Save `connection_id` in node data:

```json
{
  "id": "node_abc123",
  "type": "action",
  "data": {
    "app": "slack",
    "connection_id": 1,
    "action": "send_message"
  },
  "config": {
    "action": "send_message",
    "data": {
      "channel": "#general",
      "text": "Hello {{trigger.user_name}}!"
    }
  }
}
```

---

## Slack Actions Available

| Action | Fields |
|--------|--------|
| `send_message` | `channel` (required), `text` (required) |
| `send_dm` | `user_id` (required), `text` (required) |

### Get Action Schema
```javascript
GET /integrations/slack/actions/send_message/schema
```

**Response:**
```json
{
  "channel": {
    "type": "text",
    "label": "Channel",
    "placeholder": "#general or channel ID",
    "required": true
  },
  "text": {
    "type": "textarea",
    "label": "Message",
    "placeholder": "Enter your message...",
    "required": true
  }
}
```

---

## UI Flow Summary

1. User clicks "Add Slack Connection"
2. Frontend calls `POST /connections/oauth/init` with name
3. Open popup with `auth_url`
4. User authorizes in Slack
5. Popup closes, frontend receives `connection_id` via postMessage
6. Show connection in list with Test/Delete buttons
7. In workflow builder, show connection dropdown for Slack nodes
8. Save selected `connection_id` in node data

---

## Error Handling

```json
{
  "code": "rest_forbidden",
  "message": "You must be logged in.",
  "data": { "status": 401 }
}
```

| Code | Meaning |
|------|---------|
| `rest_forbidden` | Not logged in or no permission |
| `invalid_app` | Integration not found |
| `not_found` | Connection not found |
| `oauth_init_failed` | OAuth setup failed (check client_id/secret) |
| `invalid_state` | OAuth state expired (>10 min) |
