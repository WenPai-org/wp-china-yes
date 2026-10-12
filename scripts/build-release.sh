#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(sed -n 's/^ \* Version: //p' "$ROOT/wp-china-yes.php" | head -1)"
BUILD_ROOT="${BUILD_ROOT:-$(mktemp -d)}"
PACKAGE_DIR="$BUILD_ROOT/wp-china-yes"
ARTIFACT_DIR="${ARTIFACT_DIR:-$ROOT/dist}"
PACKAGE_ARCHIVE="$BUILD_ROOT/wp-china-yes-$VERSION.zip"
FINAL_ARCHIVE="$ARTIFACT_DIR/wp-china-yes-$VERSION.zip"

mkdir -p "$PACKAGE_DIR" "$ARTIFACT_DIR"
rsync -a "$ROOT/" "$PACKAGE_DIR/" \
  --exclude '.git' \
  --exclude '.gitignore' \
  --exclude '.github/' \
  --exclude '.codex/' \
  --exclude '.grok-context/' \
  --exclude 'AGENTS.md' \
  --exclude '.wp-env.json' \
  --exclude 'dist/' \
  --exclude 'tests/' \
  --exclude 'scripts/' \
  --exclude 'docs/' \
  --exclude 'vendor/' \
  --exclude 'node_modules/' \
  --exclude 'package.json' \
  --exclude 'package-lock.json'

# composer.json and composer.lock are build inputs, not runtime plugin files.
composer install \
  --working-dir "$PACKAGE_DIR" \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction \
  --no-progress

rm -f "$PACKAGE_DIR/composer.json" "$PACKAGE_DIR/composer.lock"

find "$PACKAGE_DIR" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

( cd "$BUILD_ROOT" && zip -qr "$PACKAGE_ARCHIVE" wp-china-yes )
mv -f "$PACKAGE_ARCHIVE" "$FINAL_ARCHIVE"
echo "$FINAL_ARCHIVE"
