<?php
/**
 * Uninstall: drop provider options and transients. Does not touch wp_china_yes.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ids = array(
	'weixiaoduo-mall',
	'wenpai-marketplace',
);

$purge = static function ( array $ids ): void {
	delete_option( 'wpcy_providers' );
	foreach ( $ids as $id ) {
		delete_option( 'wpcy_secure_provider_' . $id . '_license_key' );
		delete_option( 'wpcy_secure_provider_' . $id . '_instance' );
		delete_transient( 'wpcy_provider_' . $id . '_products' );
	}
};

$purge( $ids );

if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_sites' ) ) {
	$sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $sites as $site_id ) {
		switch_to_blog( (int) $site_id );
		$purge( $ids );
		restore_current_blog();
	}
}
