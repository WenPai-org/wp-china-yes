<?php
/**
 * MotuCloud mirror track: swap icon / photo search origins onto mirrored_base.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\IconPhotos;

use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Connectivity\Scope;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Providers\UrlGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id connectivity.icon_photos. Empty bases keep the feature closed.
 */
final class IconPhotosModule implements ConditionalModule {

	/**
	 * Config read model.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * HTTP request used after rewrite: function( string $url, array $args ): mixed
	 *
	 * @var callable
	 */
	private $request;

	/**
	 * Per-host down TTL used to skip a dead mirror for a while.
	 *
	 * @var MirrorHealth
	 */
	private MirrorHealth $health;

	/**
	 * Last URL passed to $request (tests).
	 *
	 * @var string
	 */
	private string $last_request_url = '';

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Config            $config  Config read model.
	 * @param callable|null     $request Defaults to wp_remote_request().
	 * @param MirrorHealth|null $health  Defaults to a new MirrorHealth().
	 */
	public function __construct( Config $config, $request = null, $health = null ) {
		$this->config  = $config;
		$this->request = null !== $request ? $request : 'wp_remote_request';
		$this->health  = $health instanceof MirrorHealth ? $health : new MirrorHealth();
	}

	/**
	 * Module id. Same path as the config key.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'connectivity.icon_photos';
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
	 * Icon / photo HTTP runs in admin, frontend, REST, and cron.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array(
			Environment::ADMIN,
			Environment::FRONTEND,
			Environment::REST,
			Environment::CRON,
		);
	}

	/**
	 * Off, recovery, empty bases, or scope mismatch skip register().
	 *
	 * @since 4.0.0
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		if ( true === $config->get( 'recovery_mode', false ) ) {
			return false;
		}

		if ( ! $environment->allowsUrlRewrite() ) {
			return false;
		}

		$row = $this->settings( $config );
		if ( 'off' === $row['enabled'] ) {
			return false;
		}

		if ( '' === $row['mirrored_base'] ) {
			return false;
		}

		return $this->scope_matches( $row['enabled'] );
	}

	/**
	 * Hook pre_http_request ahead of WordPress.org rewrite (priority 90 < 100).
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 90, 3 );
	}

	/**
	 * Rewrite matching icon / photo URLs onto mirrored_base. Failure keeps the core source.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $preempt     Short-circuit value.
	 * @param mixed $parsed_args Request args from WordPress.
	 * @param mixed $url         Request URL from WordPress.
	 * @return mixed
	 */
	public function filter_pre_http_request( $preempt, $parsed_args, $url ) {
		$mirror_url = $this->rewritten_url( is_string( $url ) ? $url : '' );
		if ( null === $mirror_url ) {
			return $preempt;
		}

		$args = is_array( $parsed_args ) ? $parsed_args : array();

		$timeout = isset( $args['timeout'] ) ? (float) $args['timeout'] : Origins::TIMEOUT;
		if ( $timeout > Origins::TIMEOUT || $timeout <= 0 ) {
			$timeout = Origins::TIMEOUT;
		}

		$args['timeout']        = $timeout;
		$args['sslverify']      = true;
		$this->last_request_url = $mirror_url;

		$response = ( $this->request )( $mirror_url, $args );

		if ( $this->mirror_request_failed( $response ) ) {
			$this->health->remember( MirrorHealth::host_of( $mirror_url ), 'down', Origins::DOWN_TTL );
			return $preempt;
		}

		return $response;
	}

	/**
	 * Mapped mirror URL, or null when the request must keep the upstream.
	 *
	 * @since 4.0.0
	 *
	 * @param string $url Original request URL.
	 */
	public function rewritten_url( string $url ): ?string {
		$row  = $this->settings( $this->config );
		$base = $row['mirrored_base'];
		if ( '' === $base || ! $this->matches_url( $url ) ) {
			return null;
		}

		$origin = rtrim( $base, '/' );
		if ( ! $this->health->is_healthy( MirrorHealth::host_of( $origin ) ) ) {
			return null;
		}

		$path       = $this->url_part( $url, PHP_URL_PATH );
		$mirror_url = $origin . ( '' === $path ? '/' : $path );
		$query      = $this->url_part( $url, PHP_URL_QUERY );
		if ( '' !== $query ) {
			$mirror_url .= '?' . $query;
		}

		return $mirror_url;
	}

	/**
	 * Whether $url is a core icon API or media-library photo-search request.
	 *
	 * @since 4.0.0
	 *
	 * @param string $url Request URL.
	 */
	public function matches_url( string $url ): bool {
		$host = $this->url_host( $url );
		$path = $this->url_part( $url, PHP_URL_PATH );

		return '' !== $host && $this->matches_rule( $host, $path );
	}

	/**
	 * Last rewritten request URL (empty if none).
	 *
	 * @since 4.0.0
	 */
	public function last_request_url(): string {
		return $this->last_request_url;
	}

	/**
	 * Normalized icon_photos row. Empty bases stay empty (feature closed).
	 *
	 * @since 4.0.0
	 *
	 * @param Config $config Config read model.
	 * @return array{enabled: string, mirrored_base: string, native_api_base: string}
	 */
	public function settings( Config $config ): array {
		$raw = $config->get( 'connectivity.icon_photos', array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$enabled = isset( $raw['enabled'] ) && is_string( $raw['enabled'] ) ? $raw['enabled'] : 'off';
		if ( ! in_array( $enabled, array( 'off', 'on', 'admin' ), true ) ) {
			$enabled = 'off';
		}

		return array(
			'enabled'         => $enabled,
			'mirrored_base'   => $this->origin_base( isset( $raw['mirrored_base'] ) ? $raw['mirrored_base'] : '' ),
			'native_api_base' => $this->origin_base( isset( $raw['native_api_base'] ) ? $raw['native_api_base'] : '' ),
		);
	}

	/**
	 * HTTPS origin or empty. Rejects http / private / empty.
	 *
	 * @param mixed $value Stored base.
	 */
	private function origin_base( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$base = trim( $value );
		if ( '' === $base || ! UrlGuard::allows( $base ) ) {
			return '';
		}

		return rtrim( $base, '/' );
	}

	/**
	 * On = both sides; admin = admin requests only.
	 *
	 * @param string $enabled off|on|admin.
	 */
	private function scope_matches( string $enabled ): bool {
		if ( 'on' === $enabled ) {
			return true;
		}
		if ( 'admin' !== $enabled ) {
			return false;
		}

		return Scope::ADMIN === Scope::current();
	}

	/**
	 * Whether host+path is on the isomorphic mirror table.
	 *
	 * @param string $host Request host.
	 * @param string $path Request path.
	 */
	private function matches_rule( string $host, string $path ): bool {
		$host = strtolower( $host );
		foreach ( Origins::mirror_rules() as $rule ) {
			if ( $host !== $rule['host'] ) {
				continue;
			}
			$exclude = $rule['path_exclude_prefix'];
			if ( '' !== $exclude && 0 === strpos( $path, $exclude ) ) {
				continue;
			}
			if ( 0 === strpos( $path, $rule['path_prefix'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * WP_Error or a non-2xx HTTP code.
	 *
	 * @param mixed $response HTTP result.
	 */
	private function mirror_request_failed( $response ): bool {
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return true;
		}
		if ( $response instanceof \WP_Error ) {
			return true;
		}
		$code = $this->response_code( $response );

		return $code >= 300 || ( $code > 0 && $code < 200 );
	}

	/**
	 * HTTP status from a canned array or WP HTTP response.
	 *
	 * @param mixed $response HTTP result.
	 */
	private function response_code( $response ): int {
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return 0;
		}
		if ( ! is_array( $response ) ) {
			return 0;
		}
		if ( isset( $response['code'] ) ) {
			return (int) $response['code'];
		}
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $response );
		}
		if ( isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return 0;
	}

	/**
	 * Host of $url, or empty string.
	 *
	 * @param string $url Request URL.
	 */
	private function url_host( string $url ): string {
		$host = $this->parse( $url, PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Path or query component as string.
	 *
	 * @param string $url       Request URL.
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
