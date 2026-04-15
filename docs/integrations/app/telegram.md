# Telegram Integration

Send messages and interact with Telegram chats directly from your automation workflows using the **Telegram Bot API**.

---

## Prerequisites

Before connecting Telegram you need a **Telegram Bot** and its token:

1. Open Telegram and start a chat with **@BotFather**
2. Send `/newbot` and follow the prompts (choose a name and username ending in `bot`)
3. BotFather will reply with a **Bot Token** like `123456789:AABBCCDDEEFFxxxxxxxxxxxx`
4. Keep this token — you will paste it into Zaplane

### Getting the Chat ID

Every action requires a **Chat ID** — the unique identifier of the chat you want to send to.

| Chat type | How to get the ID |
|---|---|
| **Private chat** | Message your bot, then open `https://api.telegram.org/bot<TOKEN>/getUpdates` — look for `"chat":{"id":...}` |
| **Group** | Add the bot to the group, send a message, then use `getUpdates` as above |
| **Channel** | Add the bot as admin, use the channel `@username` (e.g. `@mychannel`) or the numeric ID (e.g. `-1001234567890`) |

---

## Connecting Telegram

1. In Zaplane, open **Connections** and click **Add Connection**.
2. Select **Telegram** from the integration list.
3. Paste your **Bot Token**.
4. Click **Test Connection** to verify — it will display your bot's username on success.
5. Save the connection.

---

## Triggers

### Message Received

Fires when any message is sent to your bot or a group/channel the bot is in.

> **Setup required:** Register a webhook so Telegram pushes messages to Zaplane. Copy the webhook URL from the workflow trigger settings and call:
> ```
> https://api.telegram.org/bot<TOKEN>/setWebhook?url=<WEBHOOK_URL>
> ```

**Available output data:**

| Key | Description |
|---|---|
| `telegram_message_id` | Unique message ID |
| `telegram_text` | Full message text |
| `telegram_chat_id` | ID of the chat the message came from |
| `telegram_chat_type` | Chat type: `private`, `group`, `supergroup`, or `channel` |
| `telegram_from_id` | Sender's Telegram user ID |
| `telegram_from_first_name` | Sender's first name |
| `telegram_from_last_name` | Sender's last name |
| `telegram_from_username` | Sender's @username (may be empty) |
| `telegram_date` | Unix timestamp of the message |

---

### Command Received

Fires when a message starting with `/` (slash command) is sent to your bot.

Same webhook setup as **Message Received** above. Includes all the same output data plus:

| Key | Description |
|---|---|
| `telegram_command` | The command itself (e.g. `/start`) |
| `telegram_command_args` | Everything after the command (e.g. `welcome` from `/start welcome`) |

---

## Actions

### 1. Send Text Message

Sends a text message to a chat or user.

| Field | Required | Description |
|---|---|---|
| `chat_id` | Yes | Target chat ID or @username |
| `text` | Yes | Message content. Supports `{{variable}}` placeholders. |
| `parse_mode` | No | `HTML`, `Markdown`, or `MarkdownV2` for formatting |
| `disable_notification` | No | `Yes` sends the message silently (no sound/notification) |

**Parse mode examples:**

```
HTML:       <b>Bold</b>, <i>Italic</i>, <a href="https://example.com">Link</a>
Markdown:   *Bold*, _Italic_, [Link](https://example.com)
MarkdownV2: *Bold*, _Italic_, escape special chars with \
```

**Output data:**

| Key | Description |
|---|---|
| `telegram_message_id` | ID of the sent message |
| `telegram_chat_id` | Target chat ID used |
| `telegram_status` | Always `sent` on success |
| `telegram_timestamp` | Unix timestamp when sent |

---

### 2. Send Photo

Sends an image to a chat.

| Field | Required | Description |
|---|---|---|
| `chat_id` | Yes | Target chat ID or @username |
| `photo` | Yes | Publicly accessible URL to a JPG, PNG, GIF, BMP, or WebP image |
| `caption` | No | Optional caption (up to 1024 characters) |

---

### 3. Send Document

Sends a file to a chat.

| Field | Required | Description |
|---|---|---|
| `chat_id` | Yes | Target chat ID |
| `document` | Yes | Publicly accessible URL to any file |
| `caption` | No | Optional caption |

---

### 4. Send Video

Sends a video to a chat.

| Field | Required | Description |
|---|---|---|
| `chat_id` | Yes | Target chat ID |
| `video` | Yes | Publicly accessible URL to an MP4 video |
| `caption` | No | Optional caption |

---

### 5. Send Audio

Sends an audio file to a chat (displayed as a music player).

| Field | Required | Description |
|---|---|---|
| `chat_id` | Yes | Target chat ID |
| `audio` | Yes | Publicly accessible URL to an MP3 or M4A file |
| `caption` | No | Optional caption |

---

### 6. Send Location

Sends a map pin with latitude/longitude coordinates.

| Field | Required | Description |
|---|---|---|
| `chat_id` | Yes | Target chat ID |
| `latitude` | Yes | Decimal latitude (e.g. `40.7128`) |
| `longitude` | Yes | Decimal longitude (e.g. `-74.0060`) |

---

### 7. Pin Message

Pins a message in a group or channel. The bot must be an admin with pin permission.

| Field | Required | Description |
|---|---|---|
| `chat_id` | Yes | Target chat ID |
| `message_id` | Yes | ID of the message to pin. Use `{{telegram_message_id}}` from a previous node. |
| `disable_notification` | No | `Yes` pins silently without notifying members |

**Output data:**

| Key | Description |
|---|---|
| `telegram_pinned` | `true` on success |
| `telegram_chat_id` | Target chat ID used |

---

### 8. Send Poll

Creates a poll in a group or channel.

| Field | Required | Description |
|---|---|---|
| `chat_id` | Yes | Target chat ID |
| `question` | Yes | The poll question (1–300 characters) |
| `options` | Yes | One option per line (2–10 options) |
| `is_anonymous` | No | Whether voters are anonymous (default: Yes) |

**Options example:**
```
Option A
Option B
Option C
```

---

## Common Errors

| Error | Cause | Fix |
|---|---|---|
| `bot_token is required` | No token in connection | Edit the connection and add the Bot Token |
| `Unauthorized` | Invalid or revoked token | Generate a new token with @BotFather |
| `chat not found` | Wrong chat ID | Verify the chat ID using `getUpdates` |
| `bot is not a member` | Bot removed from group/channel | Re-add the bot |
| `not enough rights to pin a message` | Bot not admin | Promote the bot to admin in the group/channel |
| `message_id is required` | Missing message ID for pin | Use `{{telegram_message_id}}` from a Send Message node |

---

## Example Workflows

### Notify a Telegram group on WooCommerce order

1. **Trigger:** WooCommerce → Order Created
2. **Action:** Telegram → Send Text Message
   - `chat_id`: `-1001234567890` (your group ID)
   - `text`: `🛒 New order #{{order_number}} from {{billing_first_name}} {{billing_last_name}} — Total: {{order_total}}`
   - `parse_mode`: None

### Bot command handler

1. **Trigger:** Telegram → Command Received
2. **Filter:** Condition node — `{{telegram_command}}` equals `/subscribe`
3. **Action:** FluentCRM → Add Contact to List
   - Use `{{telegram_from_username}}` or ask user for email in a follow-up message

---

## Bot Permissions

| Action | Required bot permission |
|---|---|
| Send messages to private chats | User must have started a chat with the bot |
| Send to groups | Bot must be a member of the group |
| Send to channels | Bot must be an admin of the channel |
| Pin messages | Bot must be admin with "Pin Messages" permission |
| Send polls | Bot must have permission to post messages |

---

## Resources

- [Telegram Bot API Documentation](https://core.telegram.org/bots/api)
- [BotFather](https://t.me/botfather) — create and manage bots
- [Getting Updates (Webhook setup)](https://core.telegram.org/bots/api#setwebhook)
