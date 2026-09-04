<?php
/**
 * Optional Windfonts integration. Head stylesheet only; no RTL/reading.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Integrations\Windfonts;

use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id modules.windfonts. Quota exhaustion prints nothing (origin fonts).
 *
 * Admin stylesheet injection (3.x `on` vs `frontend`) is 待定（M0）.
 * Until then only wp_head, matching the 3.x frontend smoke.
 */
final class WindfontsModule implements ConditionalModule {

	/**
	 * Config read model.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Markup builder.
	 *
	 * @var Stylesheet
	 */
	private Stylesheet $stylesheet;

	/**
	 * Optional entitlement gate. Null means apply_filters default true
	 * (no entitlements client yet).
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable|null
	 */
	private $entitlement_allows;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Config          $config             Config read model.
	 * @param Stylesheet|null $stylesheet         Markup builder. Null constructs default.
	 * @param callable|null   $entitlement_allows `fn(): bool`; false prints no Windfonts tags.
	 */
	public function __construct( Config $config, $stylesheet = null, ?callable $entitlement_allows = null ) {
		$this->config             = $config;
		$this->stylesheet         = $stylesheet instanceof Stylesheet ? $stylesheet : new Stylesheet();
		$this->entitlement_allows = $entitlement_allows;
	}

	/**
	 * Module id. Same path as the config key.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'modules.windfonts';
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
	 * Frontend output. CLI is included so wp eval do_action('wp_head') can assert.
	 *
	 * Admin is omitted until the 待定（M0） admin_head decision.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array( Environment::FRONTEND, Environment::CLI );
	}

	/**
	 * True when modules.windfonts is true, not recovery, and entitlement allows.
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

		if ( true !== $config->get( 'modules.windfonts', false ) ) {
			return false;
		}

		return $this->entitlement_allows();
	}

	/**
	 * Hook wp_head only. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'print_stylesheets' ) );
	}

	/**
	 * Echo preconnect, license mark, and enabled font links. No crossorigin.
	 *
	 * @since 4.0.0
	 */
	public function print_stylesheets(): void {
		$fonts = $this->config->get( 'integrations.windfonts.fonts', array() );
		if ( ! is_array( $fonts ) ) {
			$fonts = array();
		}

		echo $this->stylesheet->render( $fonts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Stylesheet::render() escapes href via esc_url; license mark is a fixed 3.x string; selector matches 3.x htmlspecialchars_decode.
	}

	/**
	 * Limited-free Windfonts: denied / exhausted → no link tags. Default allow.
	 *
	 * @since 4.0.0
	 */
	private function entitlement_allows(): bool {
		if ( is_callable( $this->entitlement_allows ) ) {
			return (bool) call_user_func( $this->entitlement_allows );
		}

		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'wpcy_entitlement_allows', true, 'windfonts' );
		}

		return true;
	}
}
