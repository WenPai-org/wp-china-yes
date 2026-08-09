#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

find "$ROOT" -type f -name '*.php' \
  -not -path "$ROOT/vendor/*" \
  -not -path "$ROOT/.git/*" \
  -print0 | sort -z | xargs -0 -n1 php -l >/dev/null

for test_file in "$ROOT"/tests/test-*.php; do
  php "$test_file"
done

echo "All PHP syntax and standalone tests passed."
