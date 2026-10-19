<?php
/**
 * Uninstall: drop provider options, transients and first-run wizard state.
 * Does not touch wp_china_yes.
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
 * Delete the one-time wizard redirect flag for the current blog.
 *
 * @since 4.0.0
 *
 * @return void
 */
function wpcy_uninstall_onboarding(): void {
	delete_option( 'wpcy_onboarding_redirected' );
}

wpcy_uninstall_providers();
wpcy_uninstall_onboarding();

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
		wpcy_uninstall_onboarding();
		restore_current_blog();
	}
}
