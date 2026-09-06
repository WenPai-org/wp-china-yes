#!/usr/bin/env bash
# wp-env / Studio: 4.0 Windfonts wp_head (family=, subset=full, no crossorigin).
# Usage: WP_CLI="npx wp-env run cli wp" bash tests/integration-windfonts.sh
#        WP_CLI="studio wp --path ~/Studio/wpcy-40" bash tests/integration-windfonts.sh
set -euo pipefail

WP_CLI="${WP_CLI:-npx wp-env run cli wp}"

echo "==> activate plugin"
$WP_CLI plugin activate wp-china-yes >/dev/null

echo "==> 4.0 kernel"
$WP_CLI eval '
if ( ! class_exists( "WenPai\\ChinaYes\\Core\\Plugin" ) ) {
	throw new Exception( "Core\\Plugin is not loaded" );
}
echo "kernel-4.0\n";
'

echo "==> set modules.windfonts + fonts + active entitlement (avatar off)"
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
set_transient(
	"wpcy_entitlements",
	array(
		"fetched_at"   => gmdate( "Y-m-d\TH:i:s\Z" ),
		"entitlements" => array(
			array(
				"id"      => "wpcy-leaf-windfonts-ci",
				"service" => "windfonts",
				"status"  => "active",
			),
		),
	),
	3600
);
echo "settings-ok\n";
'

echo "==> do_action wp_head (new process; module registered at bootstrap)"
HTML="$($WP_CLI eval '
ob_start();
do_action( "wp_head" );
$html = ob_get_clean();
if ( false === strpos( $html, "family=wenfeng-hcszt" ) ) {
	throw new Exception( "missing family=wenfeng-hcszt in wp_head" );
}
if ( false === strpos( $html, "subset=full" ) ) {
	throw new Exception( "missing subset=full in wp_head" );
}
if ( preg_match_all( "/<(?:link|style)[^>]*(?:windfonts|wenfeng-hcszt)[^>]*>/i", $html, $matches ) ) {
	foreach ( $matches[0] as $tag ) {
		if ( false !== strpos( strtolower( $tag ), "crossorigin" ) ) {
			throw new Exception( "Windfonts tag has crossorigin: " . $tag );
		}
	}
}
echo $html;
')"

printf '%s\n' "$HTML"
echo "assert family=wenfeng-hcszt"
echo "assert subset=full"
echo "assert Windfonts tags have no crossorigin"
echo "integration-windfonts.sh ok"
