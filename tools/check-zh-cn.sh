#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for tool in wp msgfmt msgcmp; do
  command -v "$tool" >/dev/null || { echo "BLOCKED: missing $tool" >&2; exit 2; }
done
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
wp i18n make-pot . "$work/wp-china-yes.pot" \
  --domain=wp-china-yes \
  --exclude=.git,node_modules,vendor,lib/plugin-update-checker \
  --skip-js >/dev/null
msgfmt --check --check-format --output-file=/dev/null languages/wp-china-yes-zh_CN.po
msgcmp --use-fuzzy languages/wp-china-yes-zh_CN.po "$work/wp-china-yes.pot"

wp i18n make-pot . "$work/wp_china_yes.pot" \
  --domain=wp_china_yes \
  --exclude=.git,node_modules,vendor \
  --skip-js >/dev/null
msgfmt --check --check-format --output-file=/dev/null framework/languages/zh_CN.po
msgcmp --use-fuzzy framework/languages/zh_CN.po "$work/wp_china_yes.pot"
printf 'PASS: wp-china-yes zh_CN catalog covers the current wp-china-yes source catalog\n'
