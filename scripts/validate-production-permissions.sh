#!/usr/bin/env bash
set -euo pipefail

build="${1:?Usage: validate-production-permissions.sh <release>}"/public/build
test -d "$build"
find "$build" -type d ! -perm -005 -print -quit | grep -q . && { echo "Unreadable build directory" >&2; exit 1; } || true
find "$build" -type f ! -perm -004 -print -quit | grep -q . && { echo "Unreadable build asset" >&2; exit 1; } || true
echo "Public build permissions are readable."
