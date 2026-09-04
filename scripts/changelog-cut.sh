#!/usr/bin/env bash
# Cut CHANGELOG.md 「未发布」 into a dated version section.
# Usage: bash scripts/changelog-cut.sh <version> [YYYY-MM-DD]
# Leaves an empty 「未发布」 heading. Does not tag, push, or publish.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-}"
DATE="${2:-$(date +%F)}"
CHANGELOG="$ROOT/CHANGELOG.md"

if [[ -z "$VERSION" ]]; then
	echo "usage: $0 <version> [YYYY-MM-DD]" >&2
	echo "example: $0 4.0.0 2026-09-04" >&2
	exit 1
fi

VERSION="${VERSION#v}"

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
	echo "invalid version: $VERSION" >&2
	exit 1
fi

if [[ ! "$DATE" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
	echo "invalid date: $DATE (expected YYYY-MM-DD)" >&2
	exit 1
fi

if [[ ! -f "$CHANGELOG" ]]; then
	echo "missing $CHANGELOG" >&2
	exit 1
fi

HEADING="## v${VERSION} - ${DATE}"

if grep -qE "^## v${VERSION}( |$)" "$CHANGELOG"; then
	echo "CHANGELOG.md already has a section for v${VERSION}" >&2
	exit 1
fi

python3 - "$CHANGELOG" "$HEADING" <<'PY'
import sys

path, heading = sys.argv[1], sys.argv[2]
text = open(path, encoding="utf-8").read()
marker = "## 未发布"
idx = text.find(marker)
if idx < 0:
    sys.stderr.write("CHANGELOG.md has no 「未发布」 heading\n")
    sys.exit(1)

after = idx + len(marker)
rest = text[after:]
# Body of Unreleased is everything until the next heading at column 0.
next_h = rest.find("\n## ")
if next_h < 0:
    body = rest
    tail = ""
else:
    body = rest[: next_h + 1]
    tail = rest[next_h + 1 :]

body_stripped = body.strip("\n")
if body_stripped:
    version_block = "\n\n" + heading + "\n" + body_stripped + "\n\n"
else:
    version_block = "\n\n" + heading + "\n\n"

new = text[:idx] + marker + "\n" + version_block + tail
if not new.endswith("\n"):
    new += "\n"
open(path, "w", encoding="utf-8").write(new)
PY

echo "cut $HEADING from 「未发布」 in CHANGELOG.md"
