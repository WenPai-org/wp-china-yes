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
	 * Last args passed to $request (tests).
	 *
	 * @var array<string, mixed>
	 */
	private array $last_request_args = array();

	/**
	 * Microtime when a direct (non-rewritten) version-check started.
	 *
	 * @var float
	 */
	private float $version_check_started = 0.0;

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
		add_filter( 'http_response', array( $this, 'observe_version_check' ), 10, 3 );
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
			if ( is_string( $url ) && $this->is_core_version_check( $url ) ) {
				$this->version_check_started = microtime( true );
			}
			return $preempt;
		}

		$args = is_array( $parsed_args ) ? $parsed_args : array();

		$timeout = isset( $args['timeout'] ) ? (float) $args['timeout'] : 10;
		if ( $timeout > 10 || $timeout <= 0 ) {
			$timeout = 10;
		}

		$args['timeout']         = $timeout;
		$args['sslverify']       = true;
		$this->last_request_url  = $mirror_url;
		$this->last_request_args = $args;

		$started  = microtime( true );
		$response = ( $this->request )( $mirror_url, $args );
		$elapsed  = microtime( true ) - $started;
		$this->count_mirror_response( $response, $url, $elapsed );

		return $response;
	}

	/**
	 * Direct (non-rewritten) version-check: record update_check with tone neutral.
	 *
	 * Mirror responses are counted in count_mirror_response(); this only
	 * observes api.wordpress.org so the inner mirror request is skipped.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed                $response HTTP result.
	 * @param array<string, mixed> $args     Request args.
	 * @param mixed                $url      Request URL.
	 * @return mixed
	 */
	public function observe_version_check( $response, $args, $url ) {
		unset( $args );
		if ( ! is_string( $url ) || ! $this->is_core_version_check( $url ) ) {
			return $response;
		}
		if ( Origins::UPSTREAM_API_HOST !== $this->url_host( $url ) ) {
			return $response;
		}
		$code = $this->response_code( $response );
		if ( $code < 200 || $code >= 300 || ! function_exists( 'do_action' ) ) {
			return $response;
		}
		$elapsed                     = $this->version_check_started > 0.0
			? microtime( true ) - $this->version_check_started
			: 0.0;
		$this->version_check_started = 0.0;
		do_action(
			'wpcy_events_record',
			'update_check',
			array(
				'version'    => $this->latest_core_version( $response ),
				'seconds'    => number_format( $elapsed, 1, '.', '' ),
				'via_mirror' => false,
			)
		);
		return $response;
	}

	/**
	 * Count 2xx rewritten responses and record update_check for version-check.
	 *
	 * @param mixed  $response HTTP result.
	 * @param string $original Original WordPress.org URL.
	 * @param float  $elapsed  Seconds.
	 */
	private function count_mirror_response( $response, string $original, float $elapsed ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$code = $this->response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return;
		}

		do_action( 'wpcy_stats_increment', 'mirror_downloads', 1 );
		$bytes = $this->response_bytes( $response );
		if ( $bytes > 0 ) {
			do_action( 'wpcy_stats_increment', 'mirror_bytes_saved', $bytes );
		}

		if ( ! $this->is_core_version_check( $original ) ) {
			return;
		}

		do_action(
			'wpcy_events_record',
			'update_check',
			array(
				'version'    => $this->latest_core_version( $response ),
				'seconds'    => number_format( $elapsed, 1, '.', '' ),
				'via_mirror' => true,
			)
		);
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
	 * Content-Length, else body length.
	 *
	 * @param mixed $response HTTP result.
	 */
	private function response_bytes( $response ): int {
		if ( ! is_array( $response ) ) {
			return 0;
		}
		$length = '';
		if ( function_exists( 'wp_remote_retrieve_header' ) ) {
			$header = wp_remote_retrieve_header( $response, 'content-length' );
			$length = is_string( $header ) ? $header : '';
		}
		if ( '' === $length && isset( $response['headers'] ) && is_array( $response['headers'] ) ) {
			foreach ( $response['headers'] as $key => $value ) {
				if ( is_string( $key ) && 0 === strcasecmp( $key, 'content-length' ) ) {
					$length = (string) $value;
					break;
				}
			}
		}
		if ( '' !== $length && is_numeric( $length ) ) {
			return max( 0, (int) $length );
		}
		$body = '';
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			$body = (string) wp_remote_retrieve_body( $response );
		} elseif ( isset( $response['body'] ) && is_string( $response['body'] ) ) {
			$body = $response['body'];
		}
		return strlen( $body );
	}

	/**
	 * Whether `$url` is a core version-check request.
	 *
	 * @param string $url Original URL.
	 */
	private function is_core_version_check( string $url ): bool {
		$path = $this->url_part( $url, PHP_URL_PATH );
		return false !== strpos( $path, '/core/version-check/' );
	}

	/**
	 * Newest core version from a version-check body, else current WP version.
	 *
	 * @param mixed $response HTTP result.
	 */
	private function latest_core_version( $response ): string {
		$body = '';
		if ( is_array( $response ) ) {
			if ( function_exists( 'wp_remote_retrieve_body' ) ) {
				$body = (string) wp_remote_retrieve_body( $response );
			} elseif ( isset( $response['body'] ) && is_string( $response['body'] ) ) {
				$body = $response['body'];
			}
		}
		if ( '' !== $body ) {
			$decoded = json_decode( $body, true );
			if ( is_array( $decoded ) && isset( $decoded['offers'] ) && is_array( $decoded['offers'] ) ) {
				foreach ( $decoded['offers'] as $offer ) {
					if ( is_array( $offer ) && isset( $offer['version'] ) && is_string( $offer['version'] ) && '' !== $offer['version'] ) {
						return $offer['version'];
					}
				}
			}
		}
		if ( isset( $GLOBALS['wp_version'] ) && is_string( $GLOBALS['wp_version'] ) && '' !== $GLOBALS['wp_version'] ) {
			return $GLOBALS['wp_version'];
		}
		return '';
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
	 * Last rewritten request args (empty if none).
	 *
	 * @return array<string, mixed>
	 */
	public function last_request_args(): array {
		return $this->last_request_args;
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
