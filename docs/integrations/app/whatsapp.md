# WhatsApp Integration

Send WhatsApp messages directly from your automation workflows using the **Meta WhatsApp Business Cloud API**.

> **Actions only** — This integration provides send actions. Incoming message triggers are not supported.

---

## Prerequisites

Before connecting WhatsApp you need:

1. A **Meta for Developers** account — [developers.facebook.com](https://developers.facebook.com)
2. A **WhatsApp Business Account (WABA)**
3. A registered **WhatsApp Business phone number** added to your app
4. An **Access Token** (permanent System User token or temporary test token)
5. The **Phone Number ID** of your registered phone number

### Where to find your credentials

| Credential | Where to find it |
|---|---|
| **Access Token** | Meta for Developers → Your App → WhatsApp → API Setup → "Temporary access token" (testing) or create a System User token for production |
| **Phone Number ID** | Meta for Developers → Your App → WhatsApp → API Setup → Phone numbers list |
| **API Version** | Optional. Defaults to `v26.0` — see [Meta Graph API Version](meta-graph-version.md). Set one only if Meta asks you to pin a version. |

---

## Connecting WhatsApp

1. In Zaplane, open **Connections** and click **Add Connection**.
2. Select **WhatsApp** from the integration list.
3. Fill in the connection fields:
   - **Access Token** — your permanent or temporary Bearer token
   - **Phone Number ID** — the numeric ID of your sender phone number
   - **API Version** *(optional)* — leave blank to use `v26.0`
4. Click **Test Connection** to verify.
5. Save the connection.

> **Tip:** For production use, generate a permanent **System User** access token in the Meta Business Suite. Temporary tokens expire after 24 hours.

---

## Available Actions

### 1. Send Text Message

Sends a plain text message to a recipient.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient phone number in E.164 format **without** the `+` sign (e.g. `15551234567`) |
| `body` | Yes | The text content to send. Supports `{{variable}}` placeholders. |

**Output data available to next nodes:**

| Key | Description |
|---|---|
| `whatsapp_message_id` | WhatsApp message ID (e.g. `wamid.xxx`) |
| `whatsapp_to` | Recipient number used |
| `whatsapp_status` | Always `sent` on success |
| `whatsapp_timestamp` | Unix timestamp when the request was made |

---

### 2. Send Template Message

Sends a pre-approved WhatsApp message template. Templates must be approved in the Meta Business Manager before use.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient phone number (E.164, no `+`) |
| `template_name` | Yes | Exact name of the approved template (e.g. `hello_world`) |
| `language_code` | Yes | BCP-47 language/locale code (e.g. `en_US`, `es_ES`, `pt_BR`) |
| `components` | No | JSON array of template component parameters for variable substitution |

**Components example** (for a template with a body variable):
```json
[
  {
    "type": "body",
    "parameters": [
      { "type": "text", "text": "John" }
    ]
  }
]
```

---

### 3. Send Image

Sends an image from a publicly accessible URL.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient phone number |
| `link` | Yes | Publicly accessible URL to a JPEG or PNG image |
| `caption` | No | Optional caption displayed below the image |

---

### 4. Send Document

Sends a document (PDF, DOCX, etc.) from a publicly accessible URL.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient phone number |
| `link` | Yes | Publicly accessible URL to the document |
| `filename` | No | Display filename shown to the recipient (e.g. `invoice.pdf`) |
| `caption` | No | Optional caption |

---

### 5. Send Video

Sends an MP4 video from a publicly accessible URL.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient phone number |
| `link` | Yes | Publicly accessible URL to an MP4 video |
| `caption` | No | Optional caption |

---

### 6. Send Audio

Sends an audio file (MP3 or OGG) from a publicly accessible URL.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient phone number |
| `link` | Yes | Publicly accessible URL to the audio file |

---

### 7. Send Location

Sends a pin/location to a recipient.

| Field | Required | Description |
|---|---|---|
| `to` | Yes | Recipient phone number |
| `latitude` | Yes | Latitude as a decimal number (e.g. `40.7128`) |
| `longitude` | Yes | Longitude as a decimal number (e.g. `-74.0060`) |
| `name` | No | Location name shown to recipient |
| `address` | No | Street address shown to recipient |

---

## Phone Number Format

WhatsApp requires phone numbers in **E.164 format without the leading `+`**:

| Format | Example |
|---|---|
| Correct | `15551234567` |
| Incorrect | `+15551234567` |
| Incorrect | `(555) 123-4567` |
| Incorrect | `555-123-4567` |

You can use a `{{variable}}` from a previous trigger node to fill the `to` field dynamically (e.g. `{{user_phone}}`).

---

## Common Errors

| Error | Cause | Fix |
|---|---|---|
| `access_token is required` | Connection saved without a token | Edit the connection and add the access token |
| `phone_number_id is required` | Phone Number ID missing | Edit the connection and add the Phone Number ID |
| `Invalid OAuth access token` (code 190) | Token expired or invalid | Regenerate your access token in Meta Developer Console |
| `Invalid phone number` (code 100) | Recipient number format wrong | Ensure number is E.164 without `+` (e.g. `15551234567`) |
| `Template name does not exist` | Wrong template name | Check the exact template name in Meta Business Manager |
| `message body is required` | Body field empty in Send Text | Fill in the message body field |

---

## Example Workflow

**Goal:** Send a WhatsApp confirmation after a WooCommerce order is placed.

1. **Trigger:** WooCommerce → Order Created
2. **Action:** WhatsApp → Send Text Message
   - `to`: `{{billing_phone}}`
   - `body`: `Hi {{billing_first_name}}, your order #{{order_number}} has been received! We'll notify you when it ships.`

---

## Rate Limits

The Meta WhatsApp Business Cloud API enforces rate limits at the phone number level. For high-volume use cases, refer to the [Meta documentation on rate limits](https://developers.facebook.com/docs/whatsapp/cloud-api/overview#rate-limiting).

---

## Resources

- [Meta Graph API Version](meta-graph-version.md) — which version Zaplane calls, and how to pin another
- [Meta WhatsApp Business Cloud API Documentation](https://developers.facebook.com/docs/whatsapp/cloud-api)
- [Message Templates Guide](https://developers.facebook.com/docs/whatsapp/message-templates)
- [Meta for Developers Console](https://developers.facebook.com)
