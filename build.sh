#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────
# Zaplane build script
# Usage:
#   ./build.sh              # build with current version
#   ./build.sh 1.2.3        # build and bump version to 1.2.3
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

# ── 2. Remove vendor ──────────────────────────
echo "→ Removing vendor/"
rm -rf vendor

# ── 3. Production composer install ───────────
echo "→ Running composer install (no-dev)"
composer install --no-dev --optimize-autoloader --no-interaction

# ── 4. Create zip ─────────────────────────────
echo "→ Creating dist archive"
php ../wp-cli-nightly.phar dist-archive ./

# ── 5. Restore dev vendor ─────────────────────
echo "→ Restoring vendor/ with dev packages"
rm -rf vendor
composer install --no-interaction

echo ""
echo "✅ Build complete!"
