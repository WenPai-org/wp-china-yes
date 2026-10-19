<?php
/**
 * Admin locale follows the logged-in user's language; frontend is unchanged.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\AdminLocale;

use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id connectivity.admin_locale_follow.
 *
 * When the switch is on, `locale` / `determine_locale` return get_user_locale()
 * only if is_admin(). Off → enabled() is false, so the registry never hooks.
 */
final class AdminLocaleModule implements ConditionalModule {

	/**
	 * Config read model.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Re-entrancy guard: get_user_locale() may call get_locale().
	 *
	 * @var bool
	 */
	private bool $resolving = false;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Config $config Config read model.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'connectivity.admin_locale_follow';
	}

	/**
	 * No module dependencies.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Admin screens only. Frontend never registers this module.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array( Environment::ADMIN );
	}

	/**
	 * Recovery mode or switch off → do not hook (WordPress default).
	 *
	 * @since 4.0.0
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		unset( $environment );
		if ( $this->config->get( 'recovery_mode' ) || $config->get( 'recovery_mode' ) ) {
			return false;
		}

		return (bool) $this->config->get( 'connectivity.admin_locale_follow', true );
	}

	/**
	 * Filter locale and determine_locale. Both callbacks are admin-gated.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_filter( 'locale', array( $this, 'filter_locale' ) );
		add_filter( 'determine_locale', array( $this, 'filter_locale' ) );
	}

	/**
	 * Replace the locale with the current user's language in wp-admin only.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $locale Prior locale.
	 * @return mixed
	 */
	public function filter_locale( $locale ) {
		if ( $this->resolving ) {
			return $locale;
		}

		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return $locale;
		}

		if ( ! function_exists( 'get_user_locale' ) ) {
			return $locale;
		}

		$this->resolving = true;
		$user_locale     = get_user_locale();
		$this->resolving = false;

		if ( '' === $user_locale ) {
			return $locale;
		}

		return $user_locale;
	}
}
