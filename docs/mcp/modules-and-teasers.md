# Modules and feature discovery

## The module registry

Optional features are declared once, in `Zaplane\Settings::modules()`. Four
things read from it, each of which used to keep its own copy:

| Reader | Uses |
|---|---|
| `Settings::defaults()` | the on/off default for each module |
| `Settings::sanitize()` | which feature keys may be saved |
| `Settings::filter_admin_menu()` | the `menu` slug to hide while a module is off |
| Settings → Modules screen | title, description, `panel`, via `GET /zaplane/v1/modules` |

Before this, the dashboard's Modules screen kept a hardcoded `FEATURE_FIELDS`
array in JavaScript that mirrored the PHP by hand, so a new module meant editing
both and a menu rule in a third place.

### Adding a module

```php
'my_module' => [
    'key'         => 'my_module',
    'title'       => __( 'My Module', 'zaplane' ),
    'description' => __( 'What it does, and what turning it off costs.', 'zaplane' ),
    'default'     => false,        // every module is opt-in
    'menu'        => 'my-module',  // submenu slug suffix hidden while off; '' for none
    'panel'       => '',           // a settings tab that configures it; '' for none
    'apps'        => [ 'my_app' ], // integration slugs this module owns
    'since'       => '1.2.0',
],
```

That is the whole change. It appears on the Modules screen, is saveable, and
hides its menu entry when off. A module declaring a `panel` also gets a
**Configure →** link on its row, shown only once it is enabled.

`mcp_server` is a module like any other — the AI access tab is its panel.

## Feature teasers

A module added in a later release is invisible to the people who would benefit
most: someone who installed Zaplane for one workflow has no reason to reopen
Settings, and will not read a changelog. `Zaplane\Features\Teasers` puts a short,
dismissible card on the screen where the module would have been useful.

- **Workflows** → AI access, while MCP is off.
- **Connections** → Custom Apps, while nobody has built one.
- **Dashboard** → Business Knowledge, while nothing has been synced.

Every module is **opt-in**, so a teaser shows exactly while its module is off and
stops the moment it is switched on. Acting on the suggestion is also how it goes
away. `relevant` stays a callable rather than a bare flag check so a teaser can
ask a harder question than "is this on".

### In the builder

The more useful placement is the one where the user is already trying to use the
thing. No integration is hidden when its module is off — that would break
workflows already using it — so the builder will otherwise hand you a node from a
module you never enabled and say nothing.

`<Teaser app="knowledge" />` in the action drawer asks
`Teasers::for_app( $slug )`, which resolves the slug to its owning module via
`Settings::module_for_app()` (declared per module in `apps`, or resolved at
runtime for user-defined Custom Apps). When that module is off it returns a card
carrying `activates`, which turns the call to action into a **one-click switch**
rather than a link — nobody should have to abandon a half-built workflow to go
and flip something in Settings. That card is not dismissible: it describes the
node in front of you, not a suggestion to file away.

> **Watch the `.then()` here.** `window.ZaplaneGlobal.settings` is frozen, and the
> bundle runs in strict mode, so writing to it throws. When that write lived
> inside the success handler alongside a broad `.catch()`, activation succeeded
> server-side while the card claimed it had failed. Keep post-request work out of
> the success path, or use the two-argument `then()` so only a failed request can
> be treated as one.

## Upgrades

Custom Apps and Business Knowledge used to default to **on**. Settings are stored
sparsely, so a site that never opened the Modules screen has no saved value for
them — flipping the default would silently switch off features people are using
and make their menus disappear on update.

`Installer::pin_module_state()` runs once. On an existing install (detected by
`zaplane_first_install_time`) any module without an explicit saved value is
pinned to what it used to resolve to; a fresh install gets the opt-in defaults
untouched. An explicit `false` is respected either way.

Relatedly, `Settings::sanitize()` now bases a save on what is currently in
effect rather than on the defaults. It used to start from `defaults()`, so a
partial save — activating one module from a teaser — reset every key the caller
did not mention. That was harmless while the defaults were `true`; with opt-in
defaults it would have switched modules off.

Dismissals are stored per user (`zaplane_dismissed_teasers` user meta), so one
administrator hiding a card does not hide it for colleagues. At most one teaser
shows per screen, and a predicate that throws hides its own teaser rather than
taking the screen down.

### Adding a teaser

```php
add_filter( 'zaplane/feature_teasers', function ( $teasers ) {
    $teasers['my_teaser'] = [
        'key'       => 'my_teaser',
        'screen'    => 'recipes',            // matches <Teaser screen="recipes" />
        'module'    => 'my_module',
        'title'     => __( 'Short, specific claim', 'zaplane' ),
        'body'      => __( 'Two sentences on what it does for them.', 'zaplane' ),
        'cta_label' => __( 'Turn it on', 'zaplane' ),
        'cta_panel' => 'modules',            // settings tab the CTA opens
        'relevant'  => static fn(): bool => ! Settings::feature_enabled( 'my_module' ),
    ];
    return $teasers;
} );
```

Render it with `<Teaser screen="recipes" />`. The component asks the server what
belongs on that screen and renders nothing when the answer is nothing, so no
frontend change is needed to add or retire one.

The CTA links to `admin.php?page=zaplane-settings&tab=<cta_panel>`; the Settings
screen reads `?tab=` and opens that panel directly.

## Endpoints

| Route | Purpose |
|---|---|
| `GET /zaplane/v1/modules` | the registry, for the Modules screen |
| `GET /zaplane/v1/teasers?screen=<screen>` | the teaser for one screen, or `null` |
| `GET /zaplane/v1/teasers` | every visible teaser, keyed by screen |
| `GET /zaplane/v1/teasers?app=<slug>` | the prompt for a node whose module is off, or `null` |
| `POST /zaplane/v1/teasers/<key>/dismiss` | hide one for the current user |
| `POST /zaplane/v1/modules/<key>/activate` | switch one module on, for inline activation |

All require `manage_options`.
