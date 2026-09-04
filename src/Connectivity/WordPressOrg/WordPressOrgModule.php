<?php
/**
 * Rewrite api.wordpress.org / downloads.wordpress.org to split origins.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\WordPressOrg;

use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

/**
 * Conditional WordPress.org connectivity module.
 */
final class WordPressOrgModule implements ConditionalModule {

	/**
	 * Package-mirror probe.
	 *
	 * @var MirrorProbe
	 */
	private MirrorProbe $probe;

	/**
	 * HTTP request used after rewrite: function( string $url, array $args ): mixed
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $request;

	/**
	 * Whether package (install zip / language pack) rewrite is allowed.
	 *
	 * No entitlements client: default false (limited-free). Metadata still rewrites.
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $packages_allowed;

	/**
	 * Last URL passed to $request (tests).
	 *
	 * @var string
	 */
	private string $last_request_url = '';

	/**
	 * Wire probe, optional HTTP request, and package-entitlement hook.
	 *
	 * @param MirrorProbe   $probe            Usability probe.
	 * @param callable|null $request          Defaults to wp_remote_request().
	 * @param callable|null $packages_allowed Defaults to entitlement filter, false if absent.
	 */
	public function __construct( MirrorProbe $probe, $request = null, $packages_allowed = null ) {
		$this->probe   = $probe;
		$this->request = null !== $request ? $request : 'wp_remote_request';

		if ( null !== $packages_allowed ) {
			$this->packages_allowed = $packages_allowed;
			return;
		}

		$this->packages_allowed = static function () {
			if ( function_exists( 'apply_filters' ) ) {
				return (bool) apply_filters( 'wpcy_entitlement_allows', false, 'connectivity.wordpress_org.packages' );
			}

			return false;
		};
	}

	/**
	 * Module id. Same path as the config key.
	 */
	public function id(): string {
		return 'connectivity.wordpress_org';
	}

	/**
	 * No module graph edges.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Same scenes as 3.x Super: admin and cron. Not frontend.
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array(
			Environment::ADMIN,
			Environment::CRON,
		);
	}

	/**
	 * Off, recovery, or rewrite-disabled scenes skip register().
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		if ( $config->get( 'connectivity.wordpress_org', 'auto' ) === 'off' ) {
			return false;
		}

		if ( true === $config->get( 'recovery_mode', false ) ) {
			return false;
		}

		return $environment->allowsUrlRewrite();
	}

	/**
	 * Hook pre_http_request. Constructor does not register hooks.
	 */
	public function register(): void {
		add_filter( 'pre_http_request', array( $this, 'filter_wordpress_org' ), 100, 3 );
	}

	/**
	 * Rewrite WordPress.org API / download requests onto split origins.
	 *
	 * Signature matches the pre_http_request filter (3 args).
	 *
	 * @param mixed $preempt     Short-circuit value.
	 * @param mixed $parsed_args Request args from WordPress.
	 * @param mixed $url         Request URL from WordPress.
	 * @return mixed
	 */
	public function filter_wordpress_org( $preempt, $parsed_args, $url ) {
		$mirror_url = $this->rewritten_url( is_string( $url ) ? $url : '' );

		if ( null === $mirror_url ) {
			return $preempt;
		}

		$args = is_array( $parsed_args ) ? $parsed_args : array();

		$args['timeout']        = 30;
		$this->last_request_url = $mirror_url;

		return ( $this->request )( $mirror_url, $args );
	}

	/**
	 * Mapped mirror URL, or null when the request must keep the upstream.
	 *
	 * @param string $url Original request URL.
	 */
	public function rewritten_url( string $url ): ?string {
		$host = $this->url_host( $url );

		if ( Origins::UPSTREAM_API_HOST !== $host && Origins::UPSTREAM_PACKAGE_HOST !== $host ) {
			return null;
		}

		if ( ! $this->probe->is_usable() ) {
			return null;
		}

		if ( Origins::UPSTREAM_PACKAGE_HOST === $host && ! $this->packages_are_allowed() ) {
			return null;
		}

		$path  = $this->url_part( $url, PHP_URL_PATH );
		$query = $this->url_part( $url, PHP_URL_QUERY );

		$origin = ( Origins::UPSTREAM_PACKAGE_HOST === $host )
			? Origins::PACKAGE_ORIGIN
			: Origins::API_ORIGIN;

		$mirror_url = $origin . $path;

		if ( '' !== $query ) {
			$mirror_url .= '?' . $query;
		}

		return $mirror_url;
	}

	/**
	 * Last rewritten request URL (empty if none).
	 */
	public function last_request_url(): string {
		return $this->last_request_url;
	}

	/**
	 * Limited-free package layer. No numeric quota in this module.
	 */
	private function packages_are_allowed(): bool {
		return (bool) ( $this->packages_allowed )();
	}

	/**
	 * Host of $url, or empty string.
	 *
	 * @param string $url Request URL.
	 */
	private function url_host( string $url ): string {
		$host = $this->parse( $url, PHP_URL_HOST );

		return is_string( $host ) ? $host : '';
	}

	/**
	 * Path or query component as string.
	 *
	 * @param string $url      Request URL.
	 * @param int    $component PHP_URL_* constant.
	 */
	private function url_part( string $url, int $component ): string {
		$part = $this->parse( $url, $component );

		return is_string( $part ) ? $part : '';
	}

	/**
	 * Parse a URL with wp_parse_url when present, else parse_url.
	 *
	 * @param string $url       Request URL.
	 * @param int    $component PHP_URL_* constant.
	 * @return mixed
	 */
	private function parse( string $url, int $component ) {
		if ( function_exists( 'wp_parse_url' ) ) {
			return wp_parse_url( $url, $component );
		}

		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
	}
}
