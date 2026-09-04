<?php
/**
 * Compatibility report collector (format 2.1). Ported from 3.x Site Health.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Telemetry;

use WenPai\ChinaYes\Config\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the always-on compatibility report. No user-facing switch.
 */
final class Report {

	/**
	 * Payload format version. Keep the 3.x constant value.
	 *
	 * @since 4.0.0
	 */
	public const TELEMETRY_VERSION = '2.1';

	/**
	 * PHP extensions included in the platform block.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const CRITICAL_PHP_EXTENSIONS = array(
		'gd',
		'imagick',
		'curl',
		'mbstring',
		'xml',
		'zip',
		'openssl',
		'mysqli',
		'pdo_mysql',
		'intl',
		'json',
		'sodium',
		'exif',
		'fileinfo',
		'iconv',
	);

	/**
	 * Config repository used for wpcy_site_identity.site_uuid.
	 *
	 * PHP 7.4 has no union property types.
	 *
	 * @var Repository|null
	 */
	private $config;

	/**
	 * Request-local count_users() result. Avoids a second usermeta scan.
	 *
	 * PHP 7.4 has no union property types.
	 *
	 * @var array<string, mixed>|null
	 */
	private $user_counts;

	/**
	 * Create a collector.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository|null $config Identity source. Null leaves site_uuid empty in tests.
	 */
	public function __construct( $config = null ) {
		$this->config      = $config instanceof Repository ? $config : null;
		$this->user_counts = null;
	}

	/**
	 * Collect the 2.1 report. Always includes site_url. WooCommerce key only when active.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		$report = array(
			'site_uuid'         => $this->site_uuid(),
			'site_url'          => function_exists( 'home_url' ) ? home_url() : '',
			'wp_version'        => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
			'php_version'       => PHP_VERSION,
			'mysql_version'     => $this->get_mysql_version(),
			'is_multisite'      => function_exists( 'is_multisite' ) ? is_multisite() : false,
			'active_theme'      => function_exists( 'get_stylesheet' ) ? get_stylesheet() : '',
			'locale'            => function_exists( 'get_locale' ) ? get_locale() : '',
			'server_software'   => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $this->sanitize_server_software( $_SERVER['SERVER_SOFTWARE'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by sanitize_server_software().
			'wpcy_version'      => defined( 'CHINA_YES_VERSION' ) ? CHINA_YES_VERSION : 'unknown',
			'telemetry_version' => self::TELEMETRY_VERSION,
			'plugins'           => $this->get_plugin_list(),
			'platform'          => $this->get_platform_info(),
			'themes'            => $this->get_theme_list(),
			'translations'      => $this->get_translations(),
		);

		$wc_data = $this->get_woocommerce_data();
		if ( null !== $wc_data ) {
			$report['woocommerce'] = $wc_data;
		}

		return $report;
	}

	/**
	 * Site UUID from wpcy_site_identity.site_uuid.
	 *
	 * @since 4.0.0
	 */
	private function site_uuid(): string {
		if ( $this->config instanceof Repository ) {
			$identity = $this->config->get_identity();
			return isset( $identity['site_uuid'] ) ? (string) $identity['site_uuid'] : '';
		}
		return '';
	}

	/**
	 * Sanitize SERVER_SOFTWARE.
	 *
	 * @param mixed $value Raw server value.
	 */
	private function sanitize_server_software( $value ): string {
		$text = is_string( $value ) ? $value : '';
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $text );
		}
		return trim( strip_tags( $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- unit bootstrap has no WordPress.
	}

	/**
	 * MySQL / MariaDB version string.
	 *
	 * @since 4.0.0
	 */
	private function get_mysql_version(): string {
		global $wpdb;
		if ( ! $wpdb ) {
			return '';
		}
		$version = $wpdb->get_var( 'SELECT VERSION()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- version probe, no cache table.
		return $version ? (string) $version : '';
	}

	/**
	 * Installed plugins with metadata. Author tags stripped.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	private function get_plugin_list(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			if ( defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			return array();
		}

		$all_plugins    = get_plugins();
		$active_plugins = function_exists( 'get_option' ) ? get_option( 'active_plugins', array() ) : array();
		$network_active = array();
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			$network_active = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );
		}
		$list = array();

		foreach ( $all_plugins as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			$list[] = array(
				'slug'           => $slug,
				'version'        => isset( $data['Version'] ) ? $data['Version'] : '',
				'active'         => in_array( $file, $active_plugins, true ),
				'name'           => isset( $data['Name'] ) ? $data['Name'] : '',
				'author'         => isset( $data['Author'] ) ? $this->strip_tags_text( $data['Author'] ) : '',
				'requires_wp'    => isset( $data['RequiresWP'] ) ? $data['RequiresWP'] : '',
				'requires_php'   => isset( $data['RequiresPHP'] ) ? $data['RequiresPHP'] : '',
				'network_active' => in_array( $file, $network_active, true ),
			);
		}

		return $list;
	}

	/**
	 * Strip HTML from a plugin/theme author string.
	 *
	 * @param mixed $value Raw author.
	 */
	private function strip_tags_text( $value ): string {
		$text = is_string( $value ) ? $value : '';
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return wp_strip_all_tags( $text );
		}
		return trim( strip_tags( $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- unit bootstrap has no WordPress.
	}

	/**
	 * Platform block.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_platform_info(): array {
		$memory_limit = (string) ini_get( 'memory_limit' );
		$post_max     = (string) ini_get( 'post_max_size' );

		return array(
			'os'                     => PHP_OS,
			'bits'                   => PHP_INT_SIZE * 8,
			'php_memory_limit'       => $memory_limit,
			'php_max_input_vars'     => (int) ini_get( 'max_input_vars' ),
			'php_post_max_size'      => $post_max,
			'php_max_execution_time' => (int) ini_get( 'max_execution_time' ),
			'php_extensions'         => $this->get_php_extensions(),
			'is_ssl'                 => function_exists( 'is_ssl' ) ? is_ssl() : false,
			'image_support'          => $this->get_image_support(),
			'myisam_tables'          => $this->count_myisam_tables(),
			'users_count'            => $this->get_users_count(),
			'blogs_count'            => ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_blog_count' ) )
				? (int) get_blog_count()
				: 1,
		);
	}

	/**
	 * Critical PHP extensions that are loaded.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{name: string, version: string}>
	 */
	private function get_php_extensions(): array {
		$loaded = get_loaded_extensions();
		$result = array();

		foreach ( self::CRITICAL_PHP_EXTENSIONS as $ext ) {
			if ( in_array( $ext, $loaded, true ) ) {
				$ver      = phpversion( $ext );
				$result[] = array(
					'name'    => $ext,
					'version' => false !== $ver ? $ver : '',
				);
			}
		}

		return $result;
	}

	/**
	 * Image format support flags.
	 *
	 * @since 4.0.0
	 *
	 * @return array{webp: bool, avif: bool, heic: bool, jxl: bool}
	 */
	private function get_image_support(): array {
		$support = array(
			'webp' => false,
			'avif' => false,
			'heic' => false,
			'jxl'  => false,
		);

		if ( function_exists( 'gd_info' ) ) {
			$gd = gd_info();
			if ( ! empty( $gd['WebP Support'] ) ) {
				$support['webp'] = true;
			}
			if ( ! empty( $gd['AVIF Support'] ) ) {
				$support['avif'] = true;
			}
		}

		if ( class_exists( 'Imagick' ) ) {
			try {
				$formats = \Imagick::queryFormats();
				if ( in_array( 'WEBP', $formats, true ) ) {
					$support['webp'] = true;
				}
				if ( in_array( 'AVIF', $formats, true ) ) {
					$support['avif'] = true;
				}
				if ( in_array( 'HEIC', $formats, true ) ) {
					$support['heic'] = true;
				}
				if ( in_array( 'JXL', $formats, true ) ) {
					$support['jxl'] = true;
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		return $support;
	}

	/**
	 * Count MyISAM tables in the current schema.
	 *
	 * @since 4.0.0
	 */
	private function count_myisam_tables(): int {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		if ( ! defined( 'DB_NAME' ) ) {
			return 0;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND ENGINE = 'MyISAM'",
				DB_NAME
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- schema probe.

		return $count ? (int) $count : 0;
	}

	/**
	 * User total, cached in a transient for a day.
	 *
	 * @since 4.0.0
	 */
	private function get_users_count(): int {
		$count = function_exists( 'get_transient' ) ? get_transient( 'wpcy_users_count' ) : false;
		if ( false === $count ) {
			$counts = $this->count_users_once();
			$count  = isset( $counts['total_users'] ) ? (int) $counts['total_users'] : 0;
			if ( function_exists( 'set_transient' ) && defined( 'DAY_IN_SECONDS' ) ) {
				set_transient( 'wpcy_users_count', $count, DAY_IN_SECONDS );
			}
		}
		return (int) $count;
	}

	/**
	 * Run count_users() once per collect().
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function count_users_once(): array {
		if ( null === $this->user_counts ) {
			$this->user_counts = function_exists( 'count_users' ) ? count_users() : array();
		}
		return $this->user_counts;
	}

	/**
	 * Installed themes.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	private function get_theme_list(): array {
		$themes_data = function_exists( 'get_transient' ) ? get_transient( 'wpcy_themes_cache' ) : false;
		if ( false !== $themes_data && is_array( $themes_data ) ) {
			return $themes_data;
		}

		if ( ! function_exists( 'wp_get_themes' ) ) {
			return array();
		}

		$all_themes   = wp_get_themes();
		$active_theme = function_exists( 'get_stylesheet' ) ? get_stylesheet() : '';
		$list         = array();

		foreach ( $all_themes as $slug => $theme ) {
			$parent = $theme->parent();
			$list[] = array(
				'slug'           => $slug,
				'name'           => $theme->get( 'Name' ),
				'version'        => $theme->get( 'Version' ),
				'author'         => $this->strip_tags_text( $theme->get( 'Author' ) ),
				'is_active'      => ( $slug === $active_theme ),
				'is_child_theme' => ( false !== $parent ),
				'parent_slug'    => $parent ? $parent->get_stylesheet() : '',
				'is_block_theme' => $theme->is_block_theme(),
			);
		}

		if ( function_exists( 'set_transient' ) && defined( 'DAY_IN_SECONDS' ) ) {
			set_transient( 'wpcy_themes_cache', $list, DAY_IN_SECONDS );
		}

		return $list;
	}

	/**
	 * Installed translations, capped at 500 rows.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{type: string, slug: string, language: string, version: string}>
	 */
	private function get_translations(): array {
		if ( ! function_exists( 'wp_get_installed_translations' ) ) {
			return array();
		}

		$result = array();
		$types  = array( 'plugins', 'themes', 'core' );

		foreach ( $types as $type ) {
			$translations = wp_get_installed_translations( $type );
			foreach ( $translations as $slug => $languages ) {
				foreach ( $languages as $lang => $data ) {
					$result[] = array(
						'type'     => rtrim( $type, 's' ),
						'slug'     => $slug,
						'language' => $lang,
						'version'  => isset( $data['PO-Revision-Date'] ) ? $data['PO-Revision-Date'] : '',
					);
				}
			}
		}

		if ( count( $result ) > 500 ) {
			$result = array_slice( $result, 0, 500 );
		}

		return $result;
	}

	/**
	 * WooCommerce aggregates. Null when WooCommerce is not active.
	 *
	 * Country only in base_location. No order bodies, emails, or keys.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_woocommerce_data() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return null;
		}

		global $wpdb;
		if ( ! $wpdb ) {
			return null;
		}

		$data = array(
			'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'base_location'  => '',
			'is_ssl'         => function_exists( 'is_ssl' ) ? is_ssl() : false,
			'hpos_enabled'   => $this->is_hpos_enabled(),
			'allow_tracking' => function_exists( 'get_option' ) && get_option( 'woocommerce_allow_tracking', 'no' ) === 'yes',
		);

		if ( function_exists( 'wc_get_base_location' ) ) {
			$location              = wc_get_base_location();
			$data['base_location'] = isset( $location['country'] ) ? $location['country'] : '';
		}

		$data['products_total']   = 0;
		$data['products_by_type'] = array();

		$product_counts = $wpdb->get_results(
			"SELECT t.slug, COUNT(*) as cnt
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_type'
			 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			 WHERE p.post_type = 'product' AND p.post_status = 'publish'
			 GROUP BY t.slug"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- aggregate counts, table names from $wpdb.

		if ( $product_counts ) {
			foreach ( $product_counts as $row ) {
				$data['products_by_type'][ $row->slug ] = (int) $row->cnt;
				$data['products_total']                += (int) $row->cnt;
			}
		}

		$data['orders_total']     = 0;
		$data['orders_by_status'] = array();

		$orders_table = $wpdb->prefix . 'wc_orders';
		if ( $data['hpos_enabled'] && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence probe.
			$order_counts = $wpdb->get_results(
				"SELECT status, COUNT(*) as cnt FROM {$wpdb->prefix}wc_orders GROUP BY status"
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- aggregate counts.
		} else {
			$order_counts = $wpdb->get_results(
				"SELECT post_status as status, COUNT(*) as cnt
				 FROM {$wpdb->posts}
				 WHERE post_type = 'shop_order'
				 GROUP BY post_status"
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- aggregate counts.
		}

		if ( $order_counts ) {
			foreach ( $order_counts as $row ) {
				$data['orders_by_status'][ $row->status ] = (int) $row->cnt;
				$data['orders_total']                    += (int) $row->cnt;
			}
		}

		$data['gateways'] = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
			foreach ( WC()->payment_gateways->payment_gateways() as $gateway ) {
				$data['gateways'][] = array(
					'id'      => $gateway->id,
					'enabled' => 'yes' === $gateway->enabled,
				);
			}
		}

		$data['shipping_methods'] = array();
		if ( function_exists( 'WC' ) && WC()->shipping ) {
			foreach ( WC()->shipping->get_shipping_methods() as $method ) {
				$data['shipping_methods'][] = array(
					'id'      => $method->id,
					'enabled' => 'yes' === $method->enabled,
				);
			}
		}

		$data['user_roles']           = $this->get_user_role_distribution();
		$data['template_overrides']   = $this->get_wc_template_overrides();
		$data['block_cart']           = $this->page_has_block( 'woocommerce/cart', 'woocommerce_cart_page_id' );
		$data['block_checkout']       = $this->page_has_block( 'woocommerce/checkout', 'woocommerce_checkout_page_id' );
		$data['calc_taxes']           = function_exists( 'get_option' ) && get_option( 'woocommerce_calc_taxes', 'no' ) === 'yes';
		$data['coupons_enabled']      = function_exists( 'get_option' ) && get_option( 'woocommerce_enable_coupons', 'yes' ) === 'yes';
		$data['guest_checkout']       = function_exists( 'get_option' ) && get_option( 'woocommerce_enable_guest_checkout', 'yes' ) === 'yes';
		$data['shipping_zones_count'] = $this->count_shipping_zones();

		return $data;
	}

	/**
	 * Role => count, cached a day.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, int>
	 */
	private function get_user_role_distribution(): array {
		$cached = function_exists( 'get_transient' ) ? get_transient( 'wpcy_user_roles' ) : false;
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$user_count = $this->count_users_once();
		$roles      = array();

		if ( isset( $user_count['avail_roles'] ) && is_array( $user_count['avail_roles'] ) ) {
			foreach ( $user_count['avail_roles'] as $role => $count ) {
				if ( $count > 0 ) {
					$roles[ $role ] = (int) $count;
				}
			}
		}

		if ( function_exists( 'set_transient' ) && defined( 'DAY_IN_SECONDS' ) ) {
			set_transient( 'wpcy_user_roles', $roles, DAY_IN_SECONDS );
		}

		return $roles;
	}

	/**
	 * WooCommerce template files overridden by the active theme. Capped at 100.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	private function get_wc_template_overrides(): array {
		if ( ! function_exists( 'WC' ) || ! function_exists( 'get_stylesheet_directory' ) ) {
			return array();
		}

		$template_path = WC()->plugin_path() . '/templates/';
		$theme_path    = get_stylesheet_directory() . '/woocommerce/';

		if ( ! is_dir( $theme_path ) ) {
			return array();
		}

		$overrides = array();
		$iterator  = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $theme_path, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			if ( $file->getExtension() !== 'php' ) {
				continue;
			}
			$relative = str_replace( $theme_path, '', $file->getPathname() );
			if ( file_exists( $template_path . $relative ) ) {
				$overrides[] = $relative;
			}
		}

		if ( count( $overrides ) > 100 ) {
			$overrides = array_slice( $overrides, 0, 100 );
		}

		return $overrides;
	}

	/**
	 * Whether a WooCommerce page uses a given block. False when has_block() is missing.
	 *
	 * @since 4.0.0
	 *
	 * @param string $block_name Block name.
	 * @param string $option_key Page id option.
	 */
	private function page_has_block( string $block_name, string $option_key ): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		$page_id = (int) get_option( $option_key, 0 );
		if ( $page_id < 1 ) {
			return false;
		}

		$post = function_exists( 'get_post' ) ? get_post( $page_id ) : null;
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}

		return has_block( $block_name, $post );
	}

	/**
	 * Shipping zone count.
	 *
	 * @since 4.0.0
	 */
	private function count_shipping_zones(): int {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		$table = $wpdb->prefix . 'woocommerce_shipping_zones';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence probe.
			return 0;
		}

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- count on a probed table name.

		return $count ? (int) $count : 0;
	}

	/**
	 * WooCommerce HPOS flag.
	 *
	 * @since 4.0.0
	 */
	private function is_hpos_enabled(): bool {
		if ( ! class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) ) {
			return false;
		}
		try {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		} catch ( \Throwable $e ) {
			unset( $e );
			return false;
		}
	}
}
