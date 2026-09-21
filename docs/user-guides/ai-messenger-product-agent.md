# Setup Guide: AI Messenger Product Agent (WooCommerce/StoreEngine)

This guide walks you through setting up the **AI Messenger Product Agent** recipe — an AI assistant that replies to your Facebook Page's Messenger messages automatically, answers general questions from your Business Knowledge, and checks your store for the **real, current price and stock** before answering pricing questions.

No coding needed. Follow the steps in order.

---

## What this recipe does

When a customer messages your Facebook Page:

1. The AI reads their message.
2. For general questions (delivery time, return policy, etc.), it looks up your **Business Knowledge**.
3. For price/stock questions, it checks your **live store data** (WooCommerce or StoreEngine — whichever you have installed) instead of guessing or using outdated info.
4. It remembers the conversation, so it won't repeat itself or forget what the customer already said.
5. It replies automatically on Messenger.

This recipe automatically detects whether you use **WooCommerce** or **StoreEngine** as your store plugin and wires in the correct one for you — you'll see a yellow sticky note on the workflow explaining which one it picked and why.

---

## Requirements

Before you start, make sure you have:

- [ ] A **Facebook Page** you manage, with a connected **Meta App** (Meta for Developers account)
- [ ] **WooCommerce** or **StoreEngine** installed and active, with at least a few products published
- [ ] An API key for an AI provider (Anthropic, OpenAI, Google Gemini) — or you can use **WordPress Core AI** if your site has that configured
- [ ] Admin access to this WordPress site

---

## Step 1 — Connect your Facebook Page (Messenger)

Zaplane talks to Messenger through your own Meta App. This is a one-time setup per Facebook Page.

1. In your WordPress admin sidebar, go to **Zaplane → Connections**.
2. Click **Create credential** and choose **Facebook Messenger** from the app list.

   ![Connections page, Create credential button highlighted](screenshots/product-agent-01-connections-add.png)

3. Fill in:
   - **Page Access Token** — a Page access token from your Meta App, with the `pages_messaging` permission. (Meta for Developers → your App → Messenger → Generate Token.)
   - **API Version** — leave blank to use the default unless you know you need a specific one.

   ![Messenger connection form](screenshots/product-agent-02-messenger-credentials.png)

4. Save the connection, then open its **Webhook Setup** tab. You'll need to fill in and copy some values:
   - **Verify Token** — invent any string (or click Generate), save it here.
   - **App Secret** — from Meta App → Settings → Basic → App Secret.
   - Copy the **Callback URL** Zaplane shows you.

   ![Webhook setup tab with callback URL and verify token](screenshots/product-agent-03-webhook-setup.png)

5. In your Meta App dashboard: **Messenger → Settings → Webhooks**, paste the Callback URL and the same Verify Token, and subscribe to the `messages` field.

   ![Meta App webhook configuration screen](screenshots/product-agent-04-meta-webhook.png)

> **Why this matters:** Meta will refuse the webhook if the Verify Token doesn't match exactly on both sides — copy-paste it, don't retype it.

---

## Step 2 — Connect your AI provider

1. Go to **Zaplane → Connections**, click **Create credential**, choose **AI** from the app list.
2. Pick a **Provider**:
   - **WordPress Core AI** — no key needed if your site already has this set up.
   - **Anthropic / OpenAI / Google Gemini** — paste your API key for that provider.
   - **OpenAI-compatible** — for Azure, OpenRouter, Ollama, etc. (needs a Base URL too).

   ![AI connection form with provider dropdown](screenshots/product-agent-05-ai-connection.png)

3. Save the connection.

---

## Step 3 — Add your product/business info to Business Knowledge (recommended)

This is what the AI uses to answer general questions (delivery charges, return policy, FAQs). It's separate from the live price/stock lookup, which reads your store directly.

1. Go to **Zaplane → Business Knowledge**.
2. Use **Sync Content** to pull in your existing product pages, or **FAQ Builder** to add common questions and answers manually.
3. Note the **Business Key** you use here (e.g. `default`) — you'll need to match it in Step 5.

   ![Business Knowledge page with Sync Content drawer open](screenshots/product-agent-06-business-knowledge.png)

---

## Step 4 — Install the recipe

1. Go to **Zaplane → Recipes**.
2. Find **AI Messenger Product Agent (WooCommerce/StoreEngine)**.
3. Read its description — it will already tell you which store platform it detected on your site.
4. Click **Use Recipe** on its card.

   ![Recipes gallery with the product agent recipe card](screenshots/product-agent-07-recipes-gallery.png)

This creates a new workflow for you, already wired up with a trigger, the AI agent, its tools, and the reply step.

---

## Step 5 — Configure the new workflow

Open the workflow Zaplane just created. You'll see something like this:

![The created workflow on the canvas](screenshots/product-agent-08-workflow-overview.png)

Go through each node:

1. **Message Received** (trigger) — click it, and link it to the Messenger connection you made in Step 1.
2. **Store Platform Note** (yellow sticky note) — read it. It tells you which store platform was wired in and why. If it says it fell back to WooCommerce because it couldn't detect your store plugin, see [Troubleshooting](#troubleshooting) below.

   ![Sticky note explaining the detected store platform](screenshots/product-agent-09-sticky-note.png)

3. **Run Agent** — click it, open **Configure**, and review the instructions (you can customize the wording, but don't remove the parts that tell it to use the tools).
4. **Chat Model** (sub-node under Run Agent) — link it to the AI connection from Step 2, and pick a model.
5. **Memory** (sub-node) — no changes usually needed. **History from** is set to *The Inbox conversation*, so when the Inbox receives Messenger the agent sees the real thread, including replies your team typed in the Inbox. Without the Inbox it keeps its own thread per Messenger sender.
6. **Business Knowledge** (Tool sub-node) — set its **Business Key** to match what you used in Step 3.
7. **Get Product** (Tool sub-node) — this is already pointed at whichever store platform was detected. No changes needed unless the sticky note told you to swap it.
8. **Send Reply** (action) — link it to the same Messenger connection from Step 1.

   ![Run Agent configure panel with Chat Model, Memory, and Tools](screenshots/product-agent-10-run-agent-config.png)

9. Click **Update** (top right) to save.

---

## Step 6 — Test it

Before going live, test each piece:

1. Click each Tool node → **Test** tab → **Test Action**, with a sample product name, to confirm it returns real data.

   ![Test tab showing a successful product lookup](screenshots/product-agent-11-test-action.png)

2. Click **Test Flow Once** (top of the canvas) to simulate a full run.
3. Once everything looks right, message your actual Facebook Page from a personal account and confirm you get a real reply.

   ![A real Messenger conversation with the bot replying](screenshots/product-agent-12-live-test.png)

---

## Step 7 — Activate

Flip the workflow's status from **Draft** to **Active** (top right, next to Update). Only active workflows respond to real messages.

![Workflow status toggle set to Active](screenshots/product-agent-13-activate.png)

---

## Troubleshooting

**The bot isn't replying at all.**
- Check the workflow is **Active**, not Draft.
- Check the Messenger connection's webhook is subscribed to `messages` in your Meta App.
- Check **Zaplane → Logs** for errors on the run.

**The sticky note says it fell back to WooCommerce, but I use StoreEngine.**
- Make sure the StoreEngine plugin is **active** (not just installed) on this site.
- Re-open the Recipes gallery (this refreshes the detection) and re-install the recipe, or manually swap the **Get Product** tool node to StoreEngine's version.

**Product prices/stock look wrong or outdated.**
- Confirm the product is **Published** in WooCommerce/StoreEngine.
- Test the **Get Product** tool directly (Step 6.1) with the exact product name to see what it actually returns.

**The AI gives generic or wrong answers to policy/FAQ questions.**
- Make sure Business Knowledge has entries under the same **Business Key** the workflow's Business Knowledge tool is using.
- Try **Sync Content** again if you recently changed product pages or policies.

**"No AI Agent connection credentials available" or similar AI errors.**
- Re-check the API key in your AI connection (Step 2) — it may have expired or been revoked.
- If using WordPress Core AI, confirm it's configured under your site's own AI settings.

**I want to change the AI's tone or personality.**
- Open **Run Agent → Configure → Agent Instructions** and edit the text. This is a plain instruction to the AI — write it like you're briefing a new employee.
