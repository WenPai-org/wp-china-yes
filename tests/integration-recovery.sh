#!/usr/bin/env bash
# wp-env / Studio: recovery page form POST sets recovery_mode.
# Usage: WP_CLI="npx wp-env run cli wp" bash tests/integration-recovery.sh
#        WP_CLI="studio wp --path ~/Studio/wpcy-40" bash tests/integration-recovery.sh
set -euo pipefail

WP_CLI="${WP_CLI:-npx wp-env run cli wp}"

echo "==> POST recovery disable_rewrites via PHP (no JS)"
$WP_CLI eval '
$repo    = new \WenPai\ChinaYes\Config\Repository();
$actions = new \WenPai\ChinaYes\Rest\RecoveryActions( $repo );
$result  = $actions->apply( \WenPai\ChinaYes\Rest\RecoveryActions::DISABLE_REWRITES );
if ( true !== $result ) {
	throw new Exception( "disable_rewrites failed" );
}
echo "applied\n";
'

echo "==> wp option get wpcy_settings --format=json"
OPTION_RAW="$($WP_CLI option get wpcy_settings --format=json)"
printf '%s\n' "$OPTION_RAW" | python3 -c '
import json, sys
raw = sys.stdin.read()
start = raw.find("{")
if start < 0:
    raise SystemExit("stdout has no JSON object")
data = json.loads(raw[start:])
if data.get("recovery_mode") is not True:
    raise SystemExit("recovery_mode is not true: " + json.dumps(data.get("recovery_mode")))
print("recovery_mode:true")
'

echo "integration-recovery.sh ok"
