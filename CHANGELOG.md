# Changelog

All notable changes to Zaplane are documented here. This project adheres to
[Semantic Versioning](https://semver.org/).

## [1.2.0] - 2026-09-08

The dashboard is rebuilt, the workflow canvas is retyped, modules become opt-in
and discoverable, and Zaplane gains an MCP server. Integration coverage grows from
**998 to 1,070** triggers and actions across **93** apps and tools.

### Added — Integrations
- **aBlocks** — a new app with a **Form Submitted** trigger for aBlocks
  form-builder forms and an optional per-form filter. Requires the companion
  `ablocks/form_builder/after_submission` hook shipped in the aBlocks plugin.
- **FluentCart** — a large expansion: **21 new triggers and 52 new actions**,
  covering carts (items added, removed, updated, completed), coupons, licences,
  customers, orders, products and subscriptions.

### Added — MCP server
- Zaplane now presents itself to AI clients (Claude, Cursor, anything speaking the
  Model Context Protocol) at `POST /wp-json/zaplane/v1/mcp`, with a **Settings →
  AI access** panel to switch it on and manage tokens. Off by default.
- **Workflow authoring over MCP** — `search_capabilities` narrows hundreds of
  triggers and actions to a shortlist from a plain-language description,
  `describe_app` returns exact field schemas, `validate_graph` checks a draft, and
  `create_workflow` turns intent into a real draft on the canvas. Node ids,
  positions, labels, icons, trigger hooks and edges are filled in automatically.
- **Scoped, revocable tokens** — `read`, `write` and `run` are separate, `run` is
  never granted by default, secrets are stored hashed, and `tools/list` only
  advertises what the presenting token can call.
- Tools for diagnosing runs (`list_runs`, `get_run`), reading and editing existing
  workflows, and listing connections.
- **OAuth 2.1, so hosted AI clients can connect at all.** A connector run by
  someone else — claude.ai, ChatGPT — is never handed a token, because there is
  nowhere for a person to paste one; it expects to discover an authorization
  server and ask for its own. Zaplane now is one: the two `/.well-known/`
  discovery documents (RFC 9728 and RFC 8414), dynamic client registration
  (RFC 7591), and the authorization-code flow with PKCE. The 401 from the MCP
  endpoint carries the `resource_metadata` pointer that starts it off.
  Connecting still ends at a consent screen only an administrator can approve,
  and the token it issues acts as that administrator, appears in **Settings → AI
  access** like any other, and can be revoked there. Access tokens last an hour
  and are refreshed; a spent refresh token is retired with the access token it
  replaced.
- The discovery documents describe the site under **the host the request arrived
  on**, when that host is this site under another name — only a `www.` apart. A
  client checks that the resource it was told about has the same origin as the
  address you typed, and abandons the whole flow when it does not; a site whose
  `home_url()` is bare that answers a `www.` request with bare URLs is, to that
  client, a different server. Any other Host is ignored, so nobody can make the
  site advertise an authorization server of their choosing.
- The AI access panel now says **how to connect**: the three steps a hosted
  connector takes, the two for a client you run yourself, and a copyable command
  for the latter. It also warns when the site's address is not reachable from the
  internet — a development hostname cannot be resolved by claude.ai or ChatGPT,
  whose servers look it up from outside, and all they can report is that they
  could not sign in.
- The MCP endpoint answers **405** to `GET` and `DELETE`, with `Allow: POST`.
  Streamable HTTP reserves those verbs for an event stream and for ending a
  session, neither of which this server offers; the 404 they produced before
  reads as "no such endpoint", which is how opening the URL in a browser came to
  look like a broken route.

### Added — Modules and discovery
- **MCP is a module.** Optional features are now declared once in a registry that
  the settings defaults, the sanitizer, the admin-menu filter and the Modules
  screen all read from — the dashboard no longer keeps a second hardcoded copy in
  JavaScript. A module with its own settings panel gets a **Configure** link.
- **Feature teasers** — a dismissible card on the screen where an unused module
  would have helped (AI access on Workflows, Custom Apps on Connections, Business
  Knowledge on the Dashboard), so an existing user finds out a feature exists
  without reading a changelog. Relevance is a predicate rather than an on/off
  check, since a module can be enabled and still unused, and a teaser retires
  itself once the thing it suggests has happened. Dismissals are per user, and
  new teasers can be registered through the `zaplane/feature_teasers` filter.

### Changed — Integrations
- **SureCart** — the coupon, customer, order, product and subscription actions and
  their shared helper were substantially reworked (~1,000 lines) for consistent
  field handling and error reporting. No triggers or actions were added or
  removed, so existing workflows keep working.
- **Dokan** — the `withdraw_request_pending` trigger was removed; withdrawal
  events are covered by the remaining withdraw triggers.
- **FluentCart** — `order_paid_done` was removed in favour of the new order
  triggers above. **A workflow still using it will need its trigger re-selected.**
- StoreEngine licensing SDK updated to 1.5.6.

### Changed — Dark theme
- **Secondary text failed contrast.** `--zaplane-text-muted` sat at 3.70:1 and
  `--zaplane-placeholder` at 3.54:1 against the card, both under the 4.5:1 minimum
  — which is why secondary text read as washed out. They now measure 5.66:1 and
  4.85:1. Primary text comes back off near-white (14.06:1 → 13.49:1), which stops
  it halating on a ground this dark, and the border rises from 1.35:1 to 1.48:1 so
  it can do the separation work it is asked to do.
- **A white button label was unreadable on the accent.** No single blue works for
  both jobs: bright enough to read as an accent on the dark ground is too light
  under a white label, and dark enough for the label is too dim as an accent. The
  solid fill is now its own token, `--zaplane-primary-strong`, giving 5.17:1 under
  white where the accent gave 3.20:1. Light mode already cleared the bar, so it
  keeps the brand value.
- **The dashboard chart ignored the theme entirely.** Gridlines were a hardcoded
  `#EDF2F7`, which glared white on the dark ground; axis labels, the tooltip and
  the area line were likewise fixed light-mode values, and the line was a blue
  that was not even the brand. All of it now follows the palette, along with the
  status dots in the empty state.

### Changed — Workflow canvas
- **The node card is a surface again.** It used to fill with
  `--zaplane-secondary-color`, which is the same value the canvas paints, so a node
  was visible only because of its border — which is why that border had to be so
  heavy. A new `--zaplane-canvas` token separates the two, cards fill with the
  surface colour, and the border drops to a hairline.
- **Nodes are typed by colour.** Trigger, action, tool and AI each get a token
  (`--zaplane-cat-*`, with a dark-mode pair), applied to a header strip, the icon
  tile, the handles, and the connector leaving the node — so a glance shows what
  feeds what without reading a label. The floating label above each card is gone;
  it said the same thing while colliding with whatever was laid out above.
- **An empty node looks like a filled one.** A node that had not been given an app
  yet was a different component: no category strip, a plain label floating above
  the card, a single line of text and a shorter box. It now uses the same strip,
  the same two-line body and the same geometry — an empty node is still a trigger
  or an action, which is the one thing worth knowing before it is filled in — with
  a dashed tile and a "Choose a trigger" hint marking it as unset. The floating
  label is gone in both states.
- **One stroke per connector.** Edges drew a soft base path plus a dashed overlay
  at near-full opacity on a permanent loop, with an arrow landing on top of the
  target handle. Now a single stroke in the source node's hue, with the arrow
  inset to clear the handle. The moving dashes are kept for edges marked as
  running, so motion means data is moving through right now — nothing sets that
  flag yet; wiring live run state to the canvas is still to do.
- **The remove control on a node is a real button.** The click handler used to sit
  on the trash glyph rather than the control, so the padding around it did
  nothing; it was a `div` with no label, no keyboard access and no focus state;
  and it filled with `--zaplane-border-color` — a border value used as a fill,
  which is why it read as a muddy grey square. It is now a labelled button on its
  own surface that reddens on hover, says "Reset trigger" on a trigger and "Delete
  step" elsewhere, and reveals itself on focus so it can be reached without a
  mouse. A dead copy button that could never render was removed with it.
- **Every canvas colour now comes from the palette.** Thirteen hardcoded values
  are gone: `indigo-400`/`indigo-500` on hover, `#a855f7`/`#C4B5FD`/`#7C3AED` on
  the AI sub-node ports, a white chip that showed as a light box in dark mode, and
  the Yes/No branch labels, which now use the success and danger tokens so a
  semantic colour still reads over a coloured edge.

### Changed — Modules are now opt-in
- Every module ships switched **off**, including Custom Apps and Business
  Knowledge, which previously defaulted on. Existing sites keep whatever they had:
  a one-time migration pins any module without an explicit saved value to what it
  used to resolve to, so nothing disappears on update. Fresh installs get the
  opt-in defaults.
- **One card listing every module you have not switched on**, on Workflows, the
  Dashboard and Connections, each row with a one-click switch and the module most
  relevant to that screen leading and marked. Dismissing records what was on offer
  at the time, so a module added in a later release surfaces on its own instead of
  being buried — and without dragging back the ones already waved away.
- **A prompt in the builder.** No integration is hidden when its module is off —
  that would break workflows already using it — so picking such a node now shows a
  card saying which module is not enabled, with a one-click switch to turn it on
  without leaving the half-built workflow.

### Performance
- **The shipped dashboard bundle drops from 23.8 MB to 1.6 MB.** `next-release`
  had an unminified development build committed to `assets/build/app.js`; this is
  a production build, and it is what every admin page load was downloading.

### Fixed — Shipped catalogue
- **The integration catalogue described the machine that built it.** Fillout,
  Jotform and Typeform show a webhook URL to copy into the provider; built from
  `rest_url()` and frozen into `assets/json/integrations.json`, the shipped file
  carried `http://kodezen.local/...`, so every site was told to paste a
  developer's local address into its form. The REST root is now a placeholder the
  site substitutes at render time, exactly as `webhook_route` already was.
- StoreEngine's subscription, membership, affiliate and multi-vendor capabilities
  are marked `disabled` by checking whether that addon is active. That answer
  belongs to each site, but it was being frozen at build time — so the warning was
  hidden from sites without the addon and shown forever to sites with it.
  `requires_addon` still ships; the live check no longer does.

### Fixed — Integrations
- OAuth callbacks no longer surface a raw failure when a provider errors midway —
  the exception is caught and reported back to the connection window.
- **aBlocks** form triggers no longer serve stale cached form data.
- **Dokan** trigger registration, and **Paymattic** and **Slack** action fixes.

### Fixed — Editor and dashboard
- Required fields are now marked with an asterisk in node configuration.
- Log history gains a tab filter; the log table, JSON test-output view and
  run-details UI were corrected.
- Fullscreen mode on the workflow canvas.
- Custom app checkboxes, multi-app selection, and a Redux state issue that could
  leave a stale selection behind.
- Editor colour and stylesheet issues, including in dark mode, and a select
  control that was unreadable on the dark ground.

### Fixed — Settings
- The **Run** scope pill had no background tint: Tailwind's `/opacity` syntax
  needs a real colour to work on, and these are CSS variables. Uses `color-mix`,
  like the teaser components already did.
- **Save changes** is hidden when there is nothing to save, instead of showing a
  greyed-out button. A dead button reads as a save that failed, particularly on
  panels like AI access where nothing belongs to the settings form in the first
  place.
- The Appearance screen kept a hand-copied duplicate of both palettes in
  JavaScript, which had already drifted from the PHP: six tokens never appeared in
  the editor at all, and "Reset to default" wrote stale colours. Rows and defaults
  now come from the server over `GET /zaplane/v1/palette`.

### Fixed — Modules
- The discovery card no longer pairs a neutral grey border with a blue-tinted
  background, which read as muddy. Surface, border, accent and rules are now all
  derived from the primary through `color-mix`, so the card is one colour at
  different strengths and follows the palette into dark mode.
- A partial settings save no longer resets modules it did not mention. Saves were
  based on the defaults rather than on what was in effect, which was harmless
  while modules defaulted on and would have silently switched them off now that
  they do not.
- Activating a module from a teaser reported failure while actually succeeding:
  the success handler wrote to the frozen `ZaplaneGlobal.settings` snapshot, which
  throws under the bundle's strict mode, and a broad `catch` treated that as a
  failed request.

### Fixed — MCP server
- **A JSON-RPC batch came back with an extra response.** The token was stashed on
  the request with `set_param()`, which writes into the decoded JSON body — and
  when that body is an array, as a batch is, the stash became an element of it. The
  server answered a message the client never sent, echoing the token's id back as
  that response's id. It is held on the controller instead.
- **Hitting the rate limit reported `401 Sorry, you are not allowed to do that`.**
  A client reads that as a rejected credential and stops retrying, or asks to
  re-authorise. Throttling now answers `429` with `Retry-After` and says how long
  to wait.
- **The limit was half what it claimed.** WordPress calls a route's
  `permission_callback` twice per HTTP request — once to authorise, then again from
  `rest_send_allow_header()` — so the counter charged two per call and 120/min
  behaved as 60/min. The check is memoized per request.
- A `401` now carries `WWW-Authenticate: Bearer`, so a client can tell how it was
  meant to authenticate instead of seeing an opaque refusal.
- **Activating a workflow no longer succeeds when a step has no connection.** A
  missing connection is advisory while the graph is a draft — the author links the
  account afterwards — but going live with one means the workflow fires and
  silently does nothing. Findings now carry a code so activation can block on that
  one without matching message text.
- The `Authorization` header is now also read from `HTTP_AUTHORIZATION` /
  `REDIRECT_HTTP_AUTHORIZATION`, so Apache under CGI no longer causes silent 401s.
- Workflows created over MCP are attributed to the token's owner instead of being
  saved with no user.
- A workflow calling back into its own site's MCP endpoint can no longer start
  another run, which could re-enter the workflow that made the call.

## [1.1.0] - 2026-07-13

A major feature release that turns Zaplane into a full automation platform: an AI
Agent with tool-calling, a suite of first-class integrations and utility tools,
Custom Apps, and a dedicated bidirectional Webhook app.

### Added — Integrations
- **StoreEngine** — order, subscription and product triggers plus actions.
- **AI (Chat Model)** — generate replies with Anthropic, OpenAI, or WordPress Core AI.
- **AI Agent** — autonomous, tool-calling agent with **Chat Model**, **Memory** and **Tools** sub-nodes wired on the canvas, plus **MCP server** support.
- **Conversation Memory** — durable, keyed chat history for AI conversations.
- **Business Knowledge** — a searchable knowledge base the AI Agent can query; sync entries from StoreEngine products.
- **Custom Apps** — build your own integrations (external REST APIs or same-site WordPress hooks/callables) entirely from the UI, no core files touched.
- **Webhook** — one app for both directions: **Catch Webhook** (incoming trigger) and **Send Webhook** (outgoing action) with optional **HMAC-SHA256** payload signing and a structured Body-fields builder.
- **Messenger** and **WhatsApp** — AI auto-reply flows.
- **Academy** — course enrolment triggers/actions and access-group membership.

### Added — Tools & flow control
- **Manual Trigger**, **Schedule** trigger, and **Human-in-the-Loop** approval.
- **Router** (multi-branch), **Repeater/Iterator**, **Condition** and **Filter**.
- Utility tools: **CSV**, **XML**, **JSON Parser**, **Formatter**, **Date/Time**, **Image Helper**, **Set Variable**, and **Sticky Note**.

### Added — Recipe
- **AI Reply to Incoming Webhook** — receive a question by webhook, answer it with the AI Agent (searching Business Knowledge), then post the reply back out.

### Improved
- Dynamic-data **"@" token picker** across expression fields, with sample outputs so upstream tokens are available before a test run.
- Redesigned Business Knowledge, field, checkbox and connection-icon UI, with icon fallbacks.
- Clearer node port / branch handles, and AI Agent sub-node handles, on the canvas.

### Fixed
- AI and AI Agent no longer appear as trigger options where only actions apply.
- Custom Apps: trigger firing, empty-label handling, and the stale generic "Custom App" entry leaking into the app picker.
- Webhook: structured **Body fields** so multi-line/quoted values (e.g. an AI reply) can no longer produce malformed JSON.
- AI Agent sub-node connection-indicator position and the Tools-handle node-type filter.
- Set Variable test output, node port / multiple-output routing, route indicators, CSV parsing, and dynamic-data resolution.

## [1.0.2]
- Improvements and bug fixes.

## [1.0.0]
- Initial release.
