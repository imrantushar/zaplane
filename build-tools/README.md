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
| 1 | Production JS | `wp-scripts build`; the npm install is skipped when `node_modules` already exists |
| 2 | Composer | `install --no-dev --optimize-autoloader`. Strauss is off — Zaplane does not prefix namespaces |
| 3 | POT | `wp i18n make-pot` → `languages/zaplane.pot` |
| 4 | Zip | built in Node from `.distignore` |

The zip lands in the parent `plugins/` folder as `zaplane.<version>.zip`
(`outputDir: ".."` in [`build.config.json`](../build.config.json)).

## Two things to know

**Run `composer install` when you're done.** Step 2 strips dev dependencies, so
phpunit and phpcs are gone from `vendor/` until you reinstall. The old `build.sh`
restored them from an EXIT trap; this does not, because a release build should
leave the tree in the state it packaged. `npm run dist:dev` keeps them.

**`vendor/` ships, and must.** `.distignore` deliberately does not exclude it: the
StoreEngine licensing SDK resolves its own critical path through
`vendor/autoload.php`, and a zip without it cannot self-update. The zip step is
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
