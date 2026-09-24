# Changelog

All notable changes to Zaplane are documented here. This project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed
- Resolved the duplicate `handle_incoming()` method that caused a PHP critical error.
- Fixed the dashboard login/reload flow so it loads normally again.
- Corrected the Log History horizontal scrollbar and responsive layout issues.
- Fixed the shared modal/overflow behavior so opening Create Workflow no longer causes the right-side scrollbar to disappear or the UI to shift across Zaplane tabs.
- Fixed the Dashboard recent workflow data rendering, empty-state messaging and heading output.
- Improved Log History UX with responsive table behavior, accessibility labels and consistent duration text formatting.
- Cleaned up workflow UI issues including unused code, missing labels, keyboard access and related lint-level problems.
- Reduced the `react-datepicker` build warning by switching to a stable CommonJS entry.
- Rebuilt the production frontend assets successfully and verified the build remains healthy.
- Fixed the Import button icon issue in the workflow UI.
- Fixed pagination issues across the Zaplane admin interface.
- Fixed Mailchimp trigger and action issues for more reliable workflow execution.
- Fixed Custom App REST API issues affecting REST API requests and responses.
- Fixed FunnelKit trigger and action issues.
- Fixed SureMark trigger and action issues.

### Changed
- Updated the shared modal and drawer overflow handling to preserve layout stability when a panel opens.
- Improved the overall admin UI consistency for logs and recent-run views across narrower screens.
- Normalized project lint configuration and cleaned up repeated styling issues to keep the release baseline healthy.

### Added
- Added ZenCommunity Core + Pro integration with 32 triggers and 34 actions.
- Added automation support for community members, profiles, spaces, posts, comments, reactions, polls, and events.
- Added ZenCommunity Pro support for private messaging, group messaging, and support ticket workflows.
- Added dynamic field selectors for spaces, users, roles, events, posts, and ticket settings.
- Added cURL support to Custom Apps, allowing users to create Custom App requests directly from cURL configurations.
- Added a new GemBooking integration with trigger and action support.
- Added new Telegram triggers and actions for Telegram-based workflow automation.

### Improved
- Improved permission handling for member-triggered, admin-owned workflows.
- Improved action error handling to stop downstream steps when an action fails.
- Improved compatibility with background workflow execution.

### Fixed
- Fixed trigger detection and payload handling across supported ZenCommunity events.
- Fixed action failure states being incorrectly treated as successful workflow runs.
- Fixed sender identity handling in automated messaging.

### Testing
- Validated all 32 triggers and 34 actions, including destructive operations using database transactions and rollbacks to preserve test data.

## [1.3.1] - 2026-09-20

### Removed
- **Switch Theme action.** Changing the active theme is the site owner's decision,
  taken in the Appearance screens. The Theme Switch *trigger* stays: a workflow can
  still react to a switch the owner makes.
- **Authenticate User action.** It called `wp_signon()` with credentials stored in
  the workflow, which set auth cookies on whatever request happened to be running
  the run — a visitor's page view could end up signed in as that account.

### Security
- Create, Update and Delete User accept only the fields their own form offers,
  filtered and sanitized per field, and only a role the site actually defines.
  Delete refuses the last remaining administrator and the account the run is using.
- The incoming-webhook and Catch Webhook routes verify the provider's signature or
  the trigger's shared secret in their `permission_callback`, so an unsigned
  request never reaches the code that dispatches a workflow.
- The ORM refuses any table, column, operator, sort direction, join type or
  aggregate that is not a plain identifier or a known keyword (`Identifier`), and
  prepares every value in the same call that runs the statement.
- Schema changes and the remaining direct reads pass table names as `%i`
  identifiers to `$wpdb->prepare()`.
- The admin colour palette is validated before it becomes CSS, the integrations
  catalogue is escaped before it is inlined in a `<script>`, the MCP consent page
  goes through `wp_kses()`, and the webhook handshake echo is constrained and
  escaped.

### Changed
- LifterLMS, GamiPress and Paid Memberships Pro option lists are read through
  `get_posts()`/`prepare()` rather than the posts table directly.
- README documents the Telegram API address and the links the MCP screen offers to
  claude.ai and ChatGPT.

### Fixed
- `select()`, `addSelect()` and `groupBy()` accept an array of columns again;
  passing one nested the array and produced `SELECT \`Array\``, which broke
  `pluck()` and `value()`.

## [1.3.0] - 2026-09-15

### Added
- **AI steps use the WordPress AI Client by default.** On WordPress 7.0 and later
  the AI, AI Agent and Generate Image steps run on the provider the site owner set
  up in WordPress, with no key stored in Zaplane. The AI Agent's tool calling runs
  through it too. Your own OpenAI, Anthropic, Gemini or OpenAI-compatible key is
  still available, for older WordPress versions, audio transcription and
  knowledge-base embeddings.
- **Hosted AI connectors can sign in to the MCP server.** claude.ai and ChatGPT
  attach through OAuth 2.1 with dynamic client registration or a client ID
  metadata document, and an administrator approves each one on a consent screen.
  A WordPress application password also works, capped at read and write.
- MCP: every tool call is logged on the Logs screen. You get an email the first
  time a token runs a workflow and when a token is refused repeatedly. Tokens can
  expire, and a run token can be limited to chosen workflows.
- MCP: someone without admin rights can ask to connect a client, and an
  administrator allows it. The token acts as the person who asked.
- MCP: Settings → AI access has a self-check for clients that will not connect, and
  writes the configuration file for the client you pick.
- MCP: `list_field_options` gives a client the site's own choices for a field,
  such as courses, products, forms and CRM lists, so it doesn't guess ids.
- **A workflow can start from more than one trigger.** Add another trigger under
  the first one on the canvas. Whichever fires starts a run from itself and
  follows only its own connections. Triggers are numbered on the canvas, the Logs
  list shows which one started each run, and a trigger can be deleted as long as
  another remains.
- **`{{trigger.*}}`** reads the data of whichever trigger started the run, and
  `{{workflow.trigger_app}}`, `{{workflow.trigger_event}}` and
  `{{workflow.trigger_node_id}}` say which one it was, so a Router can branch on it.
- **Match fields to another trigger.** A trigger can fill another trigger's field
  names from its own data, so steps written against `{{1.email}}` still get a
  value when a different trigger fires. Fields with the same name match in one click.
- **Warnings for workflows with several triggers:** a trigger with no app, one that
  leads nowhere, two identical triggers that would run twice, steps reading fields
  another trigger doesn't supply, and a webhook with no secret that runs the same
  steps as a site trigger. They show on the canvas and never block a save.
- Each Catch Webhook trigger has its own URL, `/hook/<workflow>/<trigger>`. The old
  `/hook/<workflow>` still works and goes to the first webhook trigger.
- Test Trigger listens for that trigger only. Test Flow Once listens for all of
  them and runs from whichever fires first.
- MCP: `run_workflow` and `test_workflow` take `trigger_node_id`, and the dry run
  walks one trigger at a time.
- **Connecting steps is easier.** Drop a line anywhere on a step's card to connect
  it, not only on the small dot. The dots have a bigger grab area and a line snaps
  to one from nearby. While you drag, the steps that can take the line are
  outlined, and letting go on empty canvas opens the step picker, already
  connected. Lines into a trigger, loops and duplicate lines are refused.
- **Group recipes.** One recipe can set up several workflows at once. Run Group
  Recipe opens a setup: switch each workflow, and some of their steps, on or off,
  fill in a few values such as a coupon's discount, pick the connections they
  need, and create them together in a new folder, as drafts or turned on. The
  first is *WooCommerce Customer Lifecycle*: abandoned carts, a thank-you coupon,
  a feedback request, a win-back email and a birthday coupon.
- **Register a recipe with an array.** Name each step by its app and event, such
  as `gemcrm.send_email`, and give its settings. Zaplane lays the steps out,
  connects them and fills in their labels, icons and trigger hooks. Use
  `zaplane_register_recipe()` or the `zaplane_register_recipes` action; see
  `docs/recipes/registering-recipes.md`. Recipes a plugin registers show up the
  next time Recipes is opened.
- **Every recipe gets a setup.** Use Recipe opens the same setup as a group recipe,
  showing only what that recipe needs: its optional steps, its settings, its
  connections, and a name for the workflow.
- A folder set up from a group recipe says which one. Any folder can turn all of
  its workflows on, or pause them, in one go.
- The Recipes page can show only group recipes, or only single-workflow recipes.
- **StoreEngine Store Emails**, a group recipe that sends a StoreEngine store's
  emails from workflows: order confirmation, status, note, refund, shipping,
  delivery and cancellation emails, failed payment and failed renewal recovery,
  renewal and cancelled subscription emails, alerts to the store for new orders
  and failed payments, and a review request after delivery. Each email is a
  workflow you can add to, and some come with a follow-up coupon or an order
  note. While one of these workflows is on, StoreEngine's own copy of its email
  is switched off; pause the workflow and StoreEngine sends it again. This needs
  a StoreEngine version with the `storeengine/email/setting` filter.
- StoreEngine: a **Subscription Renewal Payment Failed** trigger, with a link to
  pay again. Order triggers also give `first_name`, `order_date`,
  `total_formatted`, `items_summary`, `payment_method_title`, `order_url`,
  `payment_url` and `edit_order_url`. Status triggers give status labels, Order
  Item Shipped gives the item name, courier and tracking, refund triggers give
  the amount and reason, and subscription triggers give the customer's name and
  formatted total.
- StoreEngine's Create Coupon step can add a random ending to the code, so a code
  built from an order number can't be guessed.
- The recipes that ship with Zaplane no longer refer to steps by number. Their
  emails and steps read the trigger as `{{trigger.first_name}}`, and a field of
  an earlier step by its name, such as `{{coupon.code}}` in the email after a
  Create Coupon step. Workflows already made from a recipe keep working as they are.

### Changed
- Updates now come only from WordPress.org. The bundled licensing SDK is gone. It
  checked store.kodezen.com for updates, fetched promotions, and could send usage
  data after the opt-in was declined.
- Action Scheduler 4.1.0. WordPress 6.8 or later is now required (was 6.0).
- The Zaplane admin menu no longer asks to sit near the top of the menu.
- The uncompiled source of the admin app ships with the plugin. Every built file
  names its source.

### Security
- Merge tags are parsed instead of being compiled to PHP and run with eval(). A
  webhook body could previously reach a function call.
- URLs that come from workflow settings or run data are checked on every redirect.
  Private and reserved network addresses are refused.
- An MCP token now acts as the user it was issued to, and that user needs
  `manage_options` on every call. Before, a token reached as far as an
  administrator's, even for a user who was later demoted or deleted.

### Fixed
- GemCRM Send Email to a contact could reach the wrong contact, and Send Email to a
  list mailed every contact. Both now reach only the chosen recipients, and
  unsubscribed or bounced contacts are skipped.
- The admin menu icon rendered as a grey smudge in most admin colour schemes.
- MCP: a batch request got an extra response, and hitting the rate limit answered
  401 instead of 429.
- Workflows made from the WooCommerce and GemCRM recipes that ship with Zaplane
  couldn't go live: their Send Email steps were missing the Email Content
  setting. Recipes already on a site are corrected on update.
- Renaming a recipe that ships with Zaplane added a second copy on the next
  update, and deleting one brought it back. Shipped recipes are now known by a
  slug, and a deleted one stays deleted.
- The Recipes page showed only the first 20 recipes.
- A step got nothing from a field whose name has a hyphen, such as a Contact Form 7
  form's `your-email`, so a Create Contact step said a valid email was required.
  A minus between two values still subtracts.
- Submitting a form failed with an error when two triggers, in one workflow or in
  several, listened for that form. Both runs started, but the visitor saw an error.
- Changing a live trigger's settings without adding or removing a step could keep
  it firing with the old settings for up to an hour. The trigger cache now keys on
  the saved graph, not only the version.
- A field using `{{wp.…}}` or `{{workflow.…}}` next to a step's output could read
  the wrong step, because merging the two renumbered the step ids.
- The Logs **Re-Try** button retried whichever node run shared the run's id. It now
  runs the run again from the same trigger with the same data.
- The schedule and inactive-customer triggers kept one "last fired" record per
  workflow. Each trigger now keeps its own, so two of them don't hold each other back.
- Test Trigger and Test Flow Once ran the event they caught twice, once for real
  and once as the test, and a test left waiting held back that trigger's real
  events. The caught event now runs only as the test.

## [1.2.0] - 2026-09-08

The dashboard is rebuilt, the workflow canvas is retyped, modules become opt-in
and discoverable, and Zaplane gains an MCP server. Integration coverage grows from
**998 to 1,070** triggers and actions across **93** apps and tools.

### Security
- **Merge tags are parsed now, not compiled and handed to `eval()`.** The tag
  inside `{{ … }}` was rewritten into PHP — identifiers became array lookups —
  and everything that was not an identifier went through untouched: operators,
  parentheses, semicolons, `$`, braces, backticks. That is a filter with a hole
  in it rather than a sandbox. Worse, `{{a.b(c.d)}}` compiled to
  `$data["a"]["b"]($data["c"]["d"])` — a function call whose name was read out
  of the run's own data. Every node's configuration is resolved against that
  data before the node executes, and for a webhook-triggered workflow the data
  is a JSON body posted by a stranger.

  There is a small parser in its place. Paths, numbers, strings, `true`/`false`/
  `null`, the arithmetic, comparison and logical operators, and parentheses for
  grouping — the same language as before, minus the parts nobody asked for.
  There is no production for a function call, which is why one can no longer be
  written. Anything outside the grammar resolves to nothing, as an unresolvable
  tag always has.
- **An outbound request can no longer be aimed inside your network.** The
  Webhook action, the HTTP Request action and every Custom App ask the
  `zaplane_http_block_request` filter before connecting — and nothing answered
  it, so the only check that ran was that the scheme was http or https. Since a
  node's configuration is resolved at run time, a URL containing a merge tag is
  chosen by whatever triggered the workflow, and the response comes back into
  the run: a way to read whatever this server can reach and the caller cannot —
  another site on the same host, an admin panel on a private address, the cloud
  metadata service at 169.254.169.254.

  Loopback, private, link-local, carrier-grade-NAT and reserved addresses are
  refused by default, on every address a hostname resolves to rather than only
  the first, and a host that resolves to nothing is refused rather than passed
  on. The filter still has the last word, and a site with an internal service a
  workflow legitimately calls can name it through
  `zaplane_http_allowed_private_hosts` — matched as a whole hostname, so one
  entry cannot admit a lookalike.

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
- **Every credential acts as the account it was issued to, and is held to what
  that account can do.** A token recorded a user and nothing applied it — no user
  was set on the request, no capability was ever asked — so a token issued to
  anybody at all reached exactly as far as an administrator's, and one issued to
  an administrator who was later demoted or deleted kept working forever. The
  endpoint now becomes the token's user and asks for `manage_options`, which is
  what Zaplane asks of everyone on every one of its own screens. Asked on every
  call rather than once at issue, so demotion takes effect.

  This retires the queue that let somebody without `manage_options` ask an
  administrator to allow their client. It was built on the premise that the
  resulting token would act as the person who asked and so carry no more
  authority than they had; it never did, and now that it does, such a token is
  refused every time it is used. The consent screen says so plainly instead of
  parking a request and producing a credential that does not work. The menu
  bubble goes with it.
- **Losing a registration now revokes what it held.** Registration is open and
  unauthenticated — a hosted connector signs itself up unasked — so a burst of
  it pushes older entries past the 50-client cap. Their tokens used to stay
  behind: access still working, with nothing left to show where it came from,
  which is the exact thing removing a client by hand was careful to avoid.
- **A token's "last used" no longer lands on the wrong token, and no longer
  writes on every call.** It was stamped by position in the stored list, and a
  revoke between verifying and stamping shifts every later record down one. The
  write was also a read-modify-write of the whole list on every authenticated
  call, so two requests arriving together could lose each other's work —
  including a token issued in between. Now keyed by id, and at most once a
  minute.
- **Tokens that can never be used again are cleared out** when the next one is
  issued. Nothing pruned them before: an expired token was refused but kept
  forever. One still holding a refresh token is left alone — it is waiting to be
  renewed, not dead.
- The header is offered as **two fields, name and value**, because that is how a
  connector dialog asks for it — with a note that `Bearer` is part of the value.
  Pasting the token on its own is the usual way this goes wrong, and it fails
  with a plain 401 that says nothing about the cause.
- A freshly issued token now comes with **the command to use it**, with the token
  already in place rather than a placeholder, plus the raw header for anything
  else. The instructions sat at the top of the panel while the token appeared at
  the bottom, so at the moment you had it in hand there was nothing telling you
  what to do with it. A token carrying `run` says so, in the box.
- **The consent screen has a checkbox per scope, with `run` unticked.** It used
  to be a single Approve button granting whatever the client asked for — and
  clients ask for everything the server advertises, so the scope that sends mail
  and takes payments was arriving without anyone deciding on it. Never grants
  more than was requested, and ticking nothing falls back to read rather than to
  everything.
- **Registered clients are visible, and removable.** Apps that signed themselves
  up were previously invisible and permanent: you could revoke a token but not
  the registration behind it. The panel now lists them, says which are actually
  connected, and removing one revokes its tokens too — a token outliving its
  registration is access with no visible origin.
- **Connecting a client is now installing one.** Pick Claude Desktop, Claude
  Code, Cursor, Claude.ai, ChatGPT or Other and the panel writes the file that
  client reads, names the path it reads it from — on macOS, Windows or Linux —
  and offers it to copy or download. Per-client steps replaced one set of
  generic ones that was true for every client and useful for none.

  A desktop client that speaks stdio gets the bridge configuration rather than a
  URL it cannot use, with the header passed through the environment because a
  value with a space in it does not survive being split back apart, and every
  Basic credential has one. Where a client cannot sign in through a browser the
  panel says so and offers to **make the credential right there**, named after
  that client, and it lands in the configuration — that being the step people
  otherwise do by hand and get wrong. Claude.ai gets a link that opens its
  add-connector dialog with the name and URL in place, and both hosted clients
  say plainly when this site cannot be reached from the internet, because
  nothing below will work until it can. Claude Code needs no credential at all —
  it signs in through the browser on first use, which was true before and went
  unsaid.
- **A client can identify itself by URL instead of registering** (Client ID
  Metadata Document). This is the option the connector dialogs mark
  *Recommended*, and it failed until now because Zaplane did not support it —
  every new user met an error on the default setting. It also leaves no row
  behind, which is why it is recommended: dynamic registration accumulates one
  per client on a busy site.

  The whole risk is the fetch: a stranger chooses the URL and this server makes
  the request. Private and reserved addresses are refused before connecting —
  loopback, the LAN ranges, and the cloud-metadata address — redirects are not
  followed, the read is capped at 5KB, only a 200 counts, the document must name
  itself as the URL it was found at, and failures are never cached.
- **An email the first time a client runs a workflow for real, and when one is
  refused repeatedly.** Issuing a run-scoped token is a decision someone made
  once, possibly without reading the third checkbox; the moment it is used is
  when they would want to know. Five refusals in ten minutes from one token is
  either a misconfiguration or something trying, and one notice per token per
  hour keeps it from becoming noise. Only these two — a notice that arrives
  constantly is one nobody reads, and the log holds everything else. Switchable
  from the panel.
- **Tokens can be given a lifetime.** OAuth tokens last an hour and rotate; one
  pasted into a config lasted forever. Choose 30 days, 90, a year, or never when
  issuing, and the expiry shows on the token.
- **A `run` token can name the workflows it may start.** Holding `run` used to
  mean holding it over every workflow on the site, which made it an
  all-or-nothing decision — and clients ask for it by default. Tick Run when
  issuing a token and a list appears; choose the ones that client is for.
  Choosing none keeps the old meaning, so nothing already issued narrows
  underneath anyone. A refused workflow says which ones the token covers, and
  the refusal is recorded.
- **AI client activity is recorded, and lives on the Logs screen** beside workflow
  runs — which is where people already go to find out what happened. Every tool
  call: which client, whose account, which tool, how it went, how long it took.
  Refusals too, because repeated attempts at a scope a client was never given is
  the shape of something going wrong and is invisible if only successes are kept.
  Filter by outcome, page through it, clear it. Arguments are never recorded: a
  call carries whatever the model was working with, and copying that into a table
  nobody prunes turns an audit trail into a second, quieter database of
  everything. The settings panel links to it rather than trying to show it.
- **A self-check on the AI access panel, for when a client will not connect.** An
  AI client can only report the symptom from outside — "could not reach", "could
  not register" — which says nothing about the cause. **Run check** tests the same
  things the client does, from the site: whether the module is on, whether the
  endpoint refuses an unauthenticated call with a pointer to the sign-in service,
  whether the two discovery documents are actually served, whether the address is
  reachable from the internet at all, and whether application passwords are
  available. Each failure says what to do about it. A disabled module reports once
  rather than cascading into three unrelated-looking failures.
- **WordPress application passwords work as a third way in.** Core already
  authenticates them on REST requests, so a client that can send Basic auth needs
  no Zaplane token at all, and revoking it is where a WordPress user already
  looks — Users → Profile → Application Passwords. They never carry `run`: an
  application password is the whole user, with no way to withhold one capability,
  so the scope that sends mail and takes payments still has to be asked for
  deliberately.
- **The panel makes one for you, through WordPress's own screen.** Getting an
  application password meant leaving Zaplane, finding Users → Profile, scrolling
  past everything else on it, and remembering what to name the thing — so the
  quickest way in was also the least discoverable. **Create credential** now
  hands you to core's Authorize Application screen with the name already filled
  in, and brings you back to the panel with the password shown once and the
  Basic header assembled ready to paste. Nothing is duplicated to do it: core
  asks, core hashes, core stores, core revokes, and the panel reads its list and
  calls its delete. The credentials it made are stamped as Zaplane's, so the
  panel lists only those and will not revoke one somebody created for something
  else. They also still appear on the profile page, because they are the same
  credentials.

  Two things in that hand-off are Zaplane's to get right rather than core's. The
  return address is **held to this site** — core sends to any domain on purpose,
  saying so where it declines to use `wp_safe_redirect`, but this screen always
  comes back to wp-admin and a newly minted password is what follows the
  address. And **approving and declining now arrive back distinguishable**: core
  says nothing about which one it took, so a cancelled attempt used to look
  exactly like nothing having happened. The panel says what happened either way,
  including when WordPress refuses because this account is not allowed one — and
  it only offers the button when core would honour it, which is a question asked
  **per user**, not per site, since a role can be withheld from it.
- **Core's consent screen now says what the credential is for.** It asks for
  "access to your account", which is accurate and tells you nothing — it is one
  screen serving every application and cannot know what any of them intends.
  Zaplane adds a line to its own request saying that an AI client will read and
  build workflows and will not start one for real. Worded as what Zaplane will
  do with the credential, never as what the credential is limited to: an
  application password authenticates every REST route on the site, so capping
  MCP at read and write caps Zaplane, not the password. The panel used to blur
  that, next to the button that mints one, and now says it plainly in both
  places. The note appears only on Zaplane's own request; every other
  application's approval is untouched.
- **The panel no longer blanks when you change something on it.** Every save
  refetched the panel, and the panel hid itself while fetching — so ticking a
  checkbox made the whole screen vanish and rebuild. A setting that makes the
  screen disappear and come back reads as one that did not save. Only the first
  read shows a loading state now; the toggle also moves at once and rolls back
  with a message if the save fails, rather than waiting on a round trip to say
  anything.
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
- Primary buttons inside the AI access panel rendered as white text on a white
  card. `bg-[var(--…)]` computed transparent there — something in the admin beats
  the generated class — so **Issue token** and the new approval button were
  invisible rather than merely unstyled.
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

### Added — MCP server
- **`list_field_options`** resolves the values a config field can take on this
  site — which course, which product, which form, which CRM list — through the
  same lookup registry the workflow editor's own pickers use. **412 of the 1,070
  triggers and actions have a required field like that**, and `describe_app`
  cannot answer for them, so a client had no way to fill them in; 377 of those 412
  are now answerable, the rest being lookups already broken in their own
  integrations.
- **A value the site does not have is now rejected instead of saved.** Dynamic
  fields were skipped during validation because the options were unknown, so a
  guessed id saved clean and the workflow then never matched anything.
  `validate_graph` and `create_workflow` resolve the list and name both the tool
  to call and the values that would work. A lookup that cannot run, or one that
  comes back empty, stays a warning — a site with no courses yet must still be
  able to author against them.

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
