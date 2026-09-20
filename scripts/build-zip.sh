#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# Zaplane release build — produces a clean, installable plugin zip.
#
# Usage:
#   scripts/build-zip.sh            # build at the current version
#   scripts/build-zip.sh 1.2.3      # bump version to 1.2.3, then build
#
# What it does (and why), modelled on the GemCRM build:
#   1. (optional) bump the version everywhere it lives.
#   2. Build production JS/CSS assets (npm run build).
#   3. Swap vendor/ to production-only deps (--no-dev) so phpcs/phpunit/etc.
#      can never ship, with an optimised, authoritative classmap.
#   4. Move heavy/dev-only folders out of the tree before packaging. This both
#      speeds up wp dist-archive AND dodges its symlink bug: node_modules/.bin/*
#      symlinks push dist-archive onto a copy-to-temp path that can silently
#      DROP vendor/autoload.php from the archive — which would fatal the plugin
#      on load (zaplane.php requires it).
#   5. Package with wp dist-archive.
#   6. Guard: verify vendor/autoload.php made it into the zip; inject if not.
#
# A trap restores the heavy folders AND the dev Composer deps on any exit
# (success, failure, or Ctrl+C), so the working tree is left dev-ready.
# ─────────────────────────────────────────────────────────────────────────────

echo "🚀 Zaplane dist build starting…"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

PLUGIN_FILE="zaplane.php"
PLUGIN_SLUG="$(basename "$REPO_ROOT")"
VERSION="${1:-}"

if [ ! -f ".distignore" ]; then
  echo "❌ .distignore not found in $REPO_ROOT"
  exit 1
fi

# ── 1. Version bump (optional) ───────────────────────────────────────────────
# Portable in-place edits (perl works the same on macOS and Linux CI).
if [ -n "$VERSION" ]; then
  echo "🔖 Bumping version to $VERSION"
  perl -i -pe "s/^(\s\*\s*Version:\s*).*/\${1}${VERSION}/"                       "$PLUGIN_FILE"
  perl -i -pe "s/(define\(\s*'ZAPLANE_VERSION',\s*')[^']*('\s*\))/\${1}${VERSION}\${2}/" "$PLUGIN_FILE"
  [ -f README.txt ]   && perl -i -pe "s/^(Stable tag:\s*).*/\${1}${VERSION}/"    README.txt || true
  [ -f package.json ] && perl -i -pe "s/(\"version\":\s*\")[^\"]*(\")/\${1}${VERSION}\${2}/" package.json || true
  echo "   ✓ version updated in $PLUGIN_FILE, README.txt, package.json"
fi

CURRENT_VERSION="$(perl -ne "print \$1 if /define\(\s*'ZAPLANE_VERSION',\s*'([^']+)'/" "$PLUGIN_FILE")"
ARCHIVE_PATH="$REPO_ROOT/../${PLUGIN_SLUG}.${CURRENT_VERSION}.zip"
echo "   Building ${PLUGIN_SLUG} ${CURRENT_VERSION}"

# ── 2. Production JS/CSS assets ──────────────────────────────────────────────
echo "🎨 Building production assets (npm run build)…"
if [ ! -d node_modules ]; then
  echo "   Installing npm dependencies…"
  npm ci --no-audit --no-fund
fi
npm run build

# ── 3. Production Composer deps (no dev) ─────────────────────────────────────
PROD_VENDOR=0
if command -v composer >/dev/null 2>&1; then
  echo "📦 Installing production Composer dependencies (--no-dev)…"
  composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction
  PROD_VENDOR=1
else
  echo "⚠️  composer not found — packaging vendor/ as-is (may include dev packages)."
fi

# ── 4. Move heavy/dev-only folders aside ─────────────────────────────────────
HEAVY_DIRS=(
  "node_modules"
  "dev_zaplane"
  "docs"
  "recipes-test"
  ".claude"
)
BACKUP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/zaplane-dist-XXXXXX")"

restore() {
  echo "♻️  Restoring dev environment…"
  for dir in "${HEAVY_DIRS[@]}"; do
    if [ -d "$BACKUP_DIR/$dir" ]; then
      mv "$BACKUP_DIR/$dir" "$REPO_ROOT/" || echo "⚠️  could not restore $dir"
    fi
  done
  rm -rf "$BACKUP_DIR"

  # Put dev Composer deps back so the tree stays dev-ready. Best-effort — a
  # network hiccup here must not fail the build; the zip is already written.
  if [ "$PROD_VENDOR" = "1" ]; then
    echo "♻️  Restoring dev Composer dependencies…"
    rm -rf vendor
    composer install --no-interaction || echo "⚠️  run 'composer install' manually to restore dev deps."
  fi
  echo "✅ Restore complete"
}
trap restore EXIT INT TERM

echo "📁 Moving heavy folders out of the tree…"
for dir in "${HEAVY_DIRS[@]}"; do
  if [ -d "$dir" ]; then
    # Refuse anything that isn't a simple, in-tree relative folder name.
    case "$dir" in
      /*|*..*|"") echo "❌ refusing to move unexpected path: '$dir'"; exit 1 ;;
    esac
    if ! mv "$dir" "$BACKUP_DIR/"; then
      echo "   mv failed for $dir — falling back to copy+remove"
      cp -a "$dir" "$BACKUP_DIR/"
      rm -rf "$dir"
    fi
  fi
done

# ── 5. Package ───────────────────────────────────────────────────────────────
echo "🗜  Running wp dist-archive…"
# Remove any stale archive first so dist-archive never prompts to overwrite
# (its --force flag isn't available in every dist-archive-command release).
rm -f "$ARCHIVE_PATH"
# Invoke dist-archive directly rather than pre-checking with `wp help` /
# `wp cli has-command` — those can report a non-zero exit even when the command
# itself runs fine. If dist-archive isn't installed, the call fails loudly here.
if command -v wp >/dev/null 2>&1; then
  wp dist-archive .
elif [ -f "$REPO_ROOT/../wp-cli-nightly.phar" ]; then
  php "$REPO_ROOT/../wp-cli-nightly.phar" dist-archive .
else
  echo "❌ wp-cli not found. Install it, plus: wp package install wp-cli/dist-archive-command"
  exit 1
fi

# ── 6. Guard: vendor/autoload.php must be in the archive ─────────────────────
ARCHIVE="$(ls -t "$REPO_ROOT/.."/"${PLUGIN_SLUG}".*.zip 2>/dev/null | head -1)"
if [ -n "$ARCHIVE" ] && [ -f "$REPO_ROOT/vendor/autoload.php" ]; then
  echo "🔎 Verifying vendor/autoload.php in $ARCHIVE…"
  if unzip -l "$ARCHIVE" | grep -q "${PLUGIN_SLUG}/vendor/autoload.php"; then
    echo "   ✔ vendor/autoload.php present"
  else
    echo "   ⚠️  vendor/autoload.php missing — injecting"
    ( cd "$REPO_ROOT/.." && zip -q "$ARCHIVE" "${PLUGIN_SLUG}/vendor/autoload.php" )
    echo "   ✅ injected"
  fi
fi

echo "🎉 Build complete → ${ARCHIVE:-$ARCHIVE_PATH}"
