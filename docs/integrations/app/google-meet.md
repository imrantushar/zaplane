# Google Meet Integration

Schedule Google Meet meetings, manage calendar events, and create instant Meet spaces directly from your automation workflows.

> **Actions only** — This integration provides meeting management actions. Real-time meeting event triggers are not supported.

---

## How It Works

This integration uses two Google APIs:

| API | Used for |
|---|---|
| **Google Calendar API** | Creating, updating, and cancelling scheduled meetings (with Meet links auto-generated) |
| **Google Meet API** | Creating instant ad-hoc Meet spaces and ending active conferences |

---

## Prerequisites

1. A **Google account** (personal Gmail or Google Workspace)
2. A **Google Cloud project** with both APIs enabled
3. An **OAuth 2.0 Client ID and Secret**

---

## Google Cloud Setup

### Step 1 — Create or select a project

1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Create a new project or select an existing one

### Step 2 — Enable required APIs

In **APIs & Services → Library**, search for and enable both:

- **Google Calendar API**
- **Google Meet API**

### Step 3 — Configure the OAuth consent screen

1. Go to **APIs & Services → OAuth consent screen**
2. Select **External** (or Internal for Google Workspace orgs)
3. Fill in App name and your email
4. Add the following **scopes**:
   - `https://www.googleapis.com/auth/calendar`
   - `https://www.googleapis.com/auth/calendar.events`
   - `https://www.googleapis.com/auth/meetings.space.created`
5. Under **Test users**, add your Google account email

### Step 4 — Create OAuth credentials

1. Go to **APIs & Services → Credentials → Create Credentials → OAuth client ID**
2. Application type: **Web application**
3. Add your redirect URI: `https://yourdomain.com/wp-json/zaplane/v1/connections/oauth/callback`
4. Copy the **Client ID** and **Client Secret**

---

## Connecting Google Meet in Zaplane

1. Open **Connections** and click **Add Connection**
2. Select **Google Meet**
3. Enter your **Client ID** and **Client Secret**
4. Click **Connect with Google** and complete the consent screen
5. After redirect, click **Test Connection** to verify
6. Save the connection

---

## Available Actions

### 1. Create Meeting

Creates a Google Calendar event with a Google Meet link automatically generated.

| Field | Required | Description |
|---|---|---|
| `summary` | Yes | Meeting title. Supports `{{variable}}` placeholders. |
| `description` | No | Meeting description or agenda |
| `start_datetime` | Yes | ISO 8601 format: `2026-05-01T10:00:00` |
| `end_datetime` | Yes | ISO 8601 format: `2026-05-01T11:00:00` |
| `timezone` | No | IANA timezone (e.g. `America/New_York`, `Europe/London`). Default: `UTC` |
| `attendees` | No | Comma-separated email addresses to invite |
| `send_notifications` | No | Whether to send calendar invites to attendees (default: Yes) |
| `calendar` | No | Which calendar to add the event to (default: primary) |

**Output data:**

| Key | Description |
|---|---|
| `google_event_id` | Google Calendar event ID — use in Update/Cancel actions |
| `google_event_summary` | Meeting title |
| `google_event_link` | Link to the event in Google Calendar |
| `google_meet_link` | The `https://meet.google.com/xxx-xxxx-xxx` join URL |
| `google_conference_id` | Internal conference ID |
| `google_start` | Start datetime (from API response) |
| `google_end` | End datetime (from API response) |

---

### 2. Update Meeting

Updates the details of an existing Calendar event.

| Field | Required | Description |
|---|---|---|
| `event_id` | Yes | The event ID. Use `{{google_event_id}}` from a Create Meeting node. |
| `summary` | No | New title |
| `description` | No | New description |
| `start_datetime` | No | New start time (ISO 8601). Leave empty to keep existing. |
| `end_datetime` | No | New end time |
| `timezone` | No | New timezone |
| `attendees` | No | New attendee list — **replaces** the existing list |
| `calendar` | No | Calendar ID (default: primary) |

**Output data:** Same keys as Create Meeting.

---

### 3. Cancel Meeting

Deletes a Google Calendar event and optionally notifies attendees.

| Field | Required | Description |
|---|---|---|
| `event_id` | Yes | The event ID to cancel. Use `{{google_event_id}}`. |
| `send_notifications` | No | Whether to email attendees about the cancellation |
| `calendar` | No | Calendar ID (default: primary) |

**Output data:**

| Key | Description |
|---|---|
| `google_event_id` | The cancelled event ID |
| `google_cancelled` | `true` on success |

---

### 4. Create Instant Space

Creates a reusable Google Meet space with no schedule — useful for on-demand video calls.

| Field | Required | Description |
|---|---|---|
| `access_type` | No | Who can join: `OPEN` (anyone with link), `TRUSTED` (org members), `RESTRICTED` (invited only). Default: Google's default. |

**Output data:**

| Key | Description |
|---|---|
| `google_meet_link` | The Meet join URL (`https://meet.google.com/…`) |
| `google_space_name` | Space resource name (e.g. `spaces/jQCFfuBOdKE`) — use in End Conference |
| `google_meeting_code` | Short meeting code (e.g. `abc-defg-hij`) |

---

### 5. End Active Conference

Forcefully ends an active Google Meet session in a given space. The space itself is not deleted — only the ongoing conference is terminated.

> Requires the authenticated user to be the space creator or host.

| Field | Required | Description |
|---|---|---|
| `space_name` | Yes | The space resource name. Use `{{google_space_name}}` from a Create Space node. |

**Output data:**

| Key | Description |
|---|---|
| `google_space_name` | The space that was ended |
| `google_conference_ended` | `true` on success |

---

## Datetime Format

All date/time fields use **ISO 8601** format:

```
YYYY-MM-DDTHH:MM:SS
```

Examples:
- `2026-05-01T09:00:00` — 9:00 AM on May 1, 2026
- `2026-12-25T14:30:00` — 2:30 PM on December 25, 2026

Set the `timezone` field to ensure the correct local time is used (e.g. `America/Chicago`, `Asia/Tokyo`).

---

## Common Errors

| Error | Cause | Fix |
|---|---|---|
| `access_token is required` | Token missing | Reconnect via OAuth |
| `The caller does not have permission` (403) | Insufficient OAuth scope or wrong calendar | Reconnect with all scopes; check the calendar ID |
| `Not Found` (404) | Wrong event_id or space_name | Verify the ID from the Create Meeting/Space output |
| `Invalid Credentials` (401) | Token expired | Reconnect — the token will refresh automatically |
| `summary (title) is required` | Title field empty | Fill in the meeting title |
| `start/end date & time is required` | Missing datetime | Provide an ISO 8601 datetime string |

---

## Example Workflows

### Schedule an onboarding call after user registers

1. **Trigger:** WordPress → User Registered
2. **Action:** Google Meet → Create Meeting
   - `summary`: `Onboarding Call with {{display_name}}`
   - `start_datetime`: *(use a Variable node to compute next business day)*
   - `end_datetime`: *(start + 1 hour)*
   - `attendees`: `{{user_email}}`
3. **Action:** Gmail → Send Email
   - `to`: `{{user_email}}`
   - `body`: `Join your onboarding call: {{google_meet_link}}`

### Create an instant support room from a form submission

1. **Trigger:** Contact Form → Form Submitted
2. **Action:** Google Meet → Create Instant Space
3. **Action:** Slack → Send Message
   - `channel`: `#support`
   - `text`: `New support request from {{name}}. Join here: {{google_meet_link}}`

### Reschedule a meeting when an order is refunded

1. **Trigger:** WooCommerce → Order Refunded
2. **Action:** Google Meet → Cancel Meeting
   - `event_id`: `{{google_event_id}}`
   - `send_notifications`: Yes
3. **Action:** Gmail → Send Email
   - `to`: `{{billing_email}}`
   - `body`: `Your scheduled call has been cancelled due to the refund.`

---

## Resources

- [Google Calendar API Documentation](https://developers.google.com/calendar/api)
- [Google Meet REST API Documentation](https://developers.google.com/meet/api)
- [Google Cloud Console](https://console.cloud.google.com)
- [IANA Timezone List](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones)
