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
    'default'     => false,
    'menu'        => 'my-module',  // submenu slug suffix hidden while off; '' for none
    'panel'       => '',           // a settings tab that configures it; '' for none
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

**Relevance is a predicate, not an on/off check.** Two of the three modules ship
enabled, so gating on "module is off" would have meant their prompts never
appeared. What makes them worth mentioning is that the module is available and
unused — so `relevant` is a callable, and a teaser retires itself once the thing
it suggests has actually happened. Acting on the suggestion is also how it goes
away.

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
| `POST /zaplane/v1/teasers/<key>/dismiss` | hide one for the current user |

All require `manage_options`.
