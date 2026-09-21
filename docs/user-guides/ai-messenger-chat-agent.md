# Setup Guide: AI Messenger Chat Agent (Knowledge + Memory)

This guide walks you through setting up the **AI Messenger Chat Agent** recipe — an AI assistant that replies to your Facebook Page's Messenger messages automatically, using your own business info (FAQs, policies, product descriptions) to answer.

This is the right recipe if you **don't use WooCommerce or StoreEngine** — for example, if you sell purely through Facebook Page posts and take orders manually. If you do run a WooCommerce or StoreEngine store and want the AI to also check live price/stock, use the **AI Messenger Product Agent** recipe instead.

No coding needed. Follow the steps in order.

---

## What this recipe does

When a customer messages your Facebook Page:

1. The AI reads their message.
2. It looks up your **Business Knowledge** (FAQs, policies, product descriptions you've added) to find a relevant answer.
3. It remembers the conversation, so it won't repeat itself or forget what the customer already said.
4. It replies automatically on Messenger.

If nothing relevant is found, it says so honestly instead of guessing, and offers to connect the customer with a human.

---

## Requirements

Before you start, make sure you have:

- [ ] A **Facebook Page** you manage, with a connected **Meta App** (Meta for Developers account)
- [ ] An API key for an AI provider (Anthropic, OpenAI, Google Gemini) — or you can use **WordPress Core AI** if your site has that configured
- [ ] Admin access to this WordPress site

---

## Step 1 — Connect your Facebook Page (Messenger)

Zaplane talks to Messenger through your own Meta App. This is a one-time setup per Facebook Page.

1. In your WordPress admin sidebar, go to **Zaplane → Connections**.
2. Click **Create credential** and choose **Facebook Messenger** from the app list.

   ![Connections page, Create credential button highlighted](screenshots/chat-agent-01-connections-add.png)

3. Fill in:
   - **Page Access Token** — a Page access token from your Meta App, with the `pages_messaging` permission. (Meta for Developers → your App → Messenger → Generate Token.)
   - **API Version** — leave blank to use the default unless you know you need a specific one.

   ![Messenger connection form](screenshots/chat-agent-02-messenger-credentials.png)

4. Save the connection, then open its **Webhook Setup** tab. You'll need to fill in and copy some values:
   - **Verify Token** — invent any string (or click Generate), save it here.
   - **App Secret** — from Meta App → Settings → Basic → App Secret.
   - Copy the **Callback URL** Zaplane shows you.

   ![Webhook setup tab with callback URL and verify token](screenshots/chat-agent-03-webhook-setup.png)

5. In your Meta App dashboard: **Messenger → Settings → Webhooks**, paste the Callback URL and the same Verify Token, and subscribe to the `messages` field.

   ![Meta App webhook configuration screen](screenshots/chat-agent-04-meta-webhook.png)

> **Why this matters:** Meta will refuse the webhook if the Verify Token doesn't match exactly on both sides — copy-paste it, don't retype it.

---

## Step 2 — Connect your AI provider

1. Go to **Zaplane → Connections**, click **Create credential**, choose **AI** from the app list.
2. Pick a **Provider**:
   - **WordPress Core AI** — no key needed if your site already has this set up.
   - **Anthropic / OpenAI / Google Gemini** — paste your API key for that provider.
   - **OpenAI-compatible** — for Azure, OpenRouter, Ollama, etc. (needs a Base URL too).

   ![AI connection form with provider dropdown](screenshots/chat-agent-05-ai-connection.png)

3. Save the connection.

---

## Step 3 — Add your business info to Business Knowledge

This is what the AI answers from — there's no live store to fall back on in this recipe, so this step matters more here than in the Product Agent version.

1. Go to **Zaplane → Business Knowledge**.
2. Add your content one of three ways:
   - **Add Entry** — manually type in a single fact or product description.
   - **FAQ Builder** — add common question/answer pairs (delivery time, payment methods, return policy, etc.).
   - **Sync Content** — pull in existing WordPress posts/pages if you have product write-ups published there.
3. Note the **Business Key** you use here (e.g. `default`) — you'll need to match it in Step 5.

   ![Business Knowledge page with FAQ Builder open](screenshots/chat-agent-06-business-knowledge.png)

> **Tip:** the more specific and complete your FAQ entries, the fewer "I'm not sure" answers your customers will get. Think about what you get asked most often on Messenger today, and add exactly that.

---

## Step 4 — Install the recipe

1. Go to **Zaplane → Recipes**.
2. Find **AI Messenger Chat Agent (Knowledge + Memory)**.
3. Click **Use Recipe** on its card.

   ![Recipes gallery with the chat agent recipe card](screenshots/chat-agent-07-recipes-gallery.png)

This creates a new workflow for you, already wired up with a trigger, the AI agent, its Business Knowledge tool, and the reply step.

---

## Step 5 — Configure the new workflow

Open the workflow Zaplane just created. You'll see something like this:

![The created workflow on the canvas](screenshots/chat-agent-08-workflow-overview.png)

Go through each node:

1. **Message Received** (trigger) — click it, and link it to the Messenger connection you made in Step 1.
2. **Run Agent** — click it, open **Configure**, and review the instructions (you can customize the wording, but don't remove the part that tells it to check Business Knowledge before answering).
3. **Chat Model** (sub-node under Run Agent) — link it to the AI connection from Step 2, and pick a model.
4. **Memory** (sub-node) — no changes usually needed. **History from** is set to *The Inbox conversation*, so when the Inbox receives Messenger the agent sees the real thread, including replies your team typed in the Inbox. Without the Inbox it keeps its own thread per Messenger sender.
5. **Business Knowledge** (Tool sub-node) — set its **Business Key** to match what you used in Step 3.
6. **Send Reply** (action) — link it to the same Messenger connection from Step 1.

   ![Run Agent configure panel with Chat Model, Memory, and Business Knowledge tool](screenshots/chat-agent-09-run-agent-config.png)

7. Click **Update** (top right) to save.

---

## Step 6 — Test it

Before going live, test each piece:

1. Click the **Business Knowledge** Tool node → **Test** tab → **Test Action**, with a sample question, to confirm it returns something relevant.

   ![Test tab showing a Business Knowledge lookup result](screenshots/chat-agent-10-test-action.png)

2. Click **Test Flow Once** (top of the canvas) to simulate a full run.
3. Once everything looks right, message your actual Facebook Page from a personal account and confirm you get a real reply.

   ![A real Messenger conversation with the bot replying](screenshots/chat-agent-11-live-test.png)

---

## Step 7 — Activate

Flip the workflow's status from **Draft** to **Active** (top right, next to Update). Only active workflows respond to real messages.

![Workflow status toggle set to Active](screenshots/chat-agent-12-activate.png)

---

## Troubleshooting

**The bot isn't replying at all.**
- Check the workflow is **Active**, not Draft.
- Check the Messenger connection's webhook is subscribed to `messages` in your Meta App.
- Check **Zaplane → Logs** for errors on the run.

**The AI says "I'm not sure" too often.**
- Add more entries to Business Knowledge — this recipe only knows what's in there.
- Double-check the Business Key matches between Business Knowledge and the workflow's tool.
- Try rephrasing your FAQ entries closer to how customers actually ask (short, direct wording usually retrieves better than formal wording).

**The bot forgets earlier parts of the conversation.**
- Check the **Memory** sub-node is still connected to Run Agent's Memory handle.
- If a customer contacts you again after a long gap, that's expected — conversation memory has a limit (10 turns by default) so very old messages naturally drop off.

**"No AI Agent connection credentials available" or similar AI errors.**
- Re-check the API key in your AI connection (Step 2) — it may have expired or been revoked.
- If using WordPress Core AI, confirm it's configured under your site's own AI settings.

**I want to change the AI's tone or personality.**
- Open **Run Agent → Configure → Agent Instructions** and edit the text. This is a plain instruction to the AI — write it like you're briefing a new employee.

**I actually do have WooCommerce or StoreEngine and want live price/stock answers too.**
- Delete this workflow (or keep it as a backup) and install the **AI Messenger Product Agent (WooCommerce/StoreEngine)** recipe instead — it does everything this one does, plus live product lookups.
