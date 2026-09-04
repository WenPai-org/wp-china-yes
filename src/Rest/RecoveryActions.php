<?php
/**
 * Recovery actions shared by REST POST /recovery and the PHP recovery page.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\Repository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared recovery actions persist off-state; exit only clears the flag.
 */
final class RecoveryActions {

	/**
	 * Known actions from docs/specs/rest-api.md.
	 *
	 * @since 4.0.0
	 */
	public const DISABLE_REWRITES = 'disable_rewrites';
	public const DISABLE_MODULES  = 'disable_modules';
	public const EXIT             = 'exit';

	/**
	 * Settings access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository $repository Settings access.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Apply one recovery action. Unknown action is WP_Error.
	 *
	 * Exit does not restore previously disabled rewrites or modules (待定 M0).
	 *
	 * @since 4.0.0
	 *
	 * @param string $action disable_rewrites|disable_modules|exit.
	 * @return true|WP_Error
	 */
	public function apply( string $action ) {
		if ( self::DISABLE_REWRITES === $action ) {
			$this->disable_rewrites();
			$this->repository->set( 'recovery_mode', true );
			return true;
		}

		if ( self::DISABLE_MODULES === $action ) {
			$this->disable_modules();
			$this->repository->set( 'recovery_mode', true );
			return true;
		}

		if ( self::EXIT === $action ) {
			$this->repository->set( 'recovery_mode', false );
			return true;
		}

		return RestError::unknown_action();
	}

	/**
	 * Effective settings after the last apply.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		return $this->repository->all();
	}

	/**
	 * Turn off URL rewrite switches so exit (flag only) does not bring them back.
	 *
	 * @since 4.0.0
	 */
	private function disable_rewrites(): void {
		$this->repository->set( 'connectivity.wordpress_org', 'off' );
		$this->repository->set( 'connectivity.public_assets', array() );
		$this->repository->set( 'connectivity.avatar', 'off' );
	}

	/**
	 * Turn off optional modules so exit does not re-enable them.
	 *
	 * @since 4.0.0
	 */
	private function disable_modules(): void {
		$this->repository->set( 'modules.notice_control', false );
		$this->repository->set( 'modules.windfonts', false );
	}
}
