<?php
/**
 * Apps kernel module: REST /apps* and dbDelta on admin_init.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Apps;

use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Core\Module;
use WenPai\ChinaYes\Rest\AppsController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id services.apps. Does not fetch production apps.wpcy.com by default.
 *
 * Kernel wiring (Plugin::create / RestModule) is left to merge: those files
 * are outside this task's diff budget and existing tests pin the current list.
 */
final class AppsModule implements Module {

	/**
	 * Verified list.
	 *
	 * @var Registry
	 */
	private Registry $registry;

	/**
	 * App data table.
	 *
	 * @var DataStore
	 */
	private DataStore $store;

	/**
	 * Entitlements reader.
	 *
	 * @var EntitlementsClient
	 */
	private EntitlementsClient $entitlements;

	/**
	 * Constructor. Does not register hooks. Empty index source disables production fetch.
	 *
	 * @since 4.0.0
	 *
	 * @param Registry|null           $registry     Verified list.
	 * @param DataStore|null          $store        Data table.
	 * @param EntitlementsClient|null $entitlements Entitlements. Null is exhausted.
	 * @param Index|null              $index        Signed index.
	 * @param Logger|null             $logger       Logger.
	 */
	public function __construct( $registry = null, $store = null, $entitlements = null, $index = null, $logger = null ) {
		if ( $registry instanceof Registry ) {
			$this->registry = $registry;
		} else {
			$logger         = $logger instanceof Logger ? $logger : null;
			$index          = $index instanceof Index ? $index : new Index( new ManifestVerifier(), '', null, $logger );
			$this->registry = new Registry( $index );
		}
		$this->store        = $store instanceof DataStore ? $store : new DataStore();
		$this->entitlements = $entitlements instanceof EntitlementsClient ? $entitlements : new ExhaustedEntitlements();
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'services.apps';
	}

	/**
	 * REST plus admin (host page later) and cron (index TTL).
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}

	/**
	 * No module graph edges.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Hook REST and table install. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_init', array( DataStore::class, 'install' ) );
	}

	/**
	 * Register /apps* on wpcy/v1.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		$controller = new AppsController( $this->registry, $this->store, $this->entitlements );
		$controller->register_routes();
	}

	/**
	 * Verified list.
	 *
	 * @since 4.0.0
	 */
	public function registry(): Registry {
		return $this->registry;
	}

	/**
	 * Data table.
	 *
	 * @since 4.0.0
	 */
	public function store(): DataStore {
		return $this->store;
	}
}
