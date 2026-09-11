<?php
/**
 * Signed element-hide rules: wp-admin CSS, no user selectors.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\ElementHide;

use WenPai\ChinaYes\Apps\ManifestVerifier;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Rest\Permissions;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id admin.element_hide. Empty source disables production fetch.
 *
 * Rules come from a signed element_hide document. Core notices are never hidden.
 */
final class ElementHideModule implements ConditionalModule {

	/**
	 * Production rules URL is not selected yet. Empty disables fetch.
	 *
	 * @since 4.0.0
	 */
	public const PRODUCTION_URL = '';

	/**
	 * Transient holding the last verified rules document.
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_KEY = 'wpcy_element_hide_rules';

	/**
	 * Option holding the monthly hit counter. Not a user setting.
	 *
	 * @since 4.0.0
	 */
	public const HITS_OPTION = 'wpcy_element_hide_hits';

	/**
	 * Stale cache TTL in seconds (72h).
	 *
	 * @since 4.0.0
	 */
	public const TTL = 259200;

	/**
	 * Cron hook for the daily pull.
	 *
	 * @since 4.0.0
	 */
	public const CRON_HOOK = 'wpcy_element_hide_refresh';

	/**
	 * WordPress generic notice class/id tokens. Rules naming these are dropped.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const GENERIC_CLASSES = array(
		'notice',
		'updated',
		'error',
		'update-nag',
		'is-dismissible',
		'inline',
	);

	/**
	 * Tokens that mark core update, security, or Site Health notices.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const PROTECTED_TOKENS = array(
		'core',
		'update-nag',
		'update_nag',
		'updated',
		'site-health',
		'site_health',
		'health-check',
		'health_check',
		'wp-site-health',
		'wp_site_health',
		'core-update',
		'core_update',
		'update-core',
		'update_core',
		'security',
		'notice',
	);

	/**
	 * Settings access.
	 *
	 * @var Repository
	 */
	private Repository $config;

	/**
	 * Local path or HTTPS URL. Empty means do not fetch.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Optional HTTP/file fetcher. Callable is not a PHP 7.4 property type.
	 *
	 * @var callable|null
	 */
	private $fetcher;

	/**
	 * Ed25519 verifier.
	 *
	 * @var ManifestVerifier
	 */
	private ManifestVerifier $verifier;

	/**
	 * Optional diagnostic logger.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Constructor. Does not register hooks or fetch.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository            $config   Settings access.
	 * @param string                $source   Fixture path or HTTPS URL. Empty disables fetch.
	 * @param callable|null         $fetcher  Optional `fn(string $source): string`.
	 * @param Logger|null           $logger   Diagnostic sink.
	 * @param ManifestVerifier|null $verifier Ed25519 verifier.
	 */
	public function __construct( Repository $config, string $source = '', $fetcher = null, $logger = null, $verifier = null ) {
		$this->config   = $config;
		$this->source   = $source;
		$this->fetcher  = is_callable( $fetcher ) ? $fetcher : null;
		$this->logger   = $logger instanceof Logger ? $logger : null;
		$this->verifier = $verifier instanceof ManifestVerifier ? $verifier : new ManifestVerifier();
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'admin.element_hide';
	}

	/**
	 * Admin plus REST (route registration) and cron.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
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
	 * True when not in recovery. The master switch only gates CSS output.
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

		return true;
	}

	/**
	 * Hook REST, admin hide, and refresh. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_head', array( $this, 'print_hide_styles' ) );
		add_action( self::CRON_HOOK, array( $this, 'cron_refresh' ) );

		if ( '' === $this->source ) {
			return;
		}
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			if ( function_exists( 'wp_schedule_event' ) ) {
				wp_schedule_event( time(), 'daily', self::CRON_HOOK );
			}
		}
	}

	/**
	 * Cron callback. refresh() returns the document and cannot be the hook.
	 *
	 * @since 4.0.0
	 */
	public function cron_refresh(): void {
		$this->refresh();
	}

	/**
	 * GET /element-hide for the diagnose outbound card.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			RestModule::NAMESPACE,
			'/element-hide',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_status' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);
	}

	/**
	 * Ruleset version, issued_at, monthly hits. Never errors.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function rest_status( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->diagnostics() );
	}

	/**
	 * Configured source. Empty when production fetch is disabled.
	 *
	 * @since 4.0.0
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Whether the master switch is on (default true).
	 *
	 * @since 4.0.0
	 */
	public function hide_promo_enabled(): bool {
		return true === $this->config->get( 'admin.hide_promo', true );
	}

	/**
	 * Diagnose payload: version, hits, issued_at, fetched_at, enabled.
	 *
	 * @since 4.0.0
	 *
	 * @return array{enabled: bool, version: int, issued_at: string, fetched_at: string, hits: int}
	 */
	public function diagnostics(): array {
		$cached = $this->cached_document();
		return array(
			'enabled'    => $this->hide_promo_enabled(),
			'version'    => is_array( $cached ) ? $cached['version'] : 0,
			'issued_at'  => is_array( $cached ) ? $cached['issued_at'] : '',
			'fetched_at' => is_array( $cached ) ? $cached['fetched_at'] : '',
			'hits'       => $this->monthly_hits(),
		);
	}

	/**
	 * Print hide styles for active rule selectors. Not user selectors.
	 *
	 * @since 4.0.0
	 */
	public function print_hide_styles(): void {
		if ( ! $this->hide_promo_enabled() ) {
			return;
		}
		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return;
		}

		$selectors = array();
		foreach ( $this->active_rules() as $rule ) {
			$selector = $this->sanitize_selector( $rule['selector'] );
			if ( '' === $selector ) {
				continue;
			}
			$selectors[] = $selector;
		}
		$selectors = array_values( array_unique( $selectors ) );
		if ( array() === $selectors ) {
			return;
		}

		echo '<style id="wpcy-element-hide">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.
		echo esc_html( implode( ',', $selectors ) ) . '{display:none!important;}';
		echo '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.

		$this->record_hit();
	}

	/**
	 * Fetch, verify, sanitize, replace the cache. Failure keeps a ≤72h document.
	 *
	 * @since 4.0.0
	 *
	 * @return array{version: int, issued_at: string, fetched_at: string, rules: list<array{id: string, target_plugin: string, selector: string, label: string}>}|null
	 */
	public function refresh() {
		$previous = $this->cached_document();
		if ( '' === $this->source ) {
			return $previous;
		}

		$raw = $this->read_source();
		if ( '' === $raw ) {
			return $this->keep_or_clear( $previous );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || ! $this->verifier->verify( $decoded ) ) {
			if ( $this->logger instanceof Logger ) {
				$this->logger->log(
					'warning',
					'Element hide rules signature invalid; keeping previous cache.',
					array(
						'code' => 'wpcy_element_hide_signature_invalid',
					)
				);
			}
			return $this->keep_or_clear( $previous );
		}

		unset( $decoded['signature'] );
		$clean = $this->sanitize_document( $decoded );
		if ( ! is_array( $clean ) ) {
			return $this->keep_or_clear( $previous );
		}

		$this->store( $clean );
		return $this->cached_document();
	}

	/**
	 * Last stored document that is still within 72h, or null.
	 *
	 * @since 4.0.0
	 *
	 * @return array{version: int, issued_at: string, fetched_at: string, rules: list<array{id: string, target_plugin: string, selector: string, label: string}>}|null
	 */
	public function cached_document() {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$stored = get_transient( self::TRANSIENT_KEY );
		$clean  = $this->sanitize_document( $stored );
		if ( ! is_array( $clean ) ) {
			return null;
		}
		if ( ! $this->within_stale_window( $clean ) ) {
			$this->clear_cache();
			return null;
		}

		return $clean;
	}

	/**
	 * Rules that passed the iron law.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{id: string, target_plugin: string, selector: string, label: string}>
	 */
	public function active_rules(): array {
		$cached = $this->cached_document();
		if ( ! is_array( $cached ) && '' !== $this->source ) {
			$this->refresh();
			$cached = $this->cached_document();
		}
		if ( ! is_array( $cached ) ) {
			return array();
		}

		return $cached['rules'];
	}

	/**
	 * Keep a spec-shaped rules document. Protected selectors are dropped.
	 *
	 * @param mixed $decoded Candidate.
	 * @return array{version: int, issued_at: string, fetched_at: string, rules: list<array{id: string, target_plugin: string, selector: string, label: string}>}|null
	 */
	private function sanitize_document( $decoded ) {
		if ( ! is_array( $decoded ) || ! isset( $decoded['rules'] ) || ! is_array( $decoded['rules'] ) ) {
			return null;
		}

		$version = isset( $decoded['version'] ) ? (int) $decoded['version'] : 0;
		if ( $version < 1 ) {
			return null;
		}

		$issued = isset( $decoded['issued_at'] ) && is_string( $decoded['issued_at'] )
			? $decoded['issued_at']
			: '';

		$fetched = isset( $decoded['fetched_at'] ) && is_string( $decoded['fetched_at'] )
			? $decoded['fetched_at']
			: '';

		$rules = array();
		foreach ( $decoded['rules'] as $row ) {
			$rule = $this->sanitize_rule( $row );
			if ( is_array( $rule ) ) {
				$rules[] = $rule;
			}
		}

		return array(
			'version'    => $version,
			'issued_at'  => $issued,
			'fetched_at' => $fetched,
			'rules'      => $rules,
		);
	}

	/**
	 * One rule. Rejects core / update-nag / Site Health selectors.
	 *
	 * @param mixed $row Candidate.
	 * @return array{id: string, target_plugin: string, selector: string, label: string}|null
	 */
	private function sanitize_rule( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}

		$id = isset( $row['id'] ) && is_string( $row['id'] ) ? trim( $row['id'] ) : '';
		if ( '' === $id || strlen( $id ) > 128 ) {
			return null;
		}

		$selector = isset( $row['selector'] ) && is_string( $row['selector'] ) ? trim( $row['selector'] ) : '';
		$selector = $this->sanitize_selector( $selector );
		if ( '' === $selector ) {
			return null;
		}

		$plugin = isset( $row['target_plugin'] ) && is_string( $row['target_plugin'] )
			? trim( $row['target_plugin'] )
			: '';
		if ( strlen( $plugin ) > 128 ) {
			$plugin = '';
		}

		$label = isset( $row['label'] ) && is_string( $row['label'] ) ? trim( $row['label'] ) : '';
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$label = wp_strip_all_tags( $label );
		}
		if ( strlen( $label ) > 200 ) {
			$label = substr( $label, 0, 200 );
		}

		return array(
			'id'            => $id,
			'target_plugin' => $plugin,
			'selector'      => $selector,
			'label'         => $label,
		);
	}

	/**
	 * Allowlisted CSS selector, or empty when unsafe / red-line.
	 *
	 * @param string $selector Raw selector.
	 */
	public function sanitize_selector( string $selector ): string {
		$selector = trim( $selector );
		if ( '' === $selector || strlen( $selector ) > 200 ) {
			return '';
		}
		$first = $selector[0];
		if ( '.' !== $first && '#' !== $first ) {
			return '';
		}
		if ( false !== strpos( $selector, '*' ) ) {
			return '';
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9_\-.#\[\]="\'\s>+~:()]+$/', $selector ) ) {
			return '';
		}
		if ( $this->is_red_line_selector( $selector ) ) {
			return '';
		}

		return $selector;
	}

	/**
	 * Whether $selector names a core update, security, or Site Health notice.
	 *
	 * @param string $selector CSS selector.
	 */
	public function is_red_line_selector( string $selector ): bool {
		$normalized = strtolower( str_replace( '_', '-', $selector ) );
		if ( $this->contains_protected_token( $normalized ) ) {
			return true;
		}

		if ( 1 !== preg_match_all( '/[.#]([a-z0-9_-]+)/', $normalized, $matches ) ) {
			return false;
		}
		foreach ( $matches[1] as $token ) {
			if ( in_array( $token, self::GENERIC_CLASSES, true ) ) {
				return true;
			}
			if ( 0 === strpos( $token, 'notice-' ) ) {
				return true;
			}
			if ( 'update-core' === $token || 0 === strpos( $token, 'update-core-' ) ) {
				return true;
			}
			if ( 0 === strpos( $token, 'site-health' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read the source. Local files do not use HTTP. Remote only https://wpcy.com/.
	 *
	 * @return string
	 */
	private function read_source(): string {
		if ( is_callable( $this->fetcher ) ) {
			$result = call_user_func( $this->fetcher, $this->source );
			return is_string( $result ) ? $result : '';
		}

		if ( $this->is_local_path( $this->source ) ) {
			if ( ! is_readable( $this->source ) ) {
				return '';
			}
			$raw = file_get_contents( $this->source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture, not a remote URL.
			return is_string( $raw ) ? $raw : '';
		}

		if ( 0 !== strpos( $this->source, 'https://wpcy.com/' ) ) {
			return '';
		}

		if ( ! function_exists( 'wp_remote_get' ) ) {
			return '';
		}

		$response = wp_remote_get(
			$this->source,
			array(
				'timeout'   => 10,
				'sslverify' => true,
			)
		);
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return '';
		}

		$code = function_exists( 'wp_remote_retrieve_response_code' )
			? (int) wp_remote_retrieve_response_code( $response )
			: 0;
		if ( 200 !== $code ) {
			return '';
		}

		if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Persist a sanitized document with fetched_at and 72h TTL.
	 *
	 * @param array{version: int, issued_at: string, fetched_at: string, rules: list<array{id: string, target_plugin: string, selector: string, label: string}>} $document Document.
	 */
	private function store( array $document ): void {
		$document['fetched_at'] = gmdate( 'Y-m-d\\TH:i:s\\Z' );
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::TRANSIENT_KEY, $document, self::TTL );
		}
	}

	/**
	 * Keep $previous when it is still within 72h; otherwise clear.
	 *
	 * @param array{version: int, issued_at: string, fetched_at: string, rules: list<array{id: string, target_plugin: string, selector: string, label: string}>}|null $previous Last document.
	 * @return array{version: int, issued_at: string, fetched_at: string, rules: list<array{id: string, target_plugin: string, selector: string, label: string}>}|null
	 */
	private function keep_or_clear( $previous ) {
		if ( is_array( $previous ) && $this->within_stale_window( $previous ) ) {
			return $previous;
		}
		$this->clear_cache();
		return null;
	}

	/**
	 * Whether $document was fetched within 72 hours.
	 *
	 * @param array<string, mixed> $document Stored document.
	 */
	private function within_stale_window( array $document ): bool {
		$fetched = isset( $document['fetched_at'] ) && is_string( $document['fetched_at'] )
			? $document['fetched_at']
			: '';
		if ( '' === $fetched ) {
			return false;
		}
		$ts = strtotime( $fetched );
		if ( false === $ts ) {
			return false;
		}

		return ( time() - $ts ) <= self::TTL;
	}

	/**
	 * Drop the cached rules document.
	 */
	private function clear_cache(): void {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}

	/**
	 * Whether $haystack contains a protected token.
	 *
	 * @param string $haystack Raw value.
	 */
	private function contains_protected_token( string $haystack ): bool {
		$normalized = strtolower( str_replace( '_', '-', $haystack ) );
		if ( '' === $normalized ) {
			return false;
		}

		foreach ( self::PROTECTED_TOKENS as $token ) {
			$needle = str_replace( '_', '-', strtolower( $token ) );
			if ( $normalized === $needle ) {
				return true;
			}
			if ( false !== strpos( $normalized, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether $source is a local filesystem path, not an HTTP URL.
	 *
	 * @param string $source Source.
	 */
	private function is_local_path( string $source ): bool {
		return 0 !== strpos( $source, 'http://' ) && 0 !== strpos( $source, 'https://' );
	}

	/**
	 * Increment this month's hide count by one page print.
	 */
	private function record_hit(): void {
		$month  = gmdate( 'Y-m' );
		$stored = $this->load_hits();
		if ( $stored['month'] !== $month ) {
			$stored = array(
				'month' => $month,
				'count' => 0,
			);
		}
		++$stored['count'];
		if ( function_exists( 'update_option' ) ) {
			update_option( self::HITS_OPTION, $stored, false );
		}
	}

	/**
	 * Hits recorded in the current UTC month.
	 */
	private function monthly_hits(): int {
		$stored = $this->load_hits();
		if ( $stored['month'] !== gmdate( 'Y-m' ) ) {
			return 0;
		}
		return $stored['count'];
	}

	/**
	 * Persisted monthly counter.
	 *
	 * @return array{month: string, count: int}
	 */
	private function load_hits(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array(
				'month' => gmdate( 'Y-m' ),
				'count' => 0,
			);
		}
		$stored = get_option( self::HITS_OPTION, array() );
		$month  = isset( $stored['month'] ) && is_string( $stored['month'] ) ? $stored['month'] : '';
		$count  = isset( $stored['count'] ) ? (int) $stored['count'] : 0;
		if ( '' === $month ) {
			$month = gmdate( 'Y-m' );
		}

		return array(
			'month' => $month,
			'count' => $count,
		);
	}
}
