# MCP server

Zaplane can present itself to an AI client — Claude, Cursor, or anything else
that speaks the [Model Context Protocol](https://modelcontextprotocol.io) — over
a single JSON-RPC endpoint. The client can then read what this site automates,
**build new workflows from a description**, and diagnose failed runs.

Zaplane is also an MCP *client* (the **MCP Client** tool node, and the AI Agent's
`mcp_server_url` field). This document is about the server side.

## Turning it on

**Zaplane → Settings → AI access.** Off by default; nothing is reachable until a
site owner enables it *and* issues a token.

1. Toggle **Enable the MCP endpoint** and press **Save changes**.
2. Under **Issue a token**, name the client, pick its scopes, press **Issue token**.
3. Copy the token — it is shown once and is not recoverable.

The endpoint is:

```
POST <site>/wp-json/zaplane/v1/mcp
Authorization: Bearer <token>
```

Protocol revision `2025-06-18`, Streamable HTTP in single-JSON-response mode.
`initialize`, `ping`, `tools/list` and `tools/call` are implemented, including
batched requests and notification acks. There is no SSE channel and no session
id — the server is stateless.

## Scopes

A token carries scopes, and `tools/list` only advertises what that token may
call, so a client is never shown a tool it would be refused.

| Scope   | Grants |
|---------|--------|
| `read`  | Browse apps, workflows, runs, connections, knowledge. |
| `write` | Create and edit workflows, sync knowledge. Implies `read`. |
| `run`   | Start workflows for real. |

`run` is never granted by default. Everything else is reversible and
inspectable; running a workflow sends the mail, takes the payment and posts to
the other services, so it is opted into deliberately.

Tokens are stored as a SHA-256 of the secret, so a database read cannot be
replayed against the endpoint. Each is revocable on its own and records when it
was last used.

## Tools

**Discovery** — `search_capabilities`, `list_apps`, `describe_app`

**Authoring** — `validate_graph`, `create_workflow`, `update_workflow`,
`set_workflow_status`, `create_workflow_from_recipe`

**Inspection** — `list_workflows`, `get_workflow`, `list_recipes`, `list_runs`,
`get_run`, `list_connections`

**Knowledge** — `search_knowledge`, `list_businesses`, `sync_content`

**Execution** — `run_workflow`

### Building a workflow

There are hundreds of triggers and actions across every installed app, so the
intended path is narrow-then-read:

1. `search_capabilities` with a plain-language description → a ranked shortlist.
2. `describe_app` on a slug from that list → every field, its type, whether it is
   required, and the allowed values for fixed selects.
3. `validate_graph` on the draft → errors before anything is written.
4. `create_workflow` → a draft on the canvas.

A node only needs its intent:

```json
{
  "title": "Notify on completed order",
  "graph": {
    "nodes": [
      { "type": "trigger", "data": { "app": "woocommerce", "event": "order_status_completed" } },
      { "type": "action",  "data": { "app": "slack", "event": "send_message",
        "config": { "channel": "orders", "text": "Order {{1.order_id}} completed" } } }
    ]
  }
}
```

Node ids, canvas positions, labels, icons, the trigger's WordPress hook and
straight-line edges are all filled in from the integration manifest. Supply
`edges` yourself only for branching (Router, Condition) or to wire an AI Agent's
`ai_tool` / `ai_memory` / `ai_model` handles.

**Node ids must be numeric strings** (`"1"`, `"2"`) if you supply them. The run
engine reads them back as integers, so an id like `"send_email"` saves and
renders but collapses every node onto key 0 at run time. Omit them and they are
assigned for you.

New workflows are created as **drafts**, and apps needing a connection are left
unlinked — the site owner picks the account in the editor. Use
`list_connections` to find an id, or `set_workflow_status` to go live once it
is wired up (which re-validates and refuses a graph that would not run).

## Behaviour worth knowing

**Header stripping.** Apache under CGI/FastCGI drops `Authorization` before PHP
sees it. The server falls back to `HTTP_AUTHORIZATION` and
`REDIRECT_HTTP_AUTHORIZATION`, so this does not present as unexplained 401s.

**Self-reference.** A workflow whose AI Agent points at this same site would be
able to start the workflow that is calling it. The MCP client sends
`X-Zaplane-Origin`; when the server sees its own address there it refuses
`run_workflow` while still allowing reads.

**Rate limit.** 120 calls per token per minute; over that the endpoint returns
429 with `Retry-After` until the window rolls.

**POST only.** The endpoint answers 405 with `Allow: POST` to `GET` and `DELETE`.
Streamable HTTP reserves those for a server-initiated event stream and for ending
a session, and this server offers neither — so opening the URL in a browser shows
a 405, not a working page and not a missing route.

**Tool failures** come back as `isError` results with a readable message rather
than JSON-RPC protocol errors, so the model can correct itself. Only an unknown
method or unknown tool is a protocol error.

## Authentication

Three ways in, for three kinds of client.

Both are described on the **Settings → AI access** panel itself, which also warns
when the site's address cannot be reached from the internet — a hosted connector
resolves it from its own servers, so a development hostname never arrives and all
it can report is that it could not sign in.

The AI access panel carries the steps for each client, so the rest of this
section is background rather than something to follow by hand.

**A token you paste.** Issue one under **Settings → AI access** and give it to a
client you run yourself — Claude Code, Cursor, a script. It is presented as
`Authorization: Bearer <token>`, never expires, and is revoked from that same
screen.

A connector dialog usually asks for the header in two fields. The name is
`Authorization` and the value is `Bearer <token>` — **the scheme word is part of
the value**, and pasting the token alone is refused with a 401. In a dialog that
also offers an authentication mode, choose the one meaning "no sign-in / uses an
API key": a static token is not OAuth, and selecting an OAuth mode alongside it
will send the connector looking for a sign-in flow it does not need.

**A WordPress application password.** Issue one at Users → Profile → Application
Passwords and send it as Basic auth — `Authorization: Basic base64(user:password)`.
Core authenticates it before Zaplane sees the request, so there is no Zaplane
token to issue or lose, and it is revoked from the same screen it was made on.

Capped at `read` and `write`. An application password is the whole user, with no
way to withhold a single capability, so `run` is never granted through one; that
scope needs an issued token or an approved OAuth grant. Note also that a site can
switch application passwords off entirely (`wp_is_application_passwords_available`),
and some security plugins do.

**OAuth, for clients you don't run.** A hosted connector — claude.ai, ChatGPT —
has nowhere for you to paste a token, so it expects to find an authorization
server and ask for one. Paste the endpoint URL into the connector and it will
walk the rest itself:

```
https://example.com/wp-json/zaplane/v1/mcp
```

What happens behind that: the connector calls the endpoint, gets a 401 carrying
`WWW-Authenticate: Bearer realm="Zaplane MCP", resource_metadata="…"`, follows
that pointer to `/.well-known/oauth-protected-resource` (RFC 9728), reads
`/.well-known/oauth-authorization-server` (RFC 8414) to find the endpoints,
registers itself (RFC 7591), and sends you to a consent screen. **You must be
signed in as an administrator to approve it.** The token it receives acts as the
account that approved it.

| | |
|---|---|
| Consent | `https://example.com/zaplane-oauth/authorize` |
| Register | `POST /wp-json/zaplane/v1/oauth/register` |
| Token | `POST /wp-json/zaplane/v1/oauth/token` |
| Revoke | `POST /wp-json/zaplane/v1/oauth/revoke` |

PKCE with `S256` is required — `plain` is not accepted. Authorization codes last
a minute and are spent once, whether or not the exchange succeeds. Access tokens
last an hour; the refresh token that comes with one is rotated on use, and the
pair it replaces stops working immediately. Every client is public: there are no
client secrets to store or leak.

Approved connectors appear in **Settings → AI access** alongside hand-issued
tokens, and revoking one there disconnects it.

If the site's MCP module is off, all of this 404s — the site does not advertise
an authorization server it isn't running.

### Identifying by URL instead of registering

A client may use an https URL as its `client_id`, serving a JSON document that
describes itself. Zaplane fetches and validates that document instead of keeping
a registration — which is what the connector dialogs recommend, since dynamic
registration otherwise leaves a row for every client that ever connects.

The document must name itself: its `client_id` must equal the URL it was fetched
from, or it is refused. Beyond that: https with a path and no fragment, a 200
answer, no redirects followed, at most 5KB read, and at least one usable
`redirect_uri`. Private and reserved addresses are refused before the request is
made — the URL is chosen by a stranger and this server makes the call, which is
the one place this feature could become a way to reach things only the site can
reach.

### www, and why it used to fail

A client compares the `resource` in the discovery document against the address
you gave it, and requires the two to have the same origin. If it does not match
it stops there — before registering — and reports that it could not register with
your sign-in service.

So the documents are built from the host the request arrived on, not from
`home_url()`, whenever that host is this site under another name (a `www.` prefix
either way). Paste `https://www.example.com/...` and the documents say
`www.example.com`; paste the bare domain and they say the bare domain. Any other
Host is ignored — reflecting one would let a stranger publish a document naming
an authorization server of their own.

The consent screen is the exception: it stays on the site's canonical host,
because that is where the login cookie lives, where `wp_login_url()` points, and
the only host `wp_safe_redirect()` will return to. A consent page on the alias
would ask for a login and then be unable to find its way back.

Nothing here replaces the token flow, and a client that can set a header does not
need OAuth. If you would rather keep a static token with a hosted client, a
bridge still works:

```bash
npx mcp-remote https://example.com/wp-json/zaplane/v1/mcp --header "Authorization: Bearer <token>"
```

## Letting someone else connect

A person who cannot manage the site can still connect their own AI client. They
start it as usual; the consent screen tells them an administrator has been asked,
and waits. The administrator sees a count on the Zaplane menu and the request on
**Settings → AI access**, with who asked and a checkbox per scope. Once they
decide, the waiting page carries on by itself.

The token acts as **the requester**, not the approver — allowing a colleague's
client must not hand them authority they do not have — and carries only the
scopes that were ticked, never more than the client asked for. Requests expire
after 15 minutes.

Only signed-in users can leave a request, so the queue cannot be filled from
outside. Nothing here changes the protocol: to the client this is the ordinary
authorization-code redirect, taking longer than usual. That is deliberate — no
MCP client implements the grant designed for approving elsewhere (RFC 8628), so
anything needing their cooperation would never be used.

## Registered clients

Apps that register themselves appear under **Settings → AI access → Registered
clients**, marked *connected* or *registered, never approved*. Registering grants
nothing on its own; approving one does.

Removing a client revokes the tokens issued through it, and it will have to
register and be approved again. Worth knowing before you tidy: an already
connected client keeps pointing at its registration, and deleting that breaks it
with an "unknown client" error that gives no clue why.

## Limiting what `run` can start

`run` is the scope that sends mail and takes payments, and holding it means
holding it over every workflow — unless the token says otherwise. When issuing
one, tick **Run** and a list of workflows appears; the token may then start those
and no others.

Choosing none means every workflow, now and in future, which is what tokens
issued before this have and what "any" still means. A refused call names the
workflows the token does cover, and the refusal is recorded in the log.

The restriction lives on the token, so revoking or reissuing is how you change
it. The same form sets a lifetime — 30 days, 90, a year, or never. OAuth tokens
expire and rotate on their own; one pasted into a config only stops when you say
so.

## What clients have been doing

**Zaplane → Logs → AI access.** Every tool call is recorded: the client, the
account it acted as, the tool, the outcome, and how long it took. Refusals are
kept alongside successes — a client repeatedly reaching for a scope it was never
granted is worth seeing, and it is invisible if only successes are logged.

Arguments are deliberately not recorded. A call carries whatever the model was
working with, and a table of that which nobody remembers to prune is a second
copy of your data with none of the care. Who, what and how it went is what the
log is for.

The trail is capped at 2000 rows and trimmed as new ones arrive. Clearing it is
a button on that screen.

## When a client will not connect

**Settings → AI access → Run check.** A client can only tell you it failed. This
runs the same checks from the site, where the cause is visible:

| Check | What a failure means |
|---|---|
| Module is on | Everything is refused before the credential is read, so any client reports a sign-in failure |
| Endpoint refuses correctly | Anything but 401 means a security plugin, firewall or cache answered before WordPress did |
| Refusal carries a discovery pointer | Without it a hosted connector concludes the server has no OAuth; something is stripping response headers |
| Discovery documents are served | Some hosts serve `/.well-known/` from disk, shadowing them; static caches can too |
| Address is reachable from the internet | A development hostname never arrives at claude.ai or ChatGPT, whose servers resolve it from outside |
| Application passwords available | A security plugin has disabled them; use a token or OAuth instead |

Two things that look like faults and are not: opening the endpoint URL in a
browser returns **405**, because it is POST-only; and a `401` with a
`WWW-Authenticate` header is the correct answer to an unauthenticated call, not a
broken credential.

## Pre-existing tokens

A token issued before scopes existed (the single `zaplane_mcp_token` option) is
still accepted and is treated as holding every scope, so an already-connected
client keeps working. Replace it with a scoped token when convenient.
