=== Zaplane - WordPress Automation ===
Contributors: kodezen
Tags: automation, workflow, woocommerce, marketing automation, crm
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Visual, no-code automation for WordPress. Connect triggers and actions across WooCommerce, CRMs, forms, email and 80+ apps.

== Description ==

**Zaplane** is a self-hosted, no-code automation builder for WordPress — think Zapier or Make, but running entirely on your own site. Connect a **trigger** (something that happens, like a WooCommerce order being completed or a form being submitted) to one or more **actions** (send an email, add a contact to your CRM, post a Slack message, and more), with delays, conditions, filters and loops in between.

Build workflows visually on a drag-and-drop canvas — no code required — and let them run reliably in the background.

= Why Zaplane? =

* **No-code visual builder** — design automations on a flow canvas by connecting trigger and action nodes.
* **Self-hosted & private** — your data never leaves your site; no per-task pricing or external middleware.
* **80+ integrations** — connect the WordPress plugins and external apps you already use.
* **Powerful flow control** — delays, conditions, filters, iterators (loops), custom variables and raw HTTP/webhook requests.
* **Reliable background processing** — long-running and scheduled steps are powered by Action Scheduler.
* **Ready-made recipes** — start from pre-built templates for common automations.

= Ready-made recipe templates =

* Abandoned cart recovery
* Customer birthday / anniversary messages
* Inactive customer win-back
* Post-purchase upsell
* Order-complete feedback request
* Product recommendation follow-ups

= Flow-control building blocks =

* **Delay** — wait minutes, hours or days before the next step.
* **Condition** — branch the workflow based on data.
* **Filter** — stop a workflow unless criteria are met.
* **Iterator** — loop over a list of items.
* **Variable** — store and reuse values across steps.
* **HTTP request** — call any external API or webhook.

= Integrations =

Zaplane connects with a wide range of WordPress plugins and external services, including:

* **eCommerce:** WooCommerce, WooCommerce Bookings, Easy Digital Downloads, SureCart, FluentCart, Dokan, FunnelKit, WPFunnels, StoreEngine, Paymattic, Abandoned Cart
* **CRM & marketing:** FluentCRM, Groundhogg, GemCRM, Mailchimp, Brevo, ActiveCampaign
* **Forms:** Fluent Forms, Gravity Forms, WPForms, Contact Form 7, Formidable, Ninja Forms, weForms, MetForm, SureForms, Jotform, Typeform, Fillout, Bit Form, ARForm, Avada Forms
* **LMS & membership:** LearnDash, Tutor LMS, LifterLMS, MasterStudy, Academy, MemberPress, Paid Memberships Pro, SureMembers, Ultimate Member, Profile Builder, BuddyBoss, GamiPress
* **Messaging & email:** Slack, Discord, Telegram, WhatsApp, Gmail, FluentSMTP, SureMail, Zoom, Google Meet
* **Page builders & blocks:** Elementor, Divi, Beaver Builder, Kadence Blocks, Essential Blocks, Spectra, CoBlocks
* **Content & data:** Advanced Custom Fields, JetEngine, Meta Box, The Events Calendar, WP User Frontend, generic HTTP / Webhooks

External services (Slack, Discord, Telegram, WhatsApp, Gmail, Zoom, Google Meet, Trello, Mailchimp, Brevo, ActiveCampaign, etc.) are third-party products. Using them with Zaplane requires your own account with that service, and your data is sent to those services only when you build a workflow that uses them. Please review each service's terms of service and privacy policy.

== Installation ==

1. Upload the `zaplane` folder to the `/wp-content/plugins/` directory, or install the plugin through the **Plugins > Add New** screen in WordPress.
2. Activate the plugin through the **Plugins** screen.
3. Open the **Zaplane** menu in your WordPress admin.
4. Create a new automation, pick a trigger, add your actions, and turn it on.

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher

== Frequently Asked Questions ==

= Do I need a Zaplane account or any external service to use this? =

No. Zaplane runs entirely on your own WordPress site. You only need an account with a third-party service (for example Slack or Mailchimp) if you build a workflow that connects to that specific service.

= Does Zaplane require WooCommerce? =

No. WooCommerce is optional. Zaplane works with WordPress core events and with whichever supported plugins you have installed; the available triggers and actions depend on the plugins active on your site.

= How are scheduled and background steps processed? =

Zaplane uses Action Scheduler (the same background-processing library used by WooCommerce) to run delays, scheduled steps and long-running workflows reliably.

= Where does my data go? =

Workflow data stays on your site. Data is only sent to an external service when a workflow you create explicitly performs an action with that service (for example, sending a message to Slack or adding a subscriber to Mailchimp).

= Can I build multi-step workflows with conditions and delays? =

Yes. You can chain multiple actions and insert delay, condition, filter, iterator and variable steps to control exactly how and when each step runs.

== Screenshots ==

1. The visual workflow builder canvas.
2. Choosing a trigger for a new automation.
3. Configuring an action step.
4. Browsing ready-made recipe templates.
5. Workflow run history and logs.

== Changelog ==

= 1.0.2 =
* Improvements and bug fixes.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.2 =
Recommended update with stability improvements and bug fixes.
