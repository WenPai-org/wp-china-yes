<?php
/**
 * Read-only outbound-layer snapshot for diagnostics and REST.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Diagnostics;

use WenPai\ChinaYes\Config\Repository as ConfigRepository;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Privacy\SiteBlocklist\Repository as BlocklistRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Three-layer view plus noise pack. Does not persist test results.
 */
final class OutboundLayers {

	/**
	 * Host table.
	 *
	 * @var Ruleset
	 */
	private Ruleset $ruleset;

	/**
	 * L2 store.
	 *
	 * @var BlocklistRepository
	 */
	private BlocklistRepository $blocklist;

	/**
	 * L1 module (reroute gate).
	 *
	 * @var DataResidencyModule
	 */
	private DataResidencyModule $residency;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param ConfigRepository         $config     Settings.
	 * @param Ruleset|null             $ruleset    Host table.
	 * @param BlocklistRepository|null $blocklist  L2 store.
	 * @param DataResidencyModule|null $residency  L1 module.
	 */
	public function __construct( ConfigRepository $config, $ruleset = null, $blocklist = null, $residency = null ) {
		$this->ruleset   = $ruleset instanceof Ruleset ? $ruleset : new Ruleset();
		$this->blocklist = $blocklist instanceof BlocklistRepository
			? $blocklist
			: new BlocklistRepository( $config, $this->ruleset );
		$this->residency = $residency instanceof DataResidencyModule
			? $residency
			: new DataResidencyModule( $this->ruleset, false, $config );
	}

	/**
	 * GET /residency/protected payload.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function snapshot(): array {
		$l2          = $this->blocklist->get();
		$noise_on    = $this->residency->noise_enabled();
		$noise_hosts = array();
		foreach ( $this->ruleset->noise_hosts() as $row ) {
			$noise_hosts[] = array(
				'host'  => $row['host'],
				'match' => $row['match'],
			);
		}

		$l1 = $this->ruleset->to_rest();

		return array(
			'l0'          => array(
				'source' => $this->ruleset->protected_source(),
				'hosts'  => $this->ruleset->protected_hosts(),
			),
			'l1'          => array(
				'ruleset_version' => $l1['ruleset_version'],
				'tiers'           => $l1['tiers'],
			),
			'l2'          => array(
				'enabled' => $l2['enabled'],
				'hosts'   => $l2['hosts'],
			),
			'noise_block' => array(
				'enabled' => $noise_on,
				'hosts'   => $noise_hosts,
			),
		);
	}

	/**
	 * Classify one URL the same way pre_http_request would. No storage.
	 *
	 * @since 4.0.0
	 *
	 * @param string $url Absolute URL.
	 * @return array{url: string, host: string, layer: string, action: string, detail: array<string, mixed>}
	 */
	public function test( string $url ): array {
		$host = $this->host_of( $url );

		if ( $this->ruleset->is_protected( $host ) ) {
			return $this->result( $url, $host, 'l0', 'allow', array() );
		}

		$l1 = $this->ruleset->match( $url );
		if ( is_array( $l1 ) ) {
			$action = isset( $l1['action'] ) && is_string( $l1['action'] ) ? $l1['action'] : '';
			$detail = $this->l1_detail( $l1 );
			if ( 'record' === $action ) {
				return $this->result( $url, $host, 'l1', 'record', $detail );
			}
			if ( 'reroute' === $action && $this->residency->reroute_enabled( $l1 ) ) {
				return $this->result( $url, $host, 'l1', 'reroute', $detail );
			}
		}

		if ( $this->residency->noise_enabled() ) {
			$noise = $this->ruleset->noise_match( $host );
			if ( is_array( $noise ) && empty( $noise['skipped'] ) ) {
				return $this->result(
					$url,
					$host,
					'noise_block',
					'block',
					array(
						'host'  => is_string( $noise['host'] ) ? $noise['host'] : $host,
						'match' => is_string( $noise['match'] ) ? $noise['match'] : 'exact',
					)
				);
			}
		}

		$l2 = $this->blocklist->get();
		if ( ! empty( $l2['enabled'] ) && $this->blocklist->matches( $host ) ) {
			return $this->result( $url, $host, 'l2', 'block', array() );
		}

		if ( is_array( $l1 ) ) {
			$action = isset( $l1['action'] ) && is_string( $l1['action'] ) ? $l1['action'] : 'ignore';
			if ( 'reroute' === $action && ! $this->residency->reroute_enabled( $l1 ) ) {
				$action = 'allow';
			}

			return $this->result( $url, $host, 'l1', $action, $this->l1_detail( $l1 ) );
		}

		return $this->result( $url, $host, 'none', 'allow', array() );
	}

	/**
	 * L1 detail object.
	 *
	 * @param array<string, mixed> $rule Matched rule.
	 * @return array<string, mixed>
	 */
	private function l1_detail( array $rule ): array {
		$detail = array();
		if ( isset( $rule['match'] ) && is_string( $rule['match'] ) ) {
			$detail['match'] = $rule['match'];
		}
		if ( isset( $rule['host'] ) && is_string( $rule['host'] ) ) {
			$detail['host'] = $rule['host'];
		}
		if ( isset( $rule['enabled_when'] ) && is_string( $rule['enabled_when'] ) ) {
			$detail['enabled_when'] = $rule['enabled_when'];
		}

		$tiers = $this->ruleset->to_rest()['tiers'];
		foreach ( array( 'A', 'B', 'C' ) as $tier ) {
			$rows = isset( $tiers[ $tier ] ) && is_array( $tiers[ $tier ] ) ? $tiers[ $tier ] : array();
			foreach ( $rows as $row ) {
				if ( $row === $rule ) {
					$detail['tier'] = $tier;
					break 2;
				}
			}
		}

		return $detail;
	}

	/**
	 * Result shape.
	 *
	 * @param string               $url    URL.
	 * @param string               $host   Host.
	 * @param string               $layer  Layer.
	 * @param string               $action Action.
	 * @param array<string, mixed> $detail Detail.
	 * @return array{url: string, host: string, layer: string, action: string, detail: array<string, mixed>}
	 */
	private function result( string $url, string $host, string $layer, string $action, array $detail ): array {
		return array(
			'url'    => $url,
			'host'   => $host,
			'layer'  => $layer,
			'action' => $action,
			'detail' => $detail,
		);
	}

	/**
	 * Host of $url.
	 *
	 * @param string $url URL.
	 */
	private function host_of( string $url ): string {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		return strtolower( (string) $parts['host'] );
	}
}
