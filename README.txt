=== Zaplane ===
Contributors: kodezen
Tags: automation, workflow, woocommerce, marketing automation, crm
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Visual, no-code automation for WordPress. Connect triggers and actions across WooCommerce, CRMs, forms, email and 80+ apps.

== Description ==

**Zaplane** is a no-code automation builder that runs inside your WordPress site. Connect a **trigger** (a WooCommerce order completing, a form being submitted) to one or more **actions** (send an email, add a contact to your CRM, post a Slack message), with delays, conditions, filters and loops in between.

Build workflows visually on a drag-and-drop canvas — no code required — and let them run reliably in the background.

= Why Zaplane? =

* **No-code visual builder** — design automations by connecting trigger and action nodes on a flow canvas.
* **Runs on your site** — workflows, run history and credentials stay in your own WordPress database, with no per-task pricing. Data reaches a third-party service only when a workflow or connection you set up uses it (see External services).
* **80+ integrations** — connect the WordPress plugins and external apps you already use.
* **Flow control** — delay, condition, filter, iterator (loop), variable and raw HTTP/webhook steps.
* **Reliable background processing** — long-running and scheduled steps run on Action Scheduler.
* **Ready-made recipes** — templates for abandoned carts, birthday messages, win-backs, upsells and feedback requests.
* **Optional AI steps** — write replies, run a tool-calling agent or search your own knowledge base, through the AI Client built into WordPress 7.0 and later, or your own OpenAI, Anthropic or Gemini key.
* **Optional MCP server** — off by default. Turn it on and an AI client you approve can read your workflows and build new ones, through scoped and revocable access.

= Integrations =

* **eCommerce:** WooCommerce, Easy Digital Downloads, SureCart, FluentCart, StoreEngine, Dokan, FunnelKit, WPFunnels
* **CRM & marketing:** FluentCRM, Groundhogg, GemCRM, Mailchimp, Brevo, ActiveCampaign
* **Forms:** Fluent Forms, Gravity Forms, WPForms, Contact Form 7, Formidable, Ninja Forms, SureForms, Jotform, Typeform
* **LMS & membership:** LearnDash, Tutor LMS, LifterLMS, MasterStudy, Academy, MemberPress, Paid Memberships Pro, BuddyBoss
* **Messaging & email:** Slack, Discord, Telegram, WhatsApp, Messenger, Gmail, Zoom, Google Meet
* **Page builders:** Elementor, Divi, Beaver Builder, Kadence Blocks, Essential Blocks, Spectra
* **Content & data:** Advanced Custom Fields, JetEngine, Meta Box, The Events Calendar, Trello, HTTP / Webhooks

Each service needs your own account with that provider. See **External services** for what Zaplane sends to each one, and when.

== Installation ==

1. Upload the `zaplane` folder to the `/wp-content/plugins/` directory, or install the plugin through the **Plugins > Add New** screen in WordPress.
2. Activate the plugin through the **Plugins** screen.
3. Open the **Zaplane** menu in your WordPress admin.
4. Create a new automation, pick a trigger, add your actions, and turn it on.

= Requirements =

* WordPress 6.8 or higher
* PHP 7.4 or higher

== Frequently Asked Questions ==

= Do I need a Zaplane account or any external service to use this? =

No. Zaplane needs no account with us and does not send your site's data to us. You only need an account with a third-party service (for example Slack or Mailchimp) if you build a workflow that uses that service.

= Does Zaplane require WooCommerce? =

No. WooCommerce is optional. Zaplane works with WordPress core events and with whichever supported plugins you have installed; the available triggers and actions depend on the plugins active on your site.

= How are scheduled and background steps processed? =

Zaplane uses Action Scheduler (the same background-processing library used by WooCommerce) to run delays, scheduled steps and long-running workflows reliably.

= Where does my data go? =

Workflows, run logs and connection credentials are stored in your WordPress database. Data leaves your site only when something you set up needs it to: a workflow step that calls a service, testing a connection, a builder field that loads options from a connected account (such as your Slack channels), or an AI step. Every service, with what is sent and when, is listed under External services.

= Can workflows reach private or internal addresses? =

Not by default. HTTP Request, Send Webhook, Custom Apps and every other step that fetches a URL refuse loopback, private, link-local and reserved addresses, including after a redirect. A developer can allow a specific internal host with the `zaplane_http_allowed_private_hosts` filter.

= What can an AI client connected through the MCP server do? =

Read your workflows, runs and recipes, draft and test workflows, and, if you gave its token the run scope, start them. It cannot administer your site. Actions that activate or deactivate plugins, create, change or delete users, change roles or capabilities, or change site options are hidden from it, and it cannot create, read, edit, activate, test or run a workflow that uses one, including one you built yourself. You can still use those actions in workflows you build in the Zaplane dashboard.

= Can a Custom App call PHP functions? =

Only functions that code on your site has allowed with the `zaplane_custom_app_callables` filter. A Custom App can also fire a WordPress action or filter hook of your choosing.

= Can I build multi-step workflows with conditions and delays? =

Yes. You can chain multiple actions and insert delay, condition, filter, iterator and variable steps to control exactly how and when each step runs.

= Where is the source code for the admin interface? =

In the plugin itself. `dev_zaplane/` holds the React source of the admin interface and `assets/scss/` its Sass; the files in `assets/build/` are compiled from them. See Source Code and Build Process for where each file is and how to build it.

== Screenshots ==

1. The visual workflow builder canvas.
2. Choosing a trigger for a new automation.
3. Configuring an action step.
4. Browsing ready-made recipe templates.
5. Workflow run history and logs.

== External services ==

Zaplane contacts no external service when you install, activate or browse it, and it sends nothing to its authors. It connects to a third-party service only after you add a connection for that service, and then only when you test the connection, when the workflow builder loads options from it (for example your Slack channels or Trello boards), or when a workflow step that uses it runs. Each service needs your own account with its provider, so please review their terms and privacy policies before connecting. Like every request WordPress makes, these carry your site's address in the User-Agent header.

= Slack =
* Used for: the Slack app (send messages, manage channels, add reactions, receive Slack events) and the Human Approval step's optional Slack notification.
* Sends: the channels, users, message text and names you configure; the OAuth code or bot token you connect with; for Human Approval, the message and its approve and reject links, to the incoming webhook URL you enter.
* When: when you connect or test a Slack connection, when the builder loads your channels, and when a Slack or Human Approval step runs.
* Terms of service: https://slack.com/terms-of-service/api
* Privacy policy: https://slack.com/trust/privacy/privacy-policy

= Discord =
* Used for: the Discord app (send messages, create invites, add reactions) through a bot token you provide.
* Sends: server, channel and user IDs and the message content you configure.
* When: when you test the connection, when the builder loads channels, and when a Discord step runs.
* Terms of service: https://discord.com/terms
* Privacy policy: https://discord.com/privacy

= Telegram =
* Used for: the Telegram app (send messages, media, locations and polls) through a bot token you provide (api.telegram.org).
* Sends: the chat ID, message text and media URLs you configure.
* When: when you test the connection and when a Telegram step runs.
* Terms of service: https://telegram.org/tos/bot-developers
* Privacy policy: https://telegram.org/privacy

= Meta (Messenger and WhatsApp) =
* Used for: the Messenger and WhatsApp apps, through the Meta Graph API (graph.facebook.com) with the page or WhatsApp Business token you provide.
* Sends: the recipient ID or phone number, message text, templates, media URLs and locations you configure.
* When: when you test the connection and when a Messenger or WhatsApp step runs.
* Terms of service: https://developers.facebook.com/terms/ and https://www.whatsapp.com/legal/business-terms
* Privacy policy: https://www.facebook.com/privacy/policy/

= Google (Gmail, Google Calendar and Google Meet) =
* Used for: the Gmail app (send email, load labels) and the Google Meet app (create calendar events with Meet links and meeting spaces), through Google's APIs (gmail.googleapis.com, www.googleapis.com, meet.googleapis.com) after you authorize your own Google Cloud OAuth client (accounts.google.com, oauth2.googleapis.com).
* Sends: recipients, subjects and message bodies; event titles, times, descriptions and attendee emails; OAuth codes and tokens.
* When: when you connect, when the builder loads labels or calendars, and when a Gmail or Google Meet step runs.
* Terms of service: https://policies.google.com/terms and https://developers.google.com/terms
* Privacy policy: https://policies.google.com/privacy (see also https://developers.google.com/terms/api-services-user-data-policy)

= AI provider configured in WordPress =
* Used for: AI and AI Agent steps, and Generate Image, when their connection uses the WordPress AI provider (the default).
* Sends: Zaplane hands the prompt, conversation history, knowledge context, tool definitions and results, and any image you reference to the AI Client in WordPress core, which sends them to the provider the site owner configured in WordPress. Zaplane stores no key for it and contacts no AI service itself.
* When: when one of those steps runs.
* Terms of service and privacy policy: those of the provider configured in WordPress, for example the Anthropic, OpenAI or Google Gemini terms listed below.

The Anthropic, OpenAI, Google Gemini and OpenAI-compatible entries below apply only when you choose that provider on a connection and enter your own key.

= Google Gemini API =
* Used for: AI steps with the Gemini provider and, if you turn it on, Business Knowledge semantic search, using your Gemini API key (generativelanguage.googleapis.com).
* Sends: prompts, conversation history, knowledge context and images you reference; for semantic search, knowledge entry titles and content and search queries.
* When: when an AI step runs, and with semantic search on, when knowledge entries are embedded or searched.
* Terms of service: https://ai.google.dev/gemini-api/terms
* Privacy policy: https://policies.google.com/privacy

= Anthropic =
* Used for: AI and AI Agent steps with the Anthropic provider, using your API key (api.anthropic.com).
* Sends: instructions, messages, conversation history, knowledge context, tool definitions and results, and image URLs you reference.
* When: when you test the connection (which lists models) and when an AI or AI Agent step runs.
* Terms of service: https://www.anthropic.com/legal/commercial-terms
* Privacy policy: https://www.anthropic.com/legal/privacy

= OpenAI =
* Used for: AI and AI Agent steps with the OpenAI provider (chat, image generation, audio transcription) and, if you turn it on, Business Knowledge semantic search, using your API key (api.openai.com).
* Sends: the same kinds of data as for Anthropic, plus image prompts, the audio files you point to for transcription, and knowledge entry text and search queries for semantic search.
* When: when you test the connection, when an AI step runs, and with semantic search on, when knowledge entries are embedded or searched.
* Terms of service: https://openai.com/policies/services-agreement/
* Privacy policy: https://openai.com/policies/privacy-policy/

= OpenAI-compatible providers =
* Used for: AI and AI Agent steps when you choose the OpenAI-compatible provider and enter a base URL (for example Azure OpenAI, OpenRouter at openrouter.ai, or a model you host yourself with Ollama, which stays on your own server).
* Sends: the same data as the OpenAI provider, to the base URL you entered.
* When: when you test the connection, when the builder loads the models the endpoint lists, and when an AI or AI Agent step runs.
* Terms of service and privacy policy: those of the provider you choose. For OpenRouter: https://openrouter.ai/terms and https://openrouter.ai/privacy. For Azure OpenAI: https://www.microsoft.com/licensing/terms/ and https://www.microsoft.com/privacy/privacystatement

= Zoom =
* Used for: the Zoom app (create meetings and webinars, add registrants) through your own Zoom app (api.zoom.us, with OAuth at zoom.us).
* Sends: meeting topics, times and settings; registrant names and emails; OAuth codes and tokens.
* When: when you connect and when a Zoom step runs.
* Terms of service: https://www.zoom.com/en/trust/terms/
* Privacy policy: https://www.zoom.com/en/trust/privacy/privacy-statement/

= Trello =
* Used for: the Trello app (create and update cards, lists and boards) with your API key and token (api.trello.com).
* Sends: the card, list, board, member and label details you configure, with your key and token.
* When: when you test the connection, when the builder loads boards and lists, and when a Trello step runs.
* Terms of service: https://www.atlassian.com/legal/atlassian-customer-agreement
* Privacy policy: https://www.atlassian.com/legal/privacy-policy

= Mailchimp =
* Used for: the Mailchimp app (add or update subscribers and tags) with your API key (the api.mailchimp.com data centre named in your key).
* Sends: subscriber email addresses, merge fields, tags and audience IDs.
* When: when you test the connection and when a Mailchimp step runs.
* Terms of service: https://mailchimp.com/legal/terms/
* Privacy policy: https://www.intuit.com/privacy/statement/

= Brevo =
* Used for: the Brevo app (create and update contacts, manage lists) with your API key (api.brevo.com).
* Sends: contact email addresses, names, attributes and list IDs.
* When: when you test the connection and when a Brevo step runs.
* Terms of service: https://www.brevo.com/legal/termsofuse/
* Privacy policy: https://www.brevo.com/legal/privacypolicy/

= ActiveCampaign =
* Used for: the ActiveCampaign app (contacts, lists, tags, automations) at the account API URL and key you enter (your account's own address, such as youraccount.api-us1.com or youraccount.activehosted.com).
* Sends: contact email addresses, names and phone numbers, and list, tag and automation IDs.
* When: when you test the connection and when an ActiveCampaign step runs.
* Terms of service: https://www.activecampaign.com/legal/terms-of-service
* Privacy policy: https://www.activecampaign.com/legal/privacy-policy

= Typeform =
* Used for: the Typeform app (list and create forms) with your access token (api.typeform.com). After the Create Form step runs, it registers your site's webhook URL with that form so its responses reach your workflows.
* Sends: form titles, fields and workspace, and your site's webhook URL.
* When: when you test the connection, when the builder loads forms, and when a Typeform step runs.
* Terms of service: https://www.typeform.com/legal/service-terms-and-conditions
* Privacy policy: https://www.typeform.com/legal/privacy-policy

= Jotform =
* Used for: the Jotform app (list and create forms) with your API key (api.jotform.com).
* Sends: form IDs and the form definitions you create.
* When: when you test the connection, when the builder loads forms, and when a Jotform step runs.
* Terms of service: https://www.jotform.com/terms/
* Privacy policy: https://www.jotform.com/privacy/

= Fillout =
* Used for: the Fillout app (list forms and their fields) with your API key (api.fillout.com).
* Sends: form IDs.
* When: when you test the connection and when the builder loads forms.
* Terms of service: https://www.fillout.com/terms
* Privacy policy: https://www.fillout.com/privacy

= YouTube thumbnails in the email editor =
* Used for: showing a video's thumbnail when you add a YouTube link to a Video block on the Email Templates screen (img.youtube.com).
* Sends: the video ID, as part of the image address.
* When: when you enter a YouTube link in a Video block.
* Terms of service: https://www.youtube.com/t/terms
* Privacy policy: https://policies.google.com/privacy

= Addresses you configure =
HTTP Request, Send Webhook, Custom Apps, the MCP Client, the AI Agent's HTTP tool and the steps that read a file from a URL (CSV, Image Helper, Upload Media From URL, AI image and audio) send data to, or fetch data from, the address you enter or that your workflow supplies. What is sent is what you configure in that step; the MCP Client also sends your site's address so a server can recognise calls from it. These requests go only where you point them, and addresses on private networks are refused unless a developer allows them.

= MCP client metadata =
When the optional MCP server is on and an AI client identifies itself with a client ID that is a URL, Zaplane fetches that client's public metadata document from the URL to show you who is asking. Nothing about your site is sent.

= Links to AI clients =
The MCP settings screen offers shortcuts that open claude.ai or chatgpt.com in your browser with the connector name and this site's MCP address already filled in, so you do not have to type them. These are ordinary links you choose to follow: Zaplane sends nothing to either service itself, and the addresses only ever appear in a page you opened yourself.
* Terms of service: https://www.anthropic.com/legal/consumer-terms and https://openai.com/policies/row-terms-of-use/
* Privacy policy: https://www.anthropic.com/legal/privacy and https://openai.com/policies/row-privacy-policy/

== Source Code and Build Process ==

Only one part of Zaplane is compiled: the admin interface, which is a React app. Everything else, including all of the plugin's PHP in `includes/` and `integrations/`, runs exactly as it ships. The complete, uncompiled source of the admin interface is included in this plugin, together with everything needed to build it.

= Where the development files are =

* `dev_zaplane/` — the React source: `app.js` is the entry point, `app.scss` its base stylesheet, with `components/` (shared UI), `containers/BackendDashboard/` (the admin screens and workflow canvas), `redux/` (store and slices), `hooks/`, `utils/` and `webpack/` (a loader that points the email editor's placeholder images at `assets/images/`).
* `assets/scss/` — the Sass for the admin interface; `backend.scss` is its entry.
* `package.json`, `webpack.config.js`, `tailwind.config.js`, `postcss.config.js` and `jsconfig.json` — the dependencies, build scripts and configuration. The build runs on `@wordpress/scripts`, with Tailwind CSS and Autoprefixer through PostCSS.

= Generated files =

* `assets/build/app.js` — built from `dev_zaplane/app.js` and every module it imports.
* `assets/build/app.css` and `assets/build/app-rtl.css` — built from the Sass above; the right-to-left file comes from the same source.
* `assets/build/app.asset.php` — written by the build: the script's WordPress dependencies and its version.

Every generated `.js` and `.css` file starts with a comment naming its source and the commands that rebuild it.

= Building the admin interface =

You need Node.js 18.12 or newer and npm 8.19 or newer. In the plugin's folder, `wp-content/plugins/zaplane`:

1. Install the dependencies: `npm install --legacy-peer-deps`
2. Build the production files: `npm run build`

The build writes minified files to `assets/build/`. While working on the source, `npm run start` rebuilds on every save, unminified and with source maps; run `npm run build` again before using the plugin on a live site. `--legacy-peer-deps` is needed because one dependency, react-json-view, declares support only for older React versions, although it works with the version Zaplane uses.

The PHP library in `vendor/` (Action Scheduler) is managed with Composer; `composer install --no-dev` reinstalls it.

== Changelog ==

= 1.3.2 =
**Security**
* An AI client connected through the MCP server can no longer reach actions that administer the site: activating or deactivating plugins, creating, changing or deleting users, changing roles and capabilities, and changing site options. It cannot add them to a workflow, or read, edit, activate, test or run a workflow that uses them.

**Fixed**
* Conditions, filters and a few triggers no longer call string functions that need PHP 8, so Zaplane runs on PHP 7.4 as declared.
* Workflow steps now run when Action Scheduler is processed from WP-CLI, as many hosts do from a system cron. Before, every queued step failed there.

= 1.3.1 =
**Security and privacy**
* Removed the Switch Theme and Authenticate User actions. Which theme a site runs, and signing in, are the site owner's own decisions.
* Create, Update and Delete User now accept only the fields their form offers and a role the site defines, and refuse to delete the last administrator or the account the run is using.
* Incoming webhook and Catch Webhook URLs check the provider's signature, or the trigger's shared secret, before the request reaches the workflow.
* The workflow query builder now refuses any table, column, operator or sort direction that is not a plain name, and every value it sends is a placeholder filled in by WordPress.
* The admin palette, the integrations catalogue and the MCP consent screen are escaped on the way out.
* Documented the Telegram API address and the links to claude.ai and ChatGPT under External services.

**Fixed**
* Lists of courses, quizzes, ranks and achievements are read through WordPress instead of the posts table directly.
* Fixed selecting a specific set of columns — a "get this field for every row" query returned nothing.

= 1.3.0 =
**Workflows**
* A workflow can start from more than one trigger. Whichever fires starts the run, and `{{trigger.*}}` reads its data.
* Connecting steps is easier: drop a line anywhere on a step's card, and letting go on empty canvas opens the step picker.

**Recipes**
* Group recipes set up several workflows at once in a new folder, starting with WooCommerce Customer Lifecycle and StoreEngine Store Emails.
* Every recipe opens a setup for its optional steps, settings and connections. Plugins can register recipes as simple arrays.

**AI**
* The AI, AI Agent and Generate Image steps use the WordPress AI Client by default on WordPress 7.0 and later, including the agent's tool calling. Your own provider key is still an option.

**AI access (MCP server)**
* claude.ai and ChatGPT can connect through OAuth, with administrator approval. WordPress application passwords also work.
* Tool calls are logged, tokens can expire, and run tokens can be limited to chosen workflows.

**Security and compatibility**
* Merge tags are no longer evaluated as PHP, and outbound requests are checked on every redirect.
* MCP tokens now act as their own user and require administrator rights.
* Updates come only from WordPress.org. WordPress 6.8 or later is required.

**Fixes**
* GemCRM Send Email now reaches only the chosen contact or list.
* Fixed shipped recipes' Send Email steps, duplicate recipes after a rename, fields with hyphens, forms watched by two triggers, and Logs Re-Try.

= 1.2.0 =
**New integrations**
* aBlocks — a Form Submitted trigger for aBlocks form-builder forms.
* FluentCart — 21 new triggers and 52 new actions across carts, coupons, licences, customers, orders, products and subscriptions.

**AI access (MCP server)**
* Let Claude, Cursor or any Model Context Protocol client read your automations and build new ones from a plain-language description. Off by default, behind scoped and revocable tokens.

**Modules**
* Optional features are now opt-in and switched off on a fresh install. Existing sites keep whatever they had.
* A card on the Workflows, Dashboard and Connections screens lists what you have not turned on, with a one-click switch. Picking a step whose module is off now says so in the builder and offers to enable it there.

**Workflow canvas**
* Nodes are colour-typed as trigger, action, tool or AI, on the card and on the connector leaving it, so a glance shows what feeds what.
* Node cards now sit on a canvas of their own colour instead of sharing it, connectors draw a single stroke instead of three, and the delete control is a real, keyboard-reachable button.

**Appearance**
* The dark theme was rebuilt for legibility — secondary text and button labels previously fell below the accessible contrast minimum.
* The dashboard chart now follows your palette instead of fixed light-mode colours.

**Performance**
* The admin bundle drops from 23.8 MB to 1.6 MB.

**Removed**
* The Dokan "Withdraw Request Pending" and FluentCart "Order Paid Done" triggers. A workflow still using either will need its trigger re-selected.

= 1.1.0 =
**New integrations**
* StoreEngine — order, subscription and product triggers plus actions.
* AI (Chat Model) — generate replies with Anthropic, OpenAI, or WordPress Core AI.
* AI Agent — autonomous tool-calling agent with Chat Model, Memory and Tools sub-nodes, plus MCP server support.
* Conversation Memory — durable, keyed chat history for AI conversations.
* Business Knowledge — a searchable knowledge base the AI Agent can query; sync from StoreEngine products.
* Custom Apps — build your own integrations (REST or same-site WordPress hooks) without code changes.
* Webhook — a dedicated app for both directions: Catch Webhook (incoming) and Send Webhook (outgoing) with optional HMAC-SHA256 signing.
* Messenger and WhatsApp — AI auto-reply flows.
* Academy — course enrolment triggers, actions and access-group membership.

**New tools & flow control**
* Manual Trigger, Schedule trigger, and Human-in-the-Loop approval.
* Router (multi-branch), Repeater/Iterator, Condition and Filter.
* Utility tools: CSV, XML, JSON Parser, Formatter, Date/Time, Image Helper, Set Variable, and Sticky Note.

**New recipe**
* "AI Reply to Incoming Webhook" — receive a question by webhook, answer it with the AI Agent using your Business Knowledge, then post the reply back out.

**Improvements**
* Dynamic-data "@" token picker across expression fields, with sample outputs so tokens are available before a test run.
* Redesigned Business Knowledge, field, checkbox and connection-icon UI, with icon fallbacks.
* Clearer node port / branch handles and AI Agent sub-node handles on the canvas.

**Fixes**
* AI and AI Agent no longer appear as trigger options where only actions apply.
* Custom Apps: trigger firing, label handling, and the stale generic "Custom App" entry in the app picker.
* Webhook: structured Body fields so multi-line/quoted values (e.g. an AI reply) can't produce malformed JSON.
* Node port, multiple-output, route-indicator, CSV parser, and dynamic-data resolution fixes.

= 1.0.2 =
* Improvements and bug fixes.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.3.2 =
MCP clients can no longer use actions that administer the site, and PHP 7.4 compatibility is fixed.

= 1.3.1 =
Security hardening throughout, and two actions removed: Switch Theme and Authenticate User.

= 1.3.0 =
Adds multiple triggers per workflow, group recipes, WordPress AI Client support and OAuth sign-in for AI clients, and fixes security issues. Requires WordPress 6.8 or later. MCP tokens issued to users without administrator rights stop working.

= 1.2.0 =
Adds aBlocks and FluentCart integrations, an optional MCP server and a rebuilt dark theme. Optional modules are off on new sites. The Dokan "Withdraw Request Pending" and FluentCart "Order Paid Done" triggers were removed; re-select the trigger in any workflow using them.

= 1.1.0 =
Major feature release: StoreEngine, AI Agent (with MCP), AI Chat Model, Memory, Business Knowledge, Custom Apps, a bidirectional Webhook app, and many new tools and flow-control nodes.

= 1.0.2 =
Recommended update with stability improvements and bug fixes.
