# Custom Apps — Testing an External Website / API Connection

Custom Apps let you turn **any external REST API** into a first-class Zaplane
integration — no PHP required. You describe the API visually (base URL, auth,
actions, triggers) and it shows up in the workflow builder like a built-in app.

This guide walks through building a Custom App that talks to an outside website
and **verifying the connection actually works**, end to end.

---

## Before you start

- You need an admin account (`manage_options`) — authoring a Custom App can issue
  outbound HTTP, so it's an admin-level capability.
- Have the target API's docs handy: its **base URL**, **auth method**, and at
  least one **endpoint** you can call.
- Requests are sent from your WordPress server, so the endpoint must be reachable
  from there. Only `http`/`https` URLs are allowed, and sites can further block
  hosts via the `zaplane_http_block_request` filter (SSRF protection).

---

## 1. Open the Custom Apps builder

**WP Admin → Zaplane → Custom Apps → New app.**

You'll see four tabs:

| Tab | What it's for |
|---|---|
| **Details** | Name, slug, kind, base URL, icon |
| **Authentication** | How to authenticate (none / API key / bearer / basic / OAuth2) |
| **Actions** | Things a workflow can *do* (send an HTTP request) |
| **Triggers** | Events that *start* a workflow (polling or webhook) |

---

## 2. Details

| Field | Example | Notes |
|---|---|---|
| **Name** | `Pipedrive` | Display name shown in the picker |
| **Slug** | `pipedrive` | Auto-generated from the name; lowercase, letters/numbers/underscores |
| **Kind** | `REST API (external service)` | Keep this for external websites. (`WordPress plugin (same site)` is for local hook/function integrations.) |
| **Base URL** | `https://api.pipedrive.com/v1` | Every action's `path` is resolved against this |

> Tip: the quickest way to prove the whole pipeline works before touching a real
> API is to point **Base URL** at a free test API such as
> `https://jsonplaceholder.typicode.com`.

---

## 3. Authentication

Pick the type your API uses. The fields you define here are what a user fills in
when they create a **Connection** for the app.

### No auth
For open/public APIs. Nothing else to configure.

### API key / Bearer token
1. Add a connection field, e.g. `key = api_key`, type `password`.
2. Set **Inject in** (`Header` or `Query param`), a **Name**, and a **Value
   template**:
   - Header bearer: Name `Authorization`, template `Bearer {{ creds.api_key }}`
   - Query key: Name `apikey`, template `{{ creds.api_key }}`
3. Optional **Test endpoint path** (e.g. `/me`) powers the **Test connection**
   button.

### Basic auth
Add `username` and `password` connection fields — they're combined into an
`Authorization: Basic …` header automatically.

### OAuth 2.0
Add `client_id` / `client_secret` fields and fill **Authorize URL**, **Token
URL**, optional **Refresh URL**, and **Scopes**. Users connect with a popup and
tokens are stored/refreshed for them; requests default to
`Authorization: Bearer {{ creds.access_token }}`.

`{{ creds.* }}` refers to the connected account's stored credentials.

---

## 4. Define an Action (and test the request live)

Under **Actions → Add action**:

1. **Key** / **Label** — e.g. `get_deal` / *Get Deal*.
2. **Input fields** — what the workflow user fills in (e.g. `deal_id`, type
   `text`). These become `{{ field_key }}` placeholders.
3. **Request** — the call to make:
   - **Method**: `GET` / `POST` / …
   - **Path**: relative to the base URL, e.g. `/deals/{{ deal_id }}`
   - **Headers (JSON)** and **Body (JSON)** — may contain `{{ placeholders }}`.
   - Upstream workflow output is available under `{{ input.* }}`, e.g.
     `{{ input.id }}` or `{{ input.response.data }}`. This is useful when an
     action should pass data from the preceding node without adding a duplicate
     input field.
4. **Outputs** — fields downstream nodes can use (see step 5).

### ✅ Send test request — the key verification step

Below the request there's a **Test** panel:

1. Fill the **(test)** sample values for your input fields.
2. If the app uses authentication, fill the **Authentication values (test only)**
   fields shown below the input fields. These values are sent only for this
   preview and are never saved into the app manifest.
3. Click **Send test request**.
4. You'll see the live result:
   - The resolved **URL** and **HTTP status** (green = 2xx).
   - The full **response body** (JSON).
   - An error message if the call failed.

This runs the request through the exact same code path the workflow uses
(interpolation + auth injection + SSRF-guarded HTTP), so a green response here
means the connection genuinely works.

---

## 5. Map response fields to Outputs

In the test result, **click any field chip** (e.g. `data.id`, `data.title`) and
it's added to the action's **Outputs** as a named variable. Those names are what
downstream workflow nodes reference via the `@` picker.

Example: clicking `data.id` adds `{ key: "id", path: "data.id" }`, exposing an
`id` output.

Every action also always exposes the raw `response`, plus `success` and
`status`.

---

## 6. Test the Connection (authenticated apps)

If you set an **auth test endpoint** (step 3), save the app, create a Connection
with real credentials, and use **Test connection**. It calls the test endpoint
with the credentials and reports success/failure — the fastest way to confirm the
credentials + auth injection are correct.

---

## 7. Use it in a workflow (the real end-to-end test)

1. Create/open a workflow and add your Custom App as an **Action** node.
2. Pick the action, fill the fields (type `@` to insert data from earlier nodes).
3. Save, then **Run** the workflow (or trigger it normally).
4. Open **Logs** → the run → the node to see the real request/response and the
   mapped output values.

Downstream nodes can now reference your action's outputs (e.g. `{{ 3.id }}`).

---

## Worked example — no-auth public API

A complete, copy-pasteable app you can build in two minutes to confirm everything
works:

- **Details** → Name `JSON Placeholder`, Base URL `https://jsonplaceholder.typicode.com`, Kind `REST API`.
- **Auth** → `No auth`.
- **Action** `get_post`:
  - Input field: `post_id` (number)
  - Request: `GET /posts/{{ post_id }}`
  - Test with `post_id = 5` → expect **HTTP 200** and a body with `id`, `title`, `body`, `userId`.
  - Click `title` and `userId` to map them as outputs.

If the test returns 200 with the post JSON, your Custom App pipeline is working.

---

## Worked example — API-key API

- **Details** → Base URL `https://api.example.com/v1`.
- **Auth** → `API key`; field `api_key` (password); inject **Header** `Authorization` = `Bearer {{ creds.api_key }}`; test path `/me`.
- **Action** `create_thing`:
  - Fields: `title` (text), `amount` (number)
  - Request: `POST /things`, Body `{ "title": "{{ title }}", "amount": "{{ amount }}" }`
- Save → create a Connection with a real key → **Test connection** → then **Send
  test request** with sample values.

---

## Import / Export

Use **Export** on a Custom App to download its manifest as JSON, and **Import** to
load one on another site. (Credentials are never part of the manifest — only the
field *definitions* — so imported apps ask each site to enter their own.)

---

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| Test request shows `scheme not allowed` | URL isn't `http`/`https`, or the base URL is empty and the path is relative. |
| Test request refused by filter | A `zaplane_http_block_request` filter is blocking the host (e.g. private/localhost ranges). |
| `401` / `403` | Auth not injected correctly — check the inject **Name**/**template**, or the connection credentials. |
| Placeholders come through literally (`{{ post_id }}`) | The field key doesn't match the placeholder, or you didn't supply a test value. |
| Upstream data is missing from an action request | Reference it through the `input` namespace, for example `{{ input.id }}`. |
| Body sent as a string, API rejects it | Ensure the **Body (JSON)** is valid JSON; the builder shows a parse error if not. |
| Outputs empty downstream | Add explicit **Outputs** (or reference `response.<path>`); run/test the node so real values are captured. |

---

## Developer verification (WP-CLI)

For a scripted end-to-end check (e.g. in CI), a saved manifest can be exercised
directly. Save the manifest, then in a **fresh** process (so the integration
registry picks it up) resolve and run it:

```bash
# 1) Save the manifest
wp eval 'Zaplane\CustomApps\ManifestStore::save([
  "slug"=>"demo","name"=>"Demo","base_url"=>"https://jsonplaceholder.typicode.com",
  "auth"=>["type"=>"none"],
  "actions"=>[["key"=>"get","label"=>"Get","request"=>["method"=>"GET","path"=>"/posts/{{ id }}"],
    "output"=>[["key"=>"title","path"=>"title"]]]],
]);'

# 2) Fresh process: register + execute against the live API
wp eval '
$i = Zaplane::init()->container->get("integrations")->get("demo");
$out = $i::execute_node(["data"=>["app"=>"demo","event"=>"get","config"=>["id"=>7]]], []);
echo "status=".$out["data"]["status"]." title=".$out["data"]["title"]."\n";
Zaplane\CustomApps\ManifestStore::delete("demo");
'
```

Expected: `status=200 title=…`. This is exactly what the **Send test request**
button does under the hood.

> Note: save and execute must be in **separate** `wp eval` calls — the integration
> registry is cached per request, so a manifest saved mid-request isn't visible
> until the next one.

---

## How it works (under the hood)

- Manifests are stored as JSON in a single non-autoloaded option.
- One generic runtime class interprets each manifest and implements the standard
  integration contract (actions, triggers, auth, dynamic dropdowns).
- All outbound requests — actions, triggers, connection tests, and the **Send
  test request** preview — funnel through one SSRF-guarded HTTP client, so the
  `http`/`https` allowlist and `zaplane_http_block_request` filter always apply.
