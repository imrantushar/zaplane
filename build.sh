#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────
# Zaplane build script
# Usage:
#   ./build.sh              # build with current version
#   ./build.sh 1.2.3        # build and bump version to 1.2.3
#
# Produces a production zip whose vendor/ contains ONLY runtime
# dependencies (no dev packages), then restores the dev vendor/ so the
# working tree is left ready for development — even if the build fails.
# ─────────────────────────────────────────────

PLUGIN_FILE="zaplane.php"
VERSION="${1:-}"

# ── 1. Bump version (optional) ────────────────
if [[ -n "$VERSION" ]]; then
    echo "→ Bumping version to $VERSION"

    # Plugin header: * Version: x.x.x
    sed -i '' "s/^\( \* Version:\s*\).*/\1${VERSION}/" "$PLUGIN_FILE"

    # PHP constant: define( 'ZAPLANE_VERSION', 'x.x.x' )
    sed -i '' "s/\(define( 'ZAPLANE_VERSION', '\)[^']*\('\))/\1${VERSION}\2)/" "$PLUGIN_FILE"

    echo "   ✓ $PLUGIN_FILE updated"
fi

# Resolve the archive path (wp dist-archive defaults to one level above
# the project dir: ../<slug>.<version>.zip).
CURRENT_VERSION="$(sed -n "s/.*define( 'ZAPLANE_VERSION', '\([^']*\)'.*/\1/p" "$PLUGIN_FILE")"
ARCHIVE="../zaplane.${CURRENT_VERSION}.zip"

# ── 2. Build production assets ─────────────────
echo "→ Building production assets (npm run build)"
if [[ ! -d node_modules ]]; then
    echo "   Installing npm dependencies…"
    npm ci --no-audit --no-fund
fi
npm run build

# Always restore dev dependencies on exit, so a failed build never leaves
# the working tree without phpunit/phpcs/etc.
build_succeeded=0
restore_dev_vendor() {
    echo ""
    echo "→ Restoring vendor/ with dev packages"
    rm -rf vendor
    composer install --no-interaction
    if [[ "$build_succeeded" == "1" ]]; then
        echo ""
        echo "✅ Build complete! → ${ARCHIVE}"
    else
        echo ""
        echo "⚠️  Build did not finish — dev dependencies were restored." >&2
    fi
}
trap restore_dev_vendor EXIT

# ── 3. Remove vendor ──────────────────────────
echo "→ Removing vendor/"
rm -rf vendor

# ── 4. Production composer install (no dev deps) ──
echo "→ Running composer install (no-dev)"
composer install --no-dev --optimize-autoloader --no-interaction

# ── 5. Create zip ─────────────────────────────
echo "→ Creating dist archive"
# Drop any stale archive so dist-archive never prompts to overwrite.
rm -f "$ARCHIVE"
if command -v wp >/dev/null 2>&1 && wp help dist-archive >/dev/null 2>&1; then
    wp dist-archive ./
elif [[ -f ../wp-cli-nightly.phar ]]; then
    php ../wp-cli-nightly.phar dist-archive ./
else
    echo "✗ No wp-cli with the dist-archive command was found." >&2
    echo "  Install it with: wp package install wp-cli/dist-archive-command" >&2
    exit 1
fi

build_succeeded=1
# Dev vendor/ is restored by the EXIT trap above.
