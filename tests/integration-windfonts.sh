#!/usr/bin/env bash
# wp-env / Studio: 4.0 Windfonts wp_head (family=, subset=full, no crossorigin).
# Usage: WP_CLI="npx wp-env run cli wp" bash tests/integration-windfonts.sh
#        WP_CLI="studio wp --path ~/Studio/wpcy-40" bash tests/integration-windfonts.sh
# Requires WPCY_KERNEL=v4 in wp-config (cannot be defined from wp eval).
set -euo pipefail

WP_CLI="${WP_CLI:-npx wp-env run cli wp}"

echo "==> WPCY_KERNEL=v4"
$WP_CLI eval '
if ( ! defined( "WPCY_KERNEL" ) || "v4" !== WPCY_KERNEL ) {
	throw new Exception( "WPCY_KERNEL is not v4; 4.0 Windfonts path not loaded" );
}
echo "kernel-v4\n";
'

echo "==> set modules.windfonts + fonts (avatar off so preconnect has no crossorigin)"
$WP_CLI eval '
$settings = get_option( "wpcy_settings", array() );
if ( ! is_array( $settings ) ) {
	$settings = array();
}
$settings["modules"]["windfonts"] = true;
$settings["integrations"]["windfonts"]["fonts"] = array(
	array(
		"family"   => "wenfeng-hcszt",
		"subset"   => "full",
		"selector" => "body",
		"enable"   => true,
	),
);
$settings["connectivity"]["avatar"] = "off";
$settings["recovery_mode"] = false;
update_option( "wpcy_settings", $settings );
echo "settings-ok\n";
'

echo "==> do_action wp_head (new process; module registered at bootstrap)"
HTML="$($WP_CLI eval '
ob_start();
do_action( "wp_head" );
$html = ob_get_clean();
if ( false === strpos( $html, "family=wenfeng-hcszt" ) || false === strpos( $html, "subset=full" ) || false !== strpos( $html, "crossorigin" ) ) {
	throw new Exception( "invalid Windfonts stylesheet output" );
}
echo $html;
')"

printf '%s\n' "$HTML"
echo "integration-windfonts.sh ok"
