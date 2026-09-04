#!/usr/bin/env bash
# wp-env / Studio: recovery page form POST sets recovery_mode.
# Usage: WP_CLI="npx wp-env run cli wp" bash tests/integration-recovery.sh
#        WP_CLI="studio wp --path ~/Studio/wpcy-40" bash tests/integration-recovery.sh
set -euo pipefail

WP_CLI="${WP_CLI:-npx wp-env run cli wp}"

echo "==> activate plugin"
$WP_CLI plugin activate wp-china-yes >/dev/null

echo "==> POST ?page=wpcy-recovery via handle_post (nonce + \$_POST, no JS)"
# handle_post calls wp_safe_redirect then exit. Returning false from wp_redirect
# stops headers; a shutdown function prints after apply() so eval is observable.
POST_RAW="$($WP_CLI eval '
$users = get_users( array( "role" => "administrator", "number" => 1 ) );
if ( empty( $users ) ) {
	throw new Exception( "no administrator" );
}
wp_set_current_user( $users[0]->ID );
$_POST = array(
	"wpcy_recovery_action" => "disable_rewrites",
	"wpcy_recovery_nonce"  => wp_create_nonce( "wpcy_recovery_disable_rewrites" ),
);
add_filter(
	"wp_redirect",
	static function ( $location ) {
		echo "redirect:" . $location . "\n";
		return false;
	}
);
register_shutdown_function(
	static function () {
		echo "handle_post_shutdown\n";
	}
);
$repo = new \WenPai\ChinaYes\Config\Repository();
$page = new \WenPai\ChinaYes\Admin\RecoveryPage( new \WenPai\ChinaYes\Rest\RecoveryActions( $repo ) );
$page->handle_post();
echo "handle_post_returned_without_exit\n";
')"
printf '%s\n' "$POST_RAW"
if printf '%s\n' "$POST_RAW" | grep -q 'handle_post_returned_without_exit'; then
	echo "handle_post returned without redirect+exit"
	exit 1
fi

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
