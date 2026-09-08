# Changelog

All notable changes to Zaplane are documented here. This project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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
- **One stroke per connector.** Edges drew a soft base path plus a dashed overlay
  at near-full opacity on a permanent loop, with an arrow landing on top of the
  target handle. Now a single stroke in the source node's hue, with the arrow
  inset to clear the handle. The moving dashes are kept for edges marked as
  running, so motion means data is moving through right now — nothing sets that
  flag yet; wiring live run state to the canvas is still to do.
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
- The `Authorization` header is now also read from `HTTP_AUTHORIZATION` /
  `REDIRECT_HTTP_AUTHORIZATION`, so Apache under CGI no longer causes silent 401s.
- Workflows created over MCP are attributed to the token's owner instead of being
  saved with no user.
- A workflow calling back into its own site's MCP endpoint can no longer start
  another run, which could re-enter the workflow that made the call.

### Added — Integrations
- **ABlocks** — a **Form Submitted** trigger for aBlocks form-builder forms, with an optional per-form filter. Requires a companion `ablocks/form_builder/after_submission` action hook shipped in the aBlocks plugin.

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
