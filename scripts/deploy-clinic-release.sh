#!/usr/bin/env bash
set -euo pipefail

# Run on Clinic only. The release must already contain a complete public/build.
root="${1:?Usage: deploy-clinic-release.sh <clinic-root> <release-id>}"
release_id="${2:?Usage: deploy-clinic-release.sh <clinic-root> <release-id>}"
release="$root/releases/$release_id"
php_bin="${PHP_BIN:-/opt/alt/php84/usr/bin/php}"

test -f "$release/artisan"
php "$release/scripts/validate-vite-release.php" "$release"
"$release/scripts/validate-production-permissions.sh" "$release"
ln -sfn "$root/shared/storage" "$release/storage"
ln -sfn "$root/shared/.env" "$release/.env"
"$php_bin" "$release/artisan" about >/dev/null
"$php_bin" "$release/artisan" route:list >/dev/null
"$php_bin" "$release/artisan" config:cache
"$php_bin" "$release/artisan" route:cache
"$php_bin" "$release/artisan" view:cache

# Preserve prior chunks for clients holding older HTML, then switch the two roots together.
cp -al "$root/public_html/build" "$root/public_html/build.previous-$release_id" 2>/dev/null || true
mv "$root/public_html/build" "$root/public_html/build.rollback-$release_id"
cp -a "$release/public/build" "$root/public_html/build.next-$release_id"
mv "$root/public_html/build.next-$release_id" "$root/public_html/build"
ln -sfn "$release" "$root/app.next"
mv -Tf "$root/app.next" "$root/app"
