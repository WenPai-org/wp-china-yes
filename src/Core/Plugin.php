<?php
/**
 * 4.0 kernel bootstrap: config → registry → scene register().
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

use WenPai\ChinaYes\Admin\AdminModule;
use WenPai\ChinaYes\Admin\Announcements\AnnouncementsModule;
use WenPai\ChinaYes\Admin\ElementHide\ElementHideModule;
use WenPai\ChinaYes\Admin\NoticeControl\NoticeControlModule;
use WenPai\ChinaYes\Apps\AppsModule;
use WenPai\ChinaYes\Apps\CachedEntitlements;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Connectivity\Avatar\AvatarModule;
use WenPai\ChinaYes\Connectivity\DashboardFeeds\DashboardFeedsModule;
use WenPai\ChinaYes\Connectivity\Heartbeat\HeartbeatModule;
use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Connectivity\PublicAssets\AssetMap;
use WenPai\ChinaYes\Connectivity\PublicAssets\PublicAssetsModule;
use WenPai\ChinaYes\Connectivity\WordPressOrg\MirrorProbe;
use WenPai\ChinaYes\Connectivity\WordPressOrg\WordPressOrgModule;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Diagnostics\DiagnosticsModule;
use WenPai\ChinaYes\Diagnostics\SiteHealth;
use WenPai\ChinaYes\Integrations\Windfonts\Catalog;
use WenPai\ChinaYes\Integrations\Windfonts\WindfontsModule;
use WenPai\ChinaYes\Migration\LegacyReader;
use WenPai\ChinaYes\Migration\Runner;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Privacy\SiteBlocklist\SiteBlocklistModule;
use WenPai\ChinaYes\Providers\UpdateBridge;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WenPai\ChinaYes\Stats\Counters;
use WenPai\ChinaYes\Stats\Events;
use WenPai\ChinaYes\Stats\StatsModule;
use WenPai\ChinaYes\Telemetry\TelemetryModule;

/**
 * 4.0 kernel. The only bootstrap path after 3.x was removed.
 */
final class Plugin {

	/**
	 * Service locator.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Module host.
	 *
	 * @var ModuleRegistry
	 */
	private ModuleRegistry $registry;

	/**
	 * Current request scene.
	 *
	 * @var Environment
	 */
	private Environment $environment;

	/**
	 * Wire a kernel instance.
	 *
	 * @param Container      $container   Service locator.
	 * @param ModuleRegistry $registry    Module host.
	 * @param Environment    $environment Request scene.
	 */
	public function __construct( Container $container, ModuleRegistry $registry, Environment $environment ) {
		$this->container   = $container;
		$this->registry    = $registry;
		$this->environment = $environment;
	}

	/**
	 * Default wiring and scene boot. Called from wp-china-yes.php after autoload.
	 */
	public static function boot(): void {
		self::maybe_migrate_from_legacy();
		self::load_textdomain();
		self::load_cli();

		$plugin = self::create();
		$plugin->register_lifecycle_hooks();
		$plugin->run();
	}

	/**
	 * First boot: read `wp_china_yes` into 4.0 settings when the 4.0 option is absent.
	 *
	 * "Not yet migrated" means `wpcy_settings` (single site) or
	 * `wpcy_network_settings` (multisite) is missing (`get_option`/`get_site_option`
	 * returns false) and `wp_china_yes` exists. Does not write `wp_china_yes`.
	 * A damaged non-array legacy option is treated as empty by LegacyReader.
	 * Runner failures are logged and left unwritten so the next boot retries.
	 *
	 * @since 4.0.0
	 */
	public static function maybe_migrate_from_legacy(): void {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}

		$network = function_exists( 'is_multisite' ) && is_multisite();
		$option  = $network ? Schema::NETWORK_SETTINGS : Schema::SETTINGS;
		$stored  = $network
			? ( function_exists( 'get_site_option' ) ? get_site_option( $option, false ) : false )
			: get_option( $option, false );

		if ( false !== $stored ) {
			return;
		}

		$reader = new LegacyReader();
		if ( ! $reader->exists() ) {
			return;
		}

		try {
			( new Runner() )->execute();
		} catch ( \Throwable $e ) {
			( new Logger() )->log(
				'warning',
				sprintf(
					'First-boot legacy migration failed (%s): %s',
					get_class( $e ),
					$e->getMessage()
				),
				array( 'exception' => $e )
			);
		}
	}

	/**
	 * Load the plugin text domain from languages/.
	 *
	 * @since 4.0.0
	 */
	private static function load_textdomain(): void {
		if ( ! function_exists( 'load_plugin_textdomain' ) || ! defined( 'CHINA_YES_PLUGIN_FILE' ) ) {
			return;
		}

		$relative = 'wp-china-yes/languages';
		if ( function_exists( 'plugin_basename' ) ) {
			$relative = dirname( plugin_basename( CHINA_YES_PLUGIN_FILE ) ) . '/languages';
		}

		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- self-hosted languages/*.mo; not a WordPress.org plugin (M4-01).
		load_plugin_textdomain( 'wp-china-yes', false, $relative );
	}

	/**
	 * Register WP-CLI commands. Composer autoload.files is empty, so boot loads this.
	 *
	 * @since 4.0.0
	 */
	private static function load_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		$path = ( defined( 'CHINA_YES_PLUGIN_PATH' ) ? CHINA_YES_PLUGIN_PATH : dirname( __DIR__, 2 ) . '/' ) . 'src/Cli/wp-cli.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * Build a kernel with Repository config, connectivity, REST, and admin.
	 */
	public static function create(): self {
		$container   = new Container();
		$environment = Environment::detect();
		$scope       = Scope::detect();
		$logger      = new Logger();
		$config      = new Repository( $logger );
		$registry    = new ModuleRegistry( $config, $environment, $logger );

		$container->set( 'config', $config );
		$container->set( 'environment', $environment );
		$container->set( 'scope', $scope );
		$container->set( 'logger', $logger );
		$container->set( 'registry', $registry );

		$counters = new Counters( $config, $logger );
		$events   = new Events( $config );
		$container->set( 'stats.counters', $counters );
		$container->set( 'stats.events', $events );
		$registry->add( new StatsModule( $counters, $events ) );

		$probe  = new MirrorProbe();
		$health = new MirrorHealth();
		$container->set( 'wordpress_org.probe', $probe );
		$container->set( 'mirror_health', $health );
		$registry->add( new WordPressOrgModule( $probe, null, null, $health ) );

		$map = new AssetMap();
		$container->set( 'public_assets.map', $map );
		$registry->add( new PublicAssetsModule( $config, $map, $health ) );

		$registry->add( new AvatarModule( $config ) );
		$registry->add( new HeartbeatModule( $config ) );
		$registry->add( new DashboardFeedsModule( $config ) );
		$catalog = new Catalog();
		$container->set( 'windfonts.catalog', $catalog );
		$registry->add( new WindfontsModule( $config ) );
		$registry->add( new TelemetryModule( $config, $logger ) );
		$registry->add( new DataResidencyModule( null, false, $config ) );
		$registry->add( new SiteBlocklistModule( $config ) );

		$checker = new Checker( null, null, null, $config, null, $events );
		$container->set( 'diagnostics.checker', $checker );
		$registry->add( new DiagnosticsModule( $config, $checker, new SiteHealth( $checker ) ) );
		$registry->add( new SiteBindingModule( $config, $logger ) );
		$entitlements = new EntitlementsModule( $config, $logger );
		$registry->add( new AppsModule( null, null, new CachedEntitlements( $entitlements ), null, $logger ) );
		$registry->add( new RestModule( $config, $checker, null, $counters, $events ) );
		$registry->add( new AdminModule( $config ) );
		$registry->add( $entitlements );
		$registry->add( new NoticeControlModule( $config, self::filtered_source( 'wpcy_notice_rules_source' ), null, $logger ) );
		$registry->add( new AnnouncementsModule( $config, self::filtered_source( 'wpcy_announcements_source' ) ) );
		$registry->add( new ElementHideModule( $config, self::filtered_source( 'wpcy_element_hide_source' ), null, $logger ) );
		$registry->add( new UpdateBridge( $config, null, null, $logger ) );

		return new self( $container, $registry, $environment );
	}

	/**
	 * Register modules for the current scene.
	 */
	public function run(): void {
		$this->registry->boot( $this->environment->context() );
	}

	/**
	 * Service locator.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Module host.
	 */
	public function registry(): ModuleRegistry {
		return $this->registry;
	}

	/**
	 * Activation: write installed_at per site; do not write the 3.x option wp_china_yes.
	 */
	public static function activate(): void {
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_sites' ) && function_exists( 'switch_to_blog' ) && function_exists( 'restore_current_blog' ) ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $id ) {
				switch_to_blog( (int) $id );
				self::maybe_write_installed_at();
				restore_current_blog();
			}
			return;
		}

		self::maybe_write_installed_at();
	}

	/**
	 * Write wpcy_installed_at once, UTC ISO 8601, autoload=false.
	 *
	 * @since 4.0.0
	 */
	private static function maybe_write_installed_at(): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}
		$existing = get_option( 'wpcy_installed_at', '' );
		if ( is_string( $existing ) && '' !== $existing ) {
			return;
		}
		update_option( 'wpcy_installed_at', gmdate( 'Y-m-d\TH:i:s\Z' ), false );
	}

	/**
	 * Optional document source from a filter. Empty disables production fetch.
	 *
	 * @since 4.0.0
	 *
	 * @param string $tag Filter name.
	 */
	private static function filtered_source( string $tag ): string {
		if ( ! function_exists( 'apply_filters' ) ) {
			return '';
		}
		$filtered = apply_filters( $tag, '' );
		return is_string( $filtered ) ? $filtered : '';
	}

	/**
	 * Clear the daily compatibility-report cron. Same hook name as 3.x.
	 */
	public static function deactivate(): void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( TelemetryModule::CRON_HOOK );
		}
	}

	/**
	 * Activation / deactivation hooks.
	 *
	 * Does not register uninstall. 4.0 must not delete `wp_china_yes`.
	 */
	private function register_lifecycle_hooks(): void {
		if ( ! defined( 'CHINA_YES_PLUGIN_FILE' ) ) {
			return;
		}

		if ( function_exists( 'register_activation_hook' ) ) {
			register_activation_hook( CHINA_YES_PLUGIN_FILE, array( self::class, 'activate' ) );
		}

		if ( function_exists( 'register_deactivation_hook' ) ) {
			register_deactivation_hook( CHINA_YES_PLUGIN_FILE, array( self::class, 'deactivate' ) );
		}
	}
}
