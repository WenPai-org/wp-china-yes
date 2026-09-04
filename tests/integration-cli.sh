#!/usr/bin/env bash
# wp-env / Studio: wp wpcy status|doctor JSON and Site Health section.
# Usage: WP_CLI="npx wp-env run cli wp" bash tests/integration-cli.sh
#        WP_CLI="studio wp --path ~/Studio/wpcy-40" bash tests/integration-cli.sh
set -euo pipefail

WP_CLI="${WP_CLI:-npx wp-env run cli wp}"

json_from_wp_out() {
	python3 -c '
import json, sys
raw = sys.stdin.read()
start = raw.find("{")
if start < 0:
    raise SystemExit("stdout has no JSON object")
data = json.loads(raw[start:])
if not isinstance(data.get("targets"), list):
    raise SystemExit("JSON missing targets array")
print("keys:", ",".join(sorted(data.keys())))
print("targets:", len(data["targets"]))
print("kernel:", data.get("kernel"))
print("recovery_mode:", data.get("recovery_mode"))
'
}

echo "==> wp wpcy status --format=json"
STATUS_RAW="$($WP_CLI wpcy status --format=json)"
printf '%s\n' "$STATUS_RAW" | json_from_wp_out

echo "==> wp wpcy doctor"
set +e
DOCTOR_RAW="$($WP_CLI wpcy doctor 2>&1)"
DOCTOR_CODE=$?
set -e
echo "doctor exit: $DOCTOR_CODE"
if [ "$DOCTOR_CODE" -ne 0 ] && [ "$DOCTOR_CODE" -ne 1 ]; then
	printf '%s\n' "$DOCTOR_RAW"
	echo "doctor exit must be 0 or 1"
	exit 1
fi
printf '%s\n' "$DOCTOR_RAW" | json_from_wp_out

echo "==> Site Health 文派叶子 section"
$WP_CLI eval '
$checker = new \WenPai\ChinaYes\Diagnostics\Checker();
$health  = new \WenPai\ChinaYes\Diagnostics\SiteHealth( $checker );
$info    = $health->add_debug_info( array() );
if ( ! isset( $info["wp-china-yes"] ) ) {
	throw new Exception( "missing 文派叶子 Site Health section" );
}
$section = $info["wp-china-yes"];
if ( "文派叶子" !== $section["label"] ) {
	throw new Exception( "wrong Site Health label: " . $section["label"] );
}
$blob = wp_json_encode( $section );
if ( false !== strpos( $blob, "遥测" ) ) {
	throw new Exception( "Site Health section contains forbidden copy" );
}
echo "site-health-ok\n";
'

echo "integration-cli.sh ok"
