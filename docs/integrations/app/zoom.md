# Zoom Integration

Schedule Zoom meetings, manage registrants, and react to live meeting events directly from your automation workflows using the **Zoom REST API**.

---

## Prerequisites

1. A **Zoom account** (Free, Pro, Business, or Enterprise)
2. A **Zoom app** created in the Zoom Marketplace
3. An **OAuth 2.0 Client ID and Secret** from your Zoom app

---

## Zoom App Setup

### Step 1 — Create an OAuth app

1. Go to [marketplace.zoom.us](https://marketplace.zoom.us) and sign in
2. Click **Develop → Build App**
3. Choose **OAuth** as the app type
4. Select **User-managed** (recommended for automation per individual user account)
5. Enter an app name (e.g. "Zaplane") and click **Create**

### Step 2 — Configure redirect URI

In your app's **App Credentials** tab, add the redirect URI:

```
https://yourdomain.com/wp-json/zaplane/v1/connections/oauth/callback
```

Copy the **Client ID** and **Client Secret** shown on this page.

### Step 3 — Add required scopes

In the **Scopes** tab, add:

| Scope | Purpose |
|---|---|
| `meeting:write` | Create and update meetings |
| `meeting:read` | Read meeting details |
| `user:read` | Verify connection (test_connection) |

Click **Save** and then **Add to Zoom** to activate the app for your account.

### Step 4 — Configure webhooks (for triggers)

In the **Feature** tab, enable **Event Subscriptions** and add a subscription:

- **Event notification endpoint URL**: `https://yourdomain.com/wp-json/zaplane/v1/incoming-webhook/zoom`
- **Event types to subscribe**:
  - `meeting.started`
  - `meeting.ended`
  - `meeting.participant_joined`

Copy the **Secret Token** — you may need it for webhook signature verification.

---

## Connecting Zoom in Zaplane

1. Open **Connections** and click **Add Connection**
2. Select **Zoom**
3. Enter your **Client ID** and **Client Secret**
4. Click **Connect with Zoom** and complete the authorisation screen
5. After redirect, click **Test Connection** to verify
6. Save the connection

---

## Triggers

### Meeting Started

Fires when a Zoom meeting begins.

**Available output data:**

| Key | Description |
|---|---|
| `zoom_meeting_id` | Zoom meeting ID |
| `zoom_topic` | Meeting topic/title |
| `zoom_host_id` | Host's Zoom user ID |
| `zoom_start_time` | ISO 8601 start time |
| `zoom_duration` | Scheduled duration in minutes |
| `zoom_uuid` | Unique meeting UUID |

---

### Meeting Ended

Fires when a Zoom meeting concludes.

Same output data as **Meeting Started**, with an additional `zoom_end_time` field.

---

### Participant Joined

Fires when a participant joins a Zoom meeting.

Includes all base meeting fields plus:

| Key | Description |
|---|---|
| `zoom_participant_id` | Participant's Zoom user ID |
| `zoom_participant_name` | Participant's display name |
| `zoom_participant_email` | Participant's email (if available) |
| `zoom_join_time` | ISO 8601 time they joined |

---

## Actions

### 1. Create Meeting

Schedules a new Zoom meeting and returns the join/start links.

| Field | Required | Description |
|---|---|---|
| `topic` | Yes | Meeting title. Supports `{{variable}}` placeholders. |
| `start_time` | Yes | ISO 8601 local time: `2026-05-01T10:00:00` |
| `duration` | No | Length in minutes. Default: `60`. |
| `timezone` | No | IANA timezone (e.g. `America/New_York`). Default: `UTC`. |
| `agenda` | No | Meeting agenda description |
| `password` | No | Meeting passcode (up to 10 alphanumeric characters) |
| `waiting_room` | No | Enable waiting room. Default: Disabled. |
| `host_video` | No | Start with host video on. Default: Yes. |
| `user_id` | No | Host Zoom user ID or email. Default: `me` (authenticated user). |

**Output data:**

| Key | Description |
|---|---|
| `zoom_meeting_id` | Numeric Zoom meeting ID — use in Update/Delete/Registrant actions |
| `zoom_topic` | Meeting title |
| `zoom_join_url` | Attendee join link (`https://zoom.us/j/…`) |
| `zoom_start_url` | Host start link (includes host key) |
| `zoom_password` | Meeting passcode |
| `zoom_start_time` | Confirmed start time from Zoom |
| `zoom_duration` | Confirmed duration in minutes |

---

### 2. Update Meeting

Updates an existing scheduled meeting. Only fields you fill in are changed.

| Field | Required | Description |
|---|---|---|
| `meeting_id` | Yes | Zoom meeting ID. Use `{{zoom_meeting_id}}`. |
| `topic` | No | New title |
| `start_time` | No | New start time (ISO 8601). Leave empty to keep existing. |
| `duration` | No | New duration in minutes |
| `timezone` | No | New timezone |
| `agenda` | No | New agenda |
| `password` | No | New passcode |

**Output data:**

| Key | Description |
|---|---|
| `zoom_meeting_id` | The updated meeting ID |
| `zoom_updated` | `true` on success |

---

### 3. Delete Meeting

Permanently deletes a Zoom meeting.

| Field | Required | Description |
|---|---|---|
| `meeting_id` | Yes | Meeting ID to delete. Use `{{zoom_meeting_id}}`. |
| `notify_registrants` | No | Send cancellation email to registrants. Default: No. |

**Output data:**

| Key | Description |
|---|---|
| `zoom_meeting_id` | The deleted meeting ID |
| `zoom_deleted` | `true` on success |

---

### 4. Add Meeting Registrant

Registers an attendee for a meeting that requires registration. Returns a personalised join link for that registrant.

> The meeting must have **Registration required** enabled in Zoom settings.

| Field | Required | Description |
|---|---|---|
| `meeting_id` | Yes | Meeting ID. Use `{{zoom_meeting_id}}`. |
| `email` | Yes | Registrant's email address |
| `first_name` | Yes | Registrant's first name |
| `last_name` | No | Registrant's last name |
| `org` | No | Organisation / company name |
| `job_title` | No | Job title |

**Output data:**

| Key | Description |
|---|---|
| `zoom_registrant_id` | Unique registrant ID |
| `zoom_join_url` | Personalised join link for this registrant |
| `zoom_meeting_id` | The meeting ID |
| `zoom_topic` | Meeting title |
| `zoom_start_time` | Meeting start time |

---

## Datetime Format

All date/time fields use **ISO 8601** format in **local time** for the given timezone:

```
YYYY-MM-DDTHH:MM:SS
```

Examples:
- `2026-05-01T09:00:00` — 9:00 AM
- `2026-12-25T14:30:00` — 2:30 PM

Always set the `timezone` field to match the local time you provide (e.g. `America/Chicago`, `Asia/Kolkata`).

---

## Common Errors

| Error | Cause | Fix |
|---|---|---|
| `access_token is required` | Token missing | Reconnect via OAuth |
| `Access token is expired` (code 124) | Token expired | Zoom tokens are short-lived — reconnect or set up token refresh |
| `Meeting does not exist` (code 3001) | Wrong meeting ID | Verify the ID from the Create Meeting output |
| `meeting topic is required` | Topic field empty | Fill in the Meeting Topic field |
| `start time is required` | Start time missing | Provide an ISO 8601 datetime string |
| `first name is required` | First name missing for registrant | Add the registrant's first name |

---

## Example Workflows

### Create a Zoom onboarding call after purchase

1. **Trigger:** WooCommerce → Order Created
2. **Action:** Zoom → Create Meeting
   - `topic`: `Onboarding: {{billing_first_name}} {{billing_last_name}}`
   - `start_time`: *(computed with a Variable node)*
   - `duration`: `60`
   - `waiting_room`: Enabled
3. **Action:** Gmail → Send Email
   - `to`: `{{billing_email}}`
   - `body`: `Join your onboarding call: {{zoom_join_url}}`

### Register a form submitter for a webinar

1. **Trigger:** Contact Form → Form Submitted
2. **Action:** Zoom → Add Meeting Registrant
   - `meeting_id`: `87654321` *(your pre-created meeting ID)*
   - `email`: `{{email}}`
   - `first_name`: `{{name}}`
3. **Action:** Gmail → Send Email
   - `to`: `{{email}}`
   - `body`: `You're registered! Join here: {{zoom_join_url}}`

### Notify Slack when a Zoom meeting starts

1. **Trigger:** Zoom → Meeting Started
2. **Action:** Slack → Send Message
   - `channel`: `#team`
   - `text`: `Meeting "{{zoom_topic}}" just started! Join: *(add link from your calendar)*`

---

## Resources

- [Zoom REST API Documentation](https://developers.zoom.us/docs/api/)
- [Zoom Marketplace](https://marketplace.zoom.us)
- [Zoom OAuth guide](https://developers.zoom.us/docs/integrations/oauth/)
- [IANA Timezone List](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones)
