# Gmail Integration

Send emails, create drafts, and manage labels in Gmail directly from your automation workflows using the **Gmail API**.

> **Actions only** — This integration provides send/manage actions. Real-time email received triggers require Google Pub/Sub and are not included.

---

## Prerequisites

1. A **Google account** with Gmail
2. A **Google Cloud project** with the Gmail API enabled
3. An **OAuth 2.0 Client ID and Secret** from Google Cloud Console

---

## Google Cloud Setup

### Step 1 — Create a Google Cloud Project

1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Click **Select a project → New Project**
3. Name it (e.g. "Zaplane Gmail") and click **Create**

### Step 2 — Enable the Gmail API

1. In your project, go to **APIs & Services → Library**
2. Search for **Gmail API**
3. Click it and press **Enable**

### Step 3 — Create OAuth 2.0 Credentials

1. Go to **APIs & Services → Credentials**
2. Click **Create Credentials → OAuth client ID**
3. If prompted, configure the **OAuth consent screen** first:
   - User type: **External**
   - Fill in the app name and your email
   - Add scopes (see below)
   - Add your Gmail address as a **Test user**
4. Back in **Create OAuth client ID**:
   - Application type: **Web application**
   - Add your site's redirect URI: `https://yourdomain.com/wp-json/zaplane/v1/connections/oauth/callback`
5. Copy the **Client ID** and **Client Secret**

### Required OAuth Scopes

| Scope | Purpose |
|---|---|
| `https://www.googleapis.com/auth/gmail.send` | Send emails |
| `https://www.googleapis.com/auth/gmail.compose` | Create drafts |
| `https://www.googleapis.com/auth/gmail.modify` | Add/remove labels |
| `https://www.googleapis.com/auth/gmail.readonly` | Read labels list |

---

## Connecting Gmail in Zaplane

1. Open **Connections** and click **Add Connection**
2. Select **Gmail**
3. Enter your **Client ID** and **Client Secret**
4. Click **Connect with Google** — you'll be redirected to Google's consent screen
5. Sign in and grant the requested permissions
6. You'll be redirected back and the connection will be saved automatically
7. Click **Test Connection** to verify

> **Note:** Google may show a warning about the app being "unverified" while in test mode. Click **Advanced → Go to [App Name]** to proceed. For production use, submit your app for Google verification.

---

## Available Actions

### 1. Send Email

Composes and sends an email from your connected Gmail account.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient email address(es). Separate multiple with commas. |
| `cc` | No | CC recipients, comma-separated |
| `bcc` | No | BCC recipients, comma-separated |
| `subject` | Yes | Email subject line. Supports `{{variable}}` placeholders. |
| `body` | Yes | Email body. Supports `{{variable}}` placeholders. |
| `content_type` | No | `Plain Text` (default) or `HTML` |

**Output data available to next nodes:**

| Key | Description |
|---|---|
| `gmail_message_id` | The unique Gmail message ID |
| `gmail_thread_id` | The Gmail thread ID |
| `gmail_status` | Always `sent` on success |

---

### 2. Send Reply

Sends a reply within an existing email thread.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient email address |
| `cc` | No | CC recipients |
| `bcc` | No | BCC recipients |
| `subject` | Yes | Subject (typically `Re: Original Subject`) |
| `body` | Yes | Reply body |
| `content_type` | No | `Plain Text` or `HTML` |
| `thread_id` | Yes | The thread to reply in. Use `{{gmail_thread_id}}` from a previous node. |
| `message_id_header` | No | The `Message-ID` header of the original email for proper client threading. |

---

### 3. Create Draft

Saves a composed email as a draft without sending it.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient email |
| `cc` | No | CC recipients |
| `bcc` | No | BCC recipients |
| `subject` | Yes | Subject |
| `body` | Yes | Email body |
| `content_type` | No | `Plain Text` or `HTML` |

**Output data:**

| Key | Description |
|---|---|
| `gmail_draft_id` | The Draft ID (can be used to delete or send via Gmail UI) |
| `gmail_message_id` | The message ID inside the draft |
| `gmail_thread_id` | The thread ID |

---

### 4. Add Label to Message

Applies one or more labels to a Gmail message.

| Field | Required | Description |
|---|---|---|
| `message_id` | Yes | Gmail message ID. Use `{{gmail_message_id}}` from a previous node. |
| `label_ids` | Yes | Comma-separated label IDs (e.g. `IMPORTANT,Label_123`). Pick from the labels dropdown. |

**Common system label IDs:**

| ID | Meaning |
|---|---|
| `INBOX` | Move to inbox |
| `STARRED` | Star the message |
| `IMPORTANT` | Mark as important |
| `UNREAD` | Mark as unread |
| `TRASH` | Move to trash |
| `SPAM` | Move to spam |

**Output data:**

| Key | Description |
|---|---|
| `gmail_message_id` | The message ID |
| `gmail_thread_id` | The thread ID |
| `gmail_label_ids` | Array of all label IDs currently on the message |

---

### 5. Remove Label from Message

Removes one or more labels from a Gmail message.

| Field | Required | Description |
|---|---|---|
| `message_id` | Yes | Gmail message ID. Use `{{gmail_message_id}}` from a previous node. |
| `label_ids` | Yes | Comma-separated label IDs to remove. |

---

## HTML Email Example

Set **Content Type** to `HTML` and use HTML in the body:

```html
<h2>Order Confirmed!</h2>
<p>Hi {{billing_first_name}},</p>
<p>Your order <strong>#{{order_number}}</strong> has been received.</p>
<p>Total: <strong>{{order_total}}</strong></p>
<p>Thank you for shopping with us!</p>
```

---

## Common Errors

| Error | Cause | Fix |
|---|---|---|
| `access_token is required` | No token in connection | Reconnect via OAuth |
| `Invalid Credentials` (401) | Token expired | Reconnect — the token will be refreshed automatically |
| `Request had insufficient authentication scopes` (403) | Missing OAuth scope | Reconnect with all required scopes enabled |
| `recipient (to) is required` | `to` field is empty | Ensure the To field has a valid email address |
| `thread_id is required for replies` | Thread ID missing in Send Reply | Use `{{gmail_thread_id}}` from a previous trigger/action node |
| `message_id is required` | Missing message ID for label actions | Use `{{gmail_message_id}}` from the Send Email output |

---

## Example Workflows

### Send a welcome email after user registration

1. **Trigger:** WordPress → User Registered
2. **Action:** Gmail → Send Email
   - `to`: `{{user_email}}`
   - `subject`: `Welcome to {{site_name}}!`
   - `body`: `Hi {{display_name}}, thanks for joining us.`

### WooCommerce order confirmation with HTML

1. **Trigger:** WooCommerce → Order Created
2. **Action:** Gmail → Send Email
   - `to`: `{{billing_email}}`
   - `subject`: `Order #{{order_number}} Confirmed`
   - `body`: (HTML template with order details)
   - `content_type`: `HTML`

### Auto-reply to a form submission and label it

1. **Trigger:** Contact Form 7 → Form Submitted
2. **Action:** Gmail → Send Reply (or Send Email)
   - `to`: `{{email}}`
   - `subject`: `Re: {{subject}}`
   - `body`: `Thanks for reaching out! We'll get back to you within 24 hours.`
3. **Action:** Gmail → Add Label to Message
   - `message_id`: `{{gmail_message_id}}`
   - `label_ids`: `IMPORTANT`

---

## Resources

- [Gmail API Documentation](https://developers.google.com/gmail/api)
- [Google Cloud Console](https://console.cloud.google.com)
- [OAuth 2.0 Playground](https://developers.google.com/oauthplayground) — useful for testing access tokens manually
- [Gmail Label IDs](https://developers.google.com/gmail/api/reference/rest/v1/users.labels/list)
