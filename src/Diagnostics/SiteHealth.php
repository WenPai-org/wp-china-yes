<?php
/**
 * Site Health debug section for connection checks. No credentials.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the 文派叶子 debug_information section.
 */
final class SiteHealth {

	/**
	 * Section key in Site Health info.
	 *
	 * @since 4.0.0
	 */
	public const SECTION = 'wp-china-yes';

	/**
	 * Result source.
	 *
	 * @var Checker
	 */
	private $checker;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Checker $checker Result source.
	 */
	public function __construct( Checker $checker ) {
		$this->checker = $checker;
	}

	/**
	 * Hook debug_information.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_filter( 'debug_information', array( $this, 'add_debug_info' ) );
	}

	/**
	 * Append the 文派叶子 connection-status section.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $info Site Health info tree.
	 * @return mixed
	 */
	public function add_debug_info( $info ) {
		if ( ! is_array( $info ) ) {
			$info = array();
		}

		$fields = array();
		foreach ( $this->checker->latest() as $row ) {
			$label = (string) $row['target'];
			$parts = array( (string) $row['result'] );
			if ( null !== $row['latency_ms'] ) {
				$parts[] = (string) (int) $row['latency_ms'] . ' ms';
			}
			$parts[] = (string) $row['checked_at'];
			if ( null !== $row['suggestion'] && '' !== $row['suggestion'] ) {
				$parts[] = (string) $row['suggestion'];
			}

			$fields[ sanitize_key( $label ) ] = array(
				'label' => $label,
				'value' => implode( ' · ', $parts ),
				'debug' => $row,
			);
		}

		$info[ self::SECTION ] = array(
			'label'       => __( '文派叶子', 'wp-china-yes' ),
			'description' => __( '连接检查：目标、结果、延迟、最近检查时间。', 'wp-china-yes' ),
			'fields'      => $fields,
		);

		return $info;
	}
}
