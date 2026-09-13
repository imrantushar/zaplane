# Cross-platform release build

One command produces a shippable Zaplane zip on **Linux, macOS and Windows** — no
bash, `zip`, `rsync` or `wp dist-archive` required.

```bash
npm run dist          # full release: JS build → composer (no-dev) → POT → zip
npm run dist:zip      # only repackage the current tree
npm run dist:dev      # keep dev dependencies (debug build)

# single steps / skips
node build-tools/build.mjs --only=js|composer|pot|zip
node build-tools/build.mjs --skip-composer
node build-tools/build.mjs --no-download   # fail rather than fetch the wp-cli phar
```

## What each step does

| # | Step | How |
|---|------|-----|
| 1 | Integration manifest | `wp zaplane build:integration` → `assets/json/integrations.json` |
| 2 | Production JS | `wp-scripts build`; the npm install is skipped when `node_modules` already exists |
| 3 | Composer | `install --no-dev --optimize-autoloader`. Strauss is off — Zaplane does not prefix namespaces |
| 4 | POT | `wp i18n make-pot` → `languages/zaplane.pot` |
| 5 | Zip | built in Node from `.distignore` |

The manifest runs first so the zip packages a freshly generated catalogue rather
than whatever was last committed.

## The manifest must not describe the machine that built it

`build:integration` reads the live integration registry, so two things about the
build machine used to leak into a file that ships to every site:

* **Per-site state.** StoreEngine marks its subscription, membership, affiliate
  and multi-vendor capabilities `disabled` by asking whether that addon is active
  *right now*. Frozen into the catalogue, a build with the addon on hid the
  warning from customers without it, and a build with it off greyed the
  capability out for customers who had it. The command now drops `disabled` and
  `disabled_reason`, keeping `requires_addon` — "this needs addon X" is true
  everywhere; whether X is on is the site's own question.
* **The hostname.** Fillout, Jotform and Typeform put a full webhook URL in a
  `copy` field for the user to paste into the provider. Built from `rest_url()`,
  the shipped catalogue carried `http://kodezen.local/...`, so every customer was
  told to paste a developer's laptop address into their form. The build swaps the
  REST root for `{{ZAPLANE_REST_URL}}` and each site swaps its own back in — the
  same treatment `webhook_route` already had.

Both are asserted by the build: regenerating on any machine should change nothing
but `generated_at`.

The zip lands in the parent `plugins/` folder as `zaplane.<version>.zip`
(`outputDir: ".."` in [`build.config.json`](../build.config.json)).

## Two things to know

**Run `composer install` when you're done.** Step 2 strips dev dependencies, so
phpunit and phpcs are gone from `vendor/` until you reinstall. The old `build.sh`
restored them from an EXIT trap; this does not, because a release build should
leave the tree in the state it packaged. `npm run dist:dev` keeps them.

**`vendor/` ships, and must.** `.distignore` deliberately does not exclude it:
`zaplane.php` requires `vendor/autoload.php` and Action Scheduler from it. The zip step is
written in Node partly for this reason — `wp dist-archive` drops
`vendor/autoload.php` when the tree contains symlinks. There is a guard: the build
refuses to package a `vendor/` that still holds dev packages (`zip.strictVendor`).

## Why not the old scripts

`build.sh` and `scripts/build-zip.sh` are bash-only and use BSD `sed -i ''`, which
fails on Linux. They also shell out to `wp dist-archive`. Both still work on macOS
and are left in place; `npm run dist` is the portable path.

## POT and JavaScript

`make-pot` scans the JS **source** in `dev_zaplane/`, which is what you want in a
POT. If JSON translations are ever added for the front end, note that
`wp i18n make-json` keys its files by an md5 of the *registered script path* —
`assets/build/app.js` — so the POT would have to be generated from the built
bundle instead, or the handles will not match and JS strings will never load.
StoreEngine solves this with a custom `bin/make-pot.mjs`.
