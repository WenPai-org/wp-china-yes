#!/usr/bin/env bash
# Sync the three 4.x version locations: plugin header, CHINA_YES_VERSION, package.json.
# Usage: bash scripts/sync-version.sh <version>
# Does not tag, push, or publish.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
	echo "usage: $0 <version>" >&2
	echo "example: $0 4.0.0" >&2
	exit 1
fi

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
	echo "invalid version: $VERSION" >&2
	exit 1
fi

PLUGIN="$ROOT/wp-china-yes.php"
PACKAGE="$ROOT/package.json"

if [[ ! -f "$PLUGIN" ]]; then
	echo "missing $PLUGIN" >&2
	exit 1
fi

if [[ ! -f "$PACKAGE" ]]; then
	echo "missing $PACKAGE (4.x requires package.json version)" >&2
	exit 1
fi

python3 - "$PLUGIN" "$PACKAGE" "$VERSION" <<'PY'
import re
import sys

plugin_path, package_path, version = sys.argv[1], sys.argv[2], sys.argv[3]

plugin = open(plugin_path, encoding="utf-8").read()
if not re.search(r"^ \* Version: ", plugin, re.M):
    sys.stderr.write("plugin header Version: not found\n")
    sys.exit(1)
if "define( 'CHINA_YES_VERSION'," not in plugin:
    sys.stderr.write("CHINA_YES_VERSION not found\n")
    sys.exit(1)

plugin, n_header = re.subn(
    r"^( \* Version: ).*$",
    r"\g<1>" + version,
    plugin,
    count=1,
    flags=re.M,
)
plugin, n_const = re.subn(
    r"define\( 'CHINA_YES_VERSION',\s*'[^']*' \)",
    "define( 'CHINA_YES_VERSION', '%s' )" % version,
    plugin,
    count=1,
)
if n_header != 1 or n_const != 1:
    sys.stderr.write("failed to rewrite plugin version fields\n")
    sys.exit(1)
open(plugin_path, "w", encoding="utf-8").write(plugin)

package = open(package_path, encoding="utf-8").read()
if re.search(r'"version"\s*:', package):
    package, n_pkg = re.subn(
        r'("version"\s*:\s*")[^"]*(")',
        r"\g<1>" + version + r"\g<2>",
        package,
        count=1,
    )
    if n_pkg != 1:
        sys.stderr.write("failed to rewrite package.json version\n")
        sys.exit(1)
else:
    package, n_pkg = re.subn(
        r'("name"\s*:\s*"[^"]*",)',
        r'\g<1>\n\t"version": "%s",' % version,
        package,
        count=1,
    )
    if n_pkg != 1:
        sys.stderr.write("failed to insert package.json version\n")
        sys.exit(1)
open(package_path, "w", encoding="utf-8").write(package)
PY

HEADER="$(sed -n 's/^ \* Version: //p' "$PLUGIN" | head -1)"
CONSTANT="$(sed -n "s/^define( 'CHINA_YES_VERSION', '\\(.*\\)' );/\\1/p" "$PLUGIN" | head -1)"
PKG="$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).version)' "$PACKAGE")"

if [[ "$HEADER" != "$VERSION" || "$CONSTANT" != "$VERSION" || "$PKG" != "$VERSION" ]]; then
	echo "version sync failed:" >&2
	echo "  header=$HEADER constant=$CONSTANT package.json=$PKG expected=$VERSION" >&2
	exit 1
fi

echo "synced version $VERSION"
echo "  wp-china-yes.php Version: $HEADER"
echo "  CHINA_YES_VERSION: $CONSTANT"
echo "  package.json version: $PKG"
