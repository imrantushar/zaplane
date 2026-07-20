# Changelog

All notable changes to Zaplane are documented here. This project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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
