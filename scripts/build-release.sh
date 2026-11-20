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

# Verify PHP kit and lightweight native admin JS are present.
if [[ ! -f "$ROOT/lib/wenpai-admin-ui/php/load.php" ]]; then
	echo "missing vendored kit: lib/wenpai-admin-ui/php/load.php" >&2
	exit 1
fi
if [[ ! -f "$ROOT/assets/js/wpcy-admin.js" ]]; then
	echo "missing admin script: assets/js/wpcy-admin.js" >&2
	exit 1
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
  --exclude 'prototypes/' \
  --exclude 'AGENTS.md' \
  --exclude '.grok-context/' \
  --exclude 'vendor/' \
  --exclude 'packages/' \
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
if ! printf '%s\n' "$CONTENTS" | grep -q '^wp-china-yes/lib/wenpai-admin-ui/php/load.php$'; then
	echo "release archive missing wp-china-yes/lib/wenpai-admin-ui/php/load.php" >&2
	exit 1
fi
if ! printf '%s\n' "$CONTENTS" | grep -q '^wp-china-yes/assets/js/wpcy-admin.js$'; then
	echo "release archive missing wp-china-yes/assets/js/wpcy-admin.js" >&2
	exit 1
fi
if printf '%s\n' "$CONTENTS" | grep -Eq '(^wp-china-yes/\.git(/|$)|/tests/|/docs/|/prototypes/|/src/Admin/app/|/packages/)'; then
	echo "release archive contains excluded paths (.git, tests/, docs/, prototypes/, src/Admin/app/, or packages/)" >&2
	exit 1
fi
if printf '%s\n' "$CONTENTS" | grep -Eq '(^wp-china-yes/(framework|Service|client|templates)/|^wp-china-yes/(Plugin|helpers|autoload-guard)\.php$|yahnis-elsts)'; then
	echo "release archive contains deleted 3.x paths" >&2
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
