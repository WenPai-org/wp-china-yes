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
use WenPai\ChinaYes\Admin\NoticeControl\NoticeControlModule;
use WenPai\ChinaYes\Apps\AppsModule;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Connectivity\Avatar\AvatarModule;
use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Connectivity\PublicAssets\AssetMap;
use WenPai\ChinaYes\Connectivity\PublicAssets\PublicAssetsModule;
use WenPai\ChinaYes\Connectivity\WordPressOrg\MirrorProbe;
use WenPai\ChinaYes\Connectivity\WordPressOrg\WordPressOrgModule;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Diagnostics\DiagnosticsModule;
use WenPai\ChinaYes\Diagnostics\SiteHealth;
use WenPai\ChinaYes\Integrations\Windfonts\WindfontsModule;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WenPai\ChinaYes\Telemetry\TelemetryModule;

/**
 * New kernel. Does not instantiate 3.x Plugin or Service\Base.
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
	 * Default wiring and scene boot. Called from wp-china-yes.php when WPCY_KERNEL=v4.
	 */
	public static function boot(): void {
		$plugin = self::create();
		$plugin->register_lifecycle_hooks();
		$plugin->run();
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

		$probe = new MirrorProbe();
		$container->set( 'wordpress_org.probe', $probe );
		$registry->add( new WordPressOrgModule( $probe ) );

		$map    = new AssetMap();
		$health = new MirrorHealth();
		$container->set( 'public_assets.map', $map );
		$container->set( 'mirror_health', $health );
		$registry->add( new PublicAssetsModule( $config, $map, $health ) );

		$registry->add( new AvatarModule( $config ) );
		$registry->add( new WindfontsModule( $config ) );
		$registry->add( new TelemetryModule( $config, $logger ) );
		$registry->add( new DataResidencyModule() );

		$checker = new Checker( null, null, null, $config );
		$container->set( 'diagnostics.checker', $checker );
		$registry->add( new DiagnosticsModule( $config, $checker, new SiteHealth( $checker ) ) );
		$registry->add( new SiteBindingModule( $config, $logger ) );
		$registry->add( new AppsModule( null, null, null, null, $logger ) );
		$registry->add( new RestModule( $config, $checker ) );
		$registry->add( new AdminModule( $config ) );
		$registry->add( new EntitlementsModule( $config, $logger ) );
		$registry->add( new NoticeControlModule( $config ) );
		$registry->add( new AnnouncementsModule( $config ) );

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
	 * V4 activation: do not write the 3.x option wp_china_yes.
	 */
	public static function activate(): void {
		// No-op. Do not write the 3.x option wp_china_yes.
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
	 * Activation / deactivation hooks for the v4 path.
	 *
	 * The bootstrap returns before the 3.x hooks when WPCY_KERNEL=v4.
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
