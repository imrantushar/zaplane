# Zaplane Connections API Documentation

This documentation covers the REST API endpoints for managing integration connections. The connection system supports both **OAuth 2.0** and **Token-based** (API Key) authentication methods.

## Base URL

```
/wp-json/zaplane/v1/connections
```

## Authentication

All endpoints require the user to be logged in (WordPress authentication via cookies or application passwords).

---

## Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/connections` | List all user connections |
| POST | `/connections` | Create a new connection (token-based) |
| GET | `/connections/{id}` | Get a single connection |
| PUT | `/connections/{id}` | Update a connection |
| DELETE | `/connections/{id}` | Delete a connection |
| POST | `/connections/{id}/test` | Test a connection |
| GET | `/connections/auth-fields/{app}` | Get auth configuration for an integration |
| POST | `/connections/oauth/init` | Initialize OAuth flow |
| GET | `/connections/oauth/callback` | OAuth callback (handled automatically) |

---

## Integration Auth Configuration

Before creating a connection, fetch the integration's authentication configuration to determine what fields to display.

### Get Auth Fields

```
GET /connections/auth-fields/{app}?auth_type={type}
```

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `app` | string | Yes | Integration slug (e.g., `slack`) |
| `auth_type` | string | No | Specific auth type to get fields for (`oauth2` or `api_key`) |

**Response (Integration with both auth types - e.g., Slack):**

```json
{
  "app": "slack",
  "auth_type": "both",
  "requires_connection": true,
  "available_auth_types": {
    "oauth2": {
      "label": "OAuth 2.0",
      "description": "Connect securely using Slack OAuth. Recommended for most users."
    },
    "api_key": {
      "label": "Bot Token",
      "description": "Use a Bot User OAuth Token directly. Requires creating a Slack App."
    }
  },
  "auth_fields": {
    "oauth2": {
      "client_id": {
        "type": "text",
        "label": "Client ID",
        "placeholder": "Your Slack App Client ID",
        "required": true,
        "help": "Go to api.slack.com/apps → Your App → Basic Information → App Credentials"
      },
      "client_secret": {
        "type": "password",
        "label": "Client Secret",
        "placeholder": "Your Slack App Client Secret",
        "required": true,
        "help": "Found in the same location as Client ID"
      }
    },
    "api_key": {
      "bot_token": {
        "type": "password",
        "label": "Bot Token",
        "placeholder": "xoxb-xxxx-xxxx-xxxx",
        "required": true,
        "help": "Go to api.slack.com/apps → Your App → OAuth & Permissions → Bot User OAuth Token"
      }
    }
  }
}
```

**Response with specific auth_type (`?auth_type=api_key`):**

```json
{
  "app": "slack",
  "auth_type": "both",
  "requires_connection": true,
  "available_auth_types": {
    "oauth2": {
      "label": "OAuth 2.0",
      "description": "Connect securely using Slack OAuth. Recommended for most users."
    },
    "api_key": {
      "label": "Bot Token",
      "description": "Use a Bot User OAuth Token directly. Requires creating a Slack App."
    }
  },
  "auth_fields": {
    "bot_token": {
      "type": "password",
      "label": "Bot Token",
      "placeholder": "xoxb-xxxx-xxxx-xxxx",
      "required": true,
      "help": "Go to api.slack.com/apps → Your App → OAuth & Permissions → Bot User OAuth Token"
    }
  }
}
```

---

## Creating Connections

### Method 1: Token-Based (API Key) Connection

Use this method when the user provides a bot token directly.

```
POST /connections
```

**Request Body:**

```json
{
  "app": "slack",
  "name": "My Slack Workspace",
  "auth_type": "api_key",
  "credentials": {
    "bot_token": "xoxb-1234567890-1234567890123-abcdefghijklmnop"
  }
}
```

**Response:**

```json
{
  "id": 1,
  "app": "slack",
  "name": "My Slack Workspace",
  "status": "active",
  "test_result": {
    "success": true,
    "message": "Connected to workspace: Acme Corp",
    "details": {
      "team": "Acme Corp",
      "user": "zaplane-bot",
      "user_id": "U12345678",
      "team_id": "T12345678",
      "url": "https://acmecorp.slack.com/"
    }
  }
}
```

### Method 2: OAuth 2.0 Connection

OAuth flow requires two steps:

#### Step 1: Initialize OAuth Flow

```
POST /connections/oauth/init
```

**Request Body:**

```json
{
  "app": "slack",
  "name": "My Slack Workspace",
  "credentials": {
    "client_id": "1234567890.1234567890",
    "client_secret": "abcdefghijklmnopqrstuvwxyz123456"
  }
}
```

**Response:**

```json
{
  "auth_url": "https://slack.com/oauth/v2/authorize?client_id=1234567890.1234567890&redirect_uri=https://example.com/wp-json/zaplane/v1/connections/oauth/callback&state=abc123...",
  "state": "abc123..."
}
```

#### Step 2: Open OAuth Window

Open the `auth_url` in a popup window:

```javascript
const popup = window.open(
  response.auth_url,
  'zaplane_oauth',
  'width=600,height=700,scrollbars=yes'
);
```

#### Step 3: Listen for Callback

The OAuth callback will post a message to the opener window:

```javascript
window.addEventListener('message', (event) => {
  if (event.data?.type === 'zaplane_oauth_callback') {
    const { success, message, connection_id } = event.data.data;

    if (success) {
      console.log('Connection created:', connection_id);
      // Refresh connection list
    } else {
      console.error('OAuth failed:', message);
    }
  }
});
```

**Callback Message Data:**

```json
{
  "type": "zaplane_oauth_callback",
  "data": {
    "success": true,
    "message": "Connection created successfully",
    "connection_id": 1
  }
}
```

---

## Managing Connections

### List Connections

```
GET /connections?app={app}
```

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `app` | string | No | Filter by integration slug |

**Response:**

```json
{
  "connections": [
    {
      "id": 1,
      "user_id": 1,
      "app": "slack",
      "name": "Work Slack",
      "auth_type": "api_key",
      "status": "active",
      "last_used_at": "2026-01-12T10:30:00",
      "last_tested_at": "2026-01-12T09:00:00",
      "last_test_status": "success",
      "created_at": "2026-01-10T08:00:00"
    },
    {
      "id": 2,
      "user_id": 1,
      "app": "slack",
      "name": "Personal Slack",
      "auth_type": "oauth2",
      "status": "active",
      "last_used_at": null,
      "last_tested_at": "2026-01-11T15:00:00",
      "last_test_status": "success",
      "created_at": "2026-01-11T14:00:00"
    }
  ]
}
```

### Get Single Connection

```
GET /connections/{id}
```

**Response:**

```json
{
  "id": 1,
  "user_id": 1,
  "app": "slack",
  "name": "Work Slack",
  "auth_type": "api_key",
  "status": "active",
  "last_used_at": "2026-01-12T10:30:00",
  "last_tested_at": "2026-01-12T09:00:00",
  "last_test_status": "success",
  "created_at": "2026-01-10T08:00:00"
}
```

### Update Connection

```
PUT /connections/{id}
```

**Request Body:**

```json
{
  "name": "Updated Connection Name",
  "status": "inactive",
  "credentials": {
    "bot_token": "xoxb-new-token-here"
  }
}
```

All fields are optional. Only include fields you want to update.

**Response:**

```json
{
  "id": 1,
  "user_id": 1,
  "app": "slack",
  "name": "Updated Connection Name",
  "auth_type": "api_key",
  "status": "inactive",
  "last_used_at": "2026-01-12T10:30:00",
  "last_tested_at": "2026-01-12T09:00:00",
  "last_test_status": "success",
  "created_at": "2026-01-10T08:00:00"
}
```

### Delete Connection

```
DELETE /connections/{id}
```

**Response:**

```json
{
  "deleted": true,
  "id": 1
}
```

### Test Connection

```
POST /connections/{id}/test
```

**Response (Success):**

```json
{
  "success": true,
  "message": "Connected to workspace: Acme Corp",
  "details": {
    "team": "Acme Corp",
    "user": "zaplane-bot",
    "user_id": "U12345678",
    "team_id": "T12345678",
    "url": "https://acmecorp.slack.com/"
  }
}
```

**Response (Failure):**

```json
{
  "success": false,
  "message": "Slack API error: invalid_auth",
  "details": {}
}
```

---

## Frontend Implementation Guide

### Connection Form Component

Here's a recommended flow for building the connection form:

```javascript
// 1. Fetch auth configuration
const authConfig = await fetch('/wp-json/zaplane/v1/connections/auth-fields/slack')
  .then(r => r.json());

// 2. Check if integration supports multiple auth types
if (authConfig.auth_type === 'both') {
  // Show auth type selector
  showAuthTypeSelector(authConfig.available_auth_types);
}

// 3. When user selects auth type, fetch specific fields
const selectedAuthType = 'api_key'; // or 'oauth2'
const specificFields = await fetch(
  `/wp-json/zaplane/v1/connections/auth-fields/slack?auth_type=${selectedAuthType}`
).then(r => r.json());

// 4. Render form fields
renderFields(specificFields.auth_fields);
```

### Complete Example: React Component

```jsx
function ConnectionForm({ app }) {
  const [authConfig, setAuthConfig] = useState(null);
  const [selectedAuthType, setSelectedAuthType] = useState(null);
  const [fields, setFields] = useState({});
  const [name, setName] = useState('');
  const [credentials, setCredentials] = useState({});

  // Fetch auth configuration on mount
  useEffect(() => {
    fetch(`/wp-json/zaplane/v1/connections/auth-fields/${app}`)
      .then(r => r.json())
      .then(config => {
        setAuthConfig(config);
        // Auto-select if only one auth type
        if (config.auth_type !== 'both') {
          setSelectedAuthType(config.auth_type);
          setFields(config.auth_fields);
        }
      });
  }, [app]);

  // Fetch fields when auth type changes
  useEffect(() => {
    if (selectedAuthType && authConfig?.auth_type === 'both') {
      fetch(`/wp-json/zaplane/v1/connections/auth-fields/${app}?auth_type=${selectedAuthType}`)
        .then(r => r.json())
        .then(data => setFields(data.auth_fields));
    }
  }, [selectedAuthType, app, authConfig]);

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (selectedAuthType === 'oauth2') {
      // OAuth flow
      const response = await fetch('/wp-json/zaplane/v1/connections/oauth/init', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          app,
          name,
          credentials
        })
      }).then(r => r.json());

      // Open OAuth popup
      const popup = window.open(response.auth_url, 'oauth', 'width=600,height=700');

      // Listen for callback
      window.addEventListener('message', (event) => {
        if (event.data?.type === 'zaplane_oauth_callback') {
          popup.close();
          if (event.data.data.success) {
            onSuccess(event.data.data.connection_id);
          } else {
            onError(event.data.data.message);
          }
        }
      });
    } else {
      // Token-based flow
      const response = await fetch('/wp-json/zaplane/v1/connections', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          app,
          name,
          auth_type: selectedAuthType,
          credentials
        })
      }).then(r => r.json());

      if (response.test_result?.success) {
        onSuccess(response.id);
      } else {
        onError(response.test_result?.message || 'Connection failed');
      }
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {/* Connection Name (always required) */}
      <div>
        <label>Connection Name</label>
        <input
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="My Slack Workspace"
          required
        />
      </div>

      {/* Auth Type Selector (if multiple types available) */}
      {authConfig?.auth_type === 'both' && (
        <div>
          <label>Authentication Method</label>
          {Object.entries(authConfig.available_auth_types).map(([key, value]) => (
            <label key={key}>
              <input
                type="radio"
                name="auth_type"
                value={key}
                checked={selectedAuthType === key}
                onChange={() => setSelectedAuthType(key)}
              />
              {value.label}
              <small>{value.description}</small>
            </label>
          ))}
        </div>
      )}

      {/* Dynamic Fields */}
      {Object.entries(fields).map(([key, field]) => (
        <div key={key}>
          <label>{field.label}</label>
          {field.type === 'textarea' ? (
            <textarea
              placeholder={field.placeholder}
              required={field.required}
              onChange={(e) => setCredentials(prev => ({
                ...prev,
                [key]: e.target.value
              }))}
            />
          ) : (
            <input
              type={field.type}
              placeholder={field.placeholder}
              required={field.required}
              onChange={(e) => setCredentials(prev => ({
                ...prev,
                [key]: e.target.value
              }))}
            />
          )}
          {field.help && <small>{field.help}</small>}
        </div>
      ))}

      <button type="submit">
        {selectedAuthType === 'oauth2' ? 'Connect with OAuth' : 'Save Connection'}
      </button>
    </form>
  );
}
```

---

## Error Handling

All error responses follow this format:

```json
{
  "code": "error_code",
  "message": "Human readable error message",
  "data": {
    "status": 400
  }
}
```

**Common Error Codes:**

| Code | Status | Description |
|------|--------|-------------|
| `rest_forbidden` | 401 | Not logged in |
| `rest_forbidden` | 403 | Don't own this connection |
| `not_found` | 404 | Connection or integration not found |
| `invalid_credentials` | 400 | Credentials format invalid |
| `oauth_init_failed` | 400 | Failed to start OAuth flow |
| `invalid_state` | 400 | OAuth state token expired/invalid |
| `token_exchange_failed` | 400 | OAuth code exchange failed |

---

## Slack-Specific Setup Guide

### Creating a Slack App

1. Go to [api.slack.com/apps](https://api.slack.com/apps)
2. Click "Create New App" > "From scratch"
3. Enter App Name and select workspace
4. Click "Create App"

### For OAuth 2.0:

1. Go to "OAuth & Permissions" in your app settings
2. Add Redirect URL: `https://your-site.com/wp-json/zaplane/v1/connections/oauth/callback`
3. Under "Bot Token Scopes", add:
   - `chat:write`
   - `channels:read`
   - `users:read`
   - `im:write`
4. Go to "Basic Information" to get:
   - **Client ID**
   - **Client Secret**

### For Bot Token:

1. Go to "OAuth & Permissions" in your app settings
2. Under "Bot Token Scopes", add the required scopes (same as above)
3. Click "Install to Workspace"
4. Copy the **Bot User OAuth Token** (starts with `xoxb-`)

---

## Security Notes

- Credentials are encrypted using AES-256-GCM before storage
- OAuth state tokens expire after 10 minutes
- All endpoints require authentication
- Users can only access their own connections
- Credentials are never returned in API responses
