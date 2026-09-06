<?php
/**
 * L2 site blocklist: user-owned exact/suffix hosts, block only.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Privacy\SiteBlocklist;

use WenPai\ChinaYes\Config\Repository as ConfigRepository;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id privacy.site_blocklist. pre_http_request priority 15.
 */
final class SiteBlocklistModule implements ConditionalModule {

	/**
	 * List store.
	 *
	 * @var Repository
	 */
	private Repository $list;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param ConfigRepository $config  Settings.
	 * @param Repository|null  $store   List store.
	 * @param Ruleset|null     $ruleset L0 table.
	 */
	public function __construct( ConfigRepository $config, $store = null, $ruleset = null ) {
		$this->list = $store instanceof Repository
			? $store
			: new Repository( $config, $ruleset );
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'privacy.site_blocklist';
	}

	/**
	 * HTTP leaves from every scene.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}

	/**
	 * Scene defaults. Network-only; three profiles keep enabled=true.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function profile_defaults(): array {
		return array(
			'modules.site_blocklist.enabled' => array(
				'domestic'    => true,
				'crossborder' => true,
				'mixed'       => true,
				'scopes'      => array( 'network' ),
			),
		);
	}

	/**
	 * Off when disabled or recovery_mode.
	 *
	 * @since 4.0.0
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		unset( $environment );
		if ( true === $config->get( 'recovery_mode', false ) ) {
			return false;
		}

		$blocklist = $config->get( 'modules.site_blocklist', array() );
		if ( is_array( $blocklist ) && array_key_exists( 'enabled', $blocklist ) ) {
			return true === $blocklist['enabled'];
		}

		return true === $config->get( 'modules.site_blocklist.enabled', true );
	}

	/**
	 * Hook pre_http_request at priority 15.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 15, 3 );
	}

	/**
	 * Block matching hosts. Earlier layers that already short-circuited are left alone.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed                $preempt Short-circuit value from earlier filters.
	 * @param array<string, mixed> $args    Request arguments.
	 * @param string               $url     Request URL.
	 * @return mixed
	 */
	public function filter_pre_http_request( $preempt, $args, $url ) {
		unset( $args );
		if ( false !== $preempt ) {
			return $preempt;
		}
		if ( '' === $url ) {
			return $preempt;
		}

		$host = $this->request_host( $url );
		if ( '' === $host ) {
			return $preempt;
		}

		if ( $this->list->ruleset()->is_protected( $host ) ) {
			return $preempt;
		}

		$l1 = $this->list->ruleset()->match( $url );
		if ( is_array( $l1 ) ) {
			$action = isset( $l1['action'] ) && is_string( $l1['action'] ) ? $l1['action'] : '';
			if ( in_array( $action, array( 'reroute', 'record' ), true ) ) {
				return $preempt;
			}
		}

		if ( ! $this->list->matches( $host ) ) {
			return $preempt;
		}

		return new WP_Error( 'wpcy_site_blocklist_blocked', 'wpcy_site_blocklist_blocked' );
	}

	/**
	 * List store.
	 *
	 * @since 4.0.0
	 */
	public function repository(): Repository {
		return $this->list;
	}

	/**
	 * Host only.
	 *
	 * @param string $url Request URL.
	 */
	private function request_host( string $url ): string {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		return strtolower( (string) $parts['host'] );
	}
}
