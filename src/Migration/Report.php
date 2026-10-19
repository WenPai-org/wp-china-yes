<?php
/**
 * Kept / ignored field lists from a 3.x → 4.0 mapping pass.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dry-run / execute document. Field names only; no option values or credentials.
 */
final class Report {

	/**
	 * 3.x keys that mapped into 4.0 settings.
	 *
	 * @var array<int, string>
	 */
	private array $kept;

	/**
	 * 3.x keys that were not mapped.
	 *
	 * @var array<int, string>
	 */
	private array $ignored;

	/**
	 * Ignored key → reason token (feature_removed, login_state, …).
	 *
	 * @var array<string, string>
	 */
	private array $ignored_reasons;

	/**
	 * Sanitized 4.0 settings document (site or network).
	 *
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Extra ignored rows with key / value / reason (weavatar rewrite, …).
	 *
	 * @var list<array{key: string, value: string, reason: string}>
	 */
	private array $ignored_entries;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param array<int, string>                                      $kept             Mapped 3.x keys.
	 * @param array<int, string>                                      $ignored          Unmapped 3.x keys.
	 * @param array<string, string>                                   $ignored_reasons  Key → reason.
	 * @param array<string, mixed>                                    $settings         Sanitized 4.0 document.
	 * @param list<array{key: string, value: string, reason: string}> $ignored_entries  Extra ignored rows.
	 */
	public function __construct( array $kept, array $ignored, array $ignored_reasons, array $settings, array $ignored_entries = array() ) {
		$this->kept            = array_values( $kept );
		$this->ignored         = array_values( $ignored );
		$this->ignored_reasons = $ignored_reasons;
		$this->settings        = $settings;
		$this->ignored_entries = $ignored_entries;
	}

	/**
	 * Mapped 3.x keys.
	 *
	 * @since 4.0.0
	 *
	 * @return array<int, string>
	 */
	public function kept(): array {
		return $this->kept;
	}

	/**
	 * Unmapped 3.x keys.
	 *
	 * @since 4.0.0
	 *
	 * @return array<int, string>
	 */
	public function ignored(): array {
		return $this->ignored;
	}

	/**
	 * Reason tokens for ignored keys.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, string>
	 */
	public function ignored_reasons(): array {
		return $this->ignored_reasons;
	}

	/**
	 * Sanitized 4.0 settings.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		return $this->settings;
	}

	/**
	 * JSON-ready document. No credentials.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$ignored = $this->ignored;
		foreach ( $this->ignored_entries as $entry ) {
			$ignored[] = $entry;
		}

		$document = array(
			'kept'            => $this->kept,
			'ignored'         => $ignored,
			'ignored_reasons' => $this->ignored_reasons,
			'settings'        => $this->settings,
		);

		if ( $this->admin_assets_dropped() ) {
			$document['notes']    = array( 'admin_assets_dropped' );
			$document['messages'] = array(
				__( '后台静态加速已取消（易致后台界面问题）', 'wp-china-yes' ),
			);
		}

		return $document;
	}

	/**
	 * Whether 3.x admin-cdn intent was recorded as no longer needed.
	 */
	public function admin_assets_dropped(): bool {
		foreach ( $this->ignored_entries as $entry ) {
			if ( 'admin' === $entry['key'] ) {
				return true;
			}
		}

		return false;
	}
}
