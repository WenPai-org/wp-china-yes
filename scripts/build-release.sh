#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(sed -n 's/^ \* Version: //p' "$ROOT/wp-china-yes.php" | head -1)"
BUILD_ROOT="${BUILD_ROOT:-$(mktemp -d)}"
PACKAGE_DIR="$BUILD_ROOT/wp-china-yes"
ARTIFACT_DIR="${ARTIFACT_DIR:-$ROOT/dist}"
PACKAGE_ARCHIVE="$BUILD_ROOT/wp-china-yes-$VERSION.zip"
FINAL_ARCHIVE="$ARTIFACT_DIR/wp-china-yes-$VERSION.zip"
SHA_FILE="$ARTIFACT_DIR/wp-china-yes-$VERSION.zip.sha256"

if [[ -z "$VERSION" ]]; then
	echo "could not read Version: from wp-china-yes.php" >&2
	exit 1
fi

# Frontend assets: build in the source tree before rsync so build/ is packed.
# Skip npm when CI (or a previous step) already produced build/index.js.
if [[ -f "$ROOT/package.json" ]]; then
	if [[ ! -f "$ROOT/build/index.js" ]]; then
		( cd "$ROOT" && npm ci && npm run build )
	fi
	if [[ ! -f "$ROOT/build/index.js" ]]; then
		echo "frontend build did not produce build/index.js" >&2
		exit 1
	fi
fi

mkdir -p "$PACKAGE_DIR" "$ARTIFACT_DIR"
rsync -a "$ROOT/" "$PACKAGE_DIR/" \
  --exclude '.git' \
  --exclude '.gitignore' \
  --exclude '.github/' \
  --exclude '.codex/' \
  --exclude '.wp-env.json' \
  --exclude 'dist/' \
  --exclude 'tests/' \
  --exclude 'scripts/' \
  --exclude 'docs/' \
  --exclude 'AGENTS.md' \
  --exclude '.grok-context/' \
  --exclude 'vendor/' \
  --exclude 'node_modules/' \
  --exclude 'src/Admin/app/' \
  --exclude 'webpack.config.js' \
  --exclude '.eslintrc.js' \
  --exclude '.eslintignore' \
  --exclude '.prettierrc.js' \
  --exclude '.prettierignore' \
  --exclude '.editorconfig' \
  --exclude '.nvmrc' \
  --exclude 'phpcs.xml.dist' \
  --exclude 'phpstan.neon.dist' \
  --exclude 'phpunit.xml.dist' \
  --exclude '.grok-context/' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude '.gitkeep'

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

CONTENTS="$(unzip -Z1 "$FINAL_ARCHIVE")"
if ! printf '%s\n' "$CONTENTS" | grep -q '^wp-china-yes/build/index.js$'; then
	echo "release archive missing wp-china-yes/build/index.js" >&2
	exit 1
fi
if printf '%s\n' "$CONTENTS" | grep -Eq '(^wp-china-yes/\.git(/|$)|/tests/|/docs/|/src/Admin/app/)'; then
	echo "release archive contains excluded paths (.git, tests/, docs/, or src/Admin/app/)" >&2
	exit 1
fi

if command -v sha256sum >/dev/null 2>&1; then
	SUM_LINE="$(sha256sum "$FINAL_ARCHIVE")"
else
	SUM_LINE="$(shasum -a 256 "$FINAL_ARCHIVE")"
fi
HASH="${SUM_LINE%% *}"
printf '%s  wp-china-yes-%s.zip\n' "$HASH" "$VERSION" > "$SHA_FILE"

echo "$FINAL_ARCHIVE"
echo "$SHA_FILE"
echo "$HASH"
