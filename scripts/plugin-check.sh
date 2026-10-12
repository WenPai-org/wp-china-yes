#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="${1:-wp-china-yes}"
shift || true

if command -v wp >/dev/null 2>&1; then
  WP=(wp)
elif [[ -f /var/www/html/wp-cli.phar ]]; then
  WP=(php /var/www/html/wp-cli.phar)
else
  echo "wp-cli is required" >&2
  exit 127
fi

ROOT_ARGS=()
if [[ "$(id -u)" -eq 0 ]]; then
  ROOT_ARGS+=(--allow-root)
fi

# Self-hosted Update URI + established wp* brands: ignore only these two.
"${WP[@]}" plugin check "$PLUGIN_SLUG" \
  --mode=update \
  --ignore-codes=plugin_updater_detected,trademarked_term \
  --ignore-warnings \
  --exclude-directories=lib,tests,docs,scripts,vendor,node_modules,.git,.forgejo,.github \
  --format=strict-table \
  "${ROOT_ARGS[@]}" \
  "$@"
