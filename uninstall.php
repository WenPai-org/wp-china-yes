<?php
/**
 * Uninstall: drop provider options and transients, first-run wizard state,
 * dismissed announcements, the apps index negative cache and status, and the
 * caches of signature-verified documents (apps index, element hide, notice
 * control, announcements).
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

// The plugin is not loaded during uninstall. Its autoloader is only needed to
// compute the versioned cache names in wpcy_uninstall_signed_caches().
$wpcy_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $wpcy_autoload ) ) {
	require_once $wpcy_autoload;
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

/**
 * Delete the dismissed announcements option for the current blog.
 *
 * Same name as WenPai\ChinaYes\Admin\Announcements\AnnouncementsModule::DISMISSED_OPTION
 * (autoload is not loaded here).
 *
 * @since 4.0.0
 *
 * @return void
 */
function wpcy_uninstall_announcements_dismissed(): void {
	delete_option( 'wpcy_announcements_dismissed' );
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

/**
 * Delete caches of signature-verified documents for the current blog.
 *
 * Base names are the TRANSIENT_KEY constants of Index, ElementHideModule,
 * NoticeControlModule and AnnouncementsModule. A transient under a bare base
 * name is a cache from an older release. The live names
 * (`<base>_<CacheKey::VERSION>_<trust fingerprint>`) are computed with the
 * plugin's classes when the autoloader is present; caches under any other
 * fingerprint are never read and expire on their TTL.
 *
 * @since 4.0.0
 *
 * @return void
 */
function wpcy_uninstall_signed_caches(): void {
	$bases = array(
		'wpcy_apps_index',
		'wpcy_element_hide_rules',
		'wpcy_notice_rules',
		'wpcy_announcements',
	);
	foreach ( $bases as $base ) {
		delete_transient( $base );
	}
	delete_transient( 'wpcy_apps_index_status' );

	if ( ! class_exists( 'WenPai\\ChinaYes\\Apps\\CacheKey' ) || ! class_exists( 'WenPai\\ChinaYes\\Apps\\ManifestVerifier' ) ) {
		return;
	}
	$verifier = new \WenPai\ChinaYes\Apps\ManifestVerifier();
	foreach ( $bases as $base ) {
		delete_transient( \WenPai\ChinaYes\Apps\CacheKey::for( $base, $verifier ) );
	}
}

wpcy_uninstall_providers();
wpcy_uninstall_onboarding();
wpcy_uninstall_announcements_dismissed();
wpcy_uninstall_apps_index_negative();
wpcy_uninstall_signed_caches();

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
		wpcy_uninstall_announcements_dismissed();
		wpcy_uninstall_apps_index_negative();
		wpcy_uninstall_signed_caches();
		restore_current_blog();
	}
}
