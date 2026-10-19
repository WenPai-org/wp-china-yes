<?php
/**
 * Uninstall: drop provider options and transients, and the apps index negative cache. Does not touch wp_china_yes.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete provider options for the current blog.
 *
 * @since 4.0.0
 *
 * @return void
 */
function wpcy_uninstall_providers(): void {
	$wpcy_ids = array(
		'weixiaoduo-mall',
		'wenpai-marketplace',
	);

	delete_option( 'wpcy_providers' );
	foreach ( $wpcy_ids as $wpcy_id ) {
		delete_option( 'wpcy_secure_provider_' . $wpcy_id . '_license_key' );
		delete_option( 'wpcy_secure_provider_' . $wpcy_id . '_instance' );
		delete_transient( 'wpcy_provider_' . $wpcy_id . '_products' );
	}
}

/**
 * Delete the apps index negative cache for the current blog.
 *
 * Same name as WenPai\ChinaYes\Apps\Index::NEGATIVE_TRANSIENT_KEY (autoload is not loaded here).
 *
 * @since 4.0.0
 *
 * @return void
 */
function wpcy_uninstall_apps_index_negative(): void {
	delete_transient( 'wpcy_apps_index_negative' );
}

wpcy_uninstall_providers();
wpcy_uninstall_apps_index_negative();

if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_sites' ) ) {
	$wpcy_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wpcy_sites as $wpcy_site_id ) {
		switch_to_blog( (int) $wpcy_site_id );
		wpcy_uninstall_providers();
		wpcy_uninstall_apps_index_negative();
		restore_current_blog();
	}
}
