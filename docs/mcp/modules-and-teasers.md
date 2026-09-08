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

## Feature discovery

Modules are opt-in, so a fresh site has every optional feature off and no reason
to discover any of them: someone who installed Zaplane for one workflow will not
reopen Settings and will not read a changelog. `Zaplane\Features\Teasers`
answers that in two places, for two different moments.

### Browsing — the module card

`Teasers::spotlight( $screen )` returns **every module that is off**, in one
card, each row with a one-click switch. Showing them together rather than one at
a time is the point: the question a new user has is "what does this thing do",
not "should I enable this particular feature".

The module that matters most on the current screen leads the list and is marked
**Useful here** — context without hiding the rest. That mapping is ordering only,
and is filterable:

```php
add_filter( 'zaplane/module_screen_relevance', function ( $map ) {
    $map['recipes'] = 'my_module';
    return $map;
} );
```

Rendered with `<Teaser screen="workflows" />` on Workflows, the Dashboard and
Connections.

**Dismissal records which modules were on offer at the time.** A module shipped
in a later release therefore surfaces on its own, rather than being buried by a
dismissal that predates it — and without dragging back the ones already waved
away. Dismissals are per user, so one administrator hiding the card does not hide
it for colleagues.

### Acting — the builder prompt

The more useful placement, because the user is already trying to use the thing.
No integration is hidden when its module is off — that would break workflows
already using one — so the builder would otherwise hand you a node from a module
you never enabled and say nothing.

`<Teaser app="knowledge" />` in the action drawer asks `Teasers::for_app( $slug )`,
which resolves the slug to its owning module via `Settings::module_for_app()`
(declared per module in `apps`, or resolved at runtime for user-defined Custom
Apps). It carries `activates`, so the call to action is a **switch, not a link** —
nobody should have to abandon a half-built workflow to go and flip something in
Settings. It is not dismissible: it describes the node in front of you, not a
suggestion to file away.

> **Watch the `.then()` here.** `window.ZaplaneGlobal.settings` is frozen, and the
> bundle runs in strict mode, so writing to it throws. When that write lived
> inside the success handler alongside a broad `.catch()`, activation succeeded
> server-side while the card claimed it had failed. Keep post-request work out of
> the success path, or use the two-argument `then()` so only a failed request is
> treated as one.

### Colour

The card derives its surface, border, accent and rules from
`var(--zaplane-primary)` through `color-mix`, so it reads as one colour at
different strengths and follows the palette into dark mode. An earlier version
paired the neutral `--zaplane-border-color` with a blue-tinted background; two
unrelated hues at similar lightness look muddy. `color-mix` is used rather than
Tailwind's `/opacity` syntax, which does not work against a CSS custom property.

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
