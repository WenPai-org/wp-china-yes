<?php
/**
 * Versioned notice-hide rules. Core / security / Site Health are never hidden.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\NoticeControl;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Rest\Permissions;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id admin.notice_control. Empty source disables production fetch.
 *
 * Does not accept user CSS selectors. Rules come from versioned JSON.
 */
final class NoticeControlModule implements ConditionalModule {

	/**
	 * Production rules URL is not selected yet (M3). Empty disables fetch.
	 *
	 * @since 4.0.0
	 */
	public const PRODUCTION_URL = '';

	/**
	 * Transient holding the last successful rules document.
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_KEY = 'wpcy_notice_rules';

	/**
	 * Option holding the hidden-notice log. Not a user setting.
	 *
	 * @since 4.0.0
	 */
	public const LOG_OPTION = 'wpcy_notice_control_log';

	/**
	 * Cache TTL in seconds (24h).
	 *
	 * @since 4.0.0
	 */
	public const TTL = 86400;

	/**
	 * Cron hook for the daily pull.
	 *
	 * @since 4.0.0
	 */
	public const CRON_HOOK = 'wpcy_notice_rules_refresh';

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
	 * In-memory hidden log keyed by rule id.
	 *
	 * @var array<string, array{plugin: string, rule: string, first_hidden: string, count: int}>
	 */
	private array $log = array();

	/**
	 * Constructor. Does not register hooks or fetch.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository    $config  Settings access.
	 * @param string        $source  Fixture path or HTTPS URL. Empty disables fetch.
	 * @param callable|null $fetcher Optional `fn(string $source): string`.
	 */
	public function __construct( Repository $config, string $source = '', $fetcher = null ) {
		$this->config  = $config;
		$this->source  = $source;
		$this->fetcher = is_callable( $fetcher ) ? $fetcher : null;
		$this->log     = $this->load_log();
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'admin.notice_control';
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
	 * True when modules.notice_control is true and not recovery.
	 *
	 * @since 4.0.0
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		unset( $environment );

		if ( true === $this->config->get( 'recovery_mode', false ) ) {
			return false;
		}

		return true === $config->get( 'modules.notice_control', false );
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
	 * GET /notice-control/hidden for the diagnose DataViews tab.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			RestModule::NAMESPACE,
			'/notice-control/hidden',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_hidden' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);
	}

	/**
	 * Hidden-notice log for diagnose. Never errors.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function rest_hidden( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( array( 'items' => $this->hidden_items() ) );
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
	 * Whether hook / class / plugin names a core update, security, or Site Health notice.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hook        Admin hook.
	 * @param string $css_class   Notice CSS class.
	 * @param string $plugin      Plugin slug.
	 */
	public function is_protected( string $hook, string $css_class, string $plugin = '' ): bool {
		return $this->contains_protected_token( $hook )
			|| $this->contains_protected_token( $css_class )
			|| $this->contains_protected_token( $plugin );
	}

	/**
	 * Whether this notice should be hidden. Protected targets never match.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hook      Admin hook.
	 * @param string $css_class Notice CSS class.
	 * @param string $plugin    Plugin slug.
	 */
	public function should_hide( string $hook, string $css_class, string $plugin = '' ): bool {
		if ( $this->is_protected( $hook, $css_class, $plugin ) ) {
			return false;
		}

		return is_array( $this->matching_rule( $hook, $css_class ) );
	}

	/**
	 * First matching non-protected rule, or null.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hook      Admin hook.
	 * @param string $css_class Notice CSS class.
	 * @return array{id: string, plugin: string, hook: string, class: string}|null
	 */
	public function matching_rule( string $hook, string $css_class ) {
		foreach ( $this->active_rules() as $rule ) {
			if ( '' !== $rule['hook'] && 0 !== strcasecmp( $rule['hook'], $hook ) ) {
				continue;
			}
			if ( '' !== $rule['class'] && ! $this->class_list_has( $css_class, $rule['class'] ) ) {
				continue;
			}
			if ( '' === $rule['hook'] && '' === $rule['class'] ) {
				continue;
			}
			return $rule;
		}

		return null;
	}

	/**
	 * Record a hide when the notice is not protected and matches a rule.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hook      Admin hook.
	 * @param string $css_class Notice CSS class.
	 * @param string $plugin    Plugin slug.
	 * @return bool True when hidden.
	 */
	public function consider( string $hook, string $css_class, string $plugin = '' ): bool {
		if ( $this->is_protected( $hook, $css_class, $plugin ) ) {
			return false;
		}

		$rule = $this->matching_rule( $hook, $css_class );
		if ( ! is_array( $rule ) ) {
			return false;
		}

		$this->record_hidden( $rule, $plugin );
		return true;
	}

	/**
	 * Hidden rows for diagnose: plugin / rule / first_hidden / count.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{id: string, plugin: string, rule: string, first_hidden: string, count: int}>
	 */
	public function hidden_items(): array {
		$out = array();
		foreach ( $this->log as $row ) {
			$out[] = array(
				'id'           => $row['rule'],
				'plugin'       => $row['plugin'],
				'rule'         => $row['rule'],
				'first_hidden' => $row['first_hidden'],
				'count'        => $row['count'],
			);
		}
		return $out;
	}

	/**
	 * Print hide styles for active rule classes. Not user selectors.
	 *
	 * @since 4.0.0
	 */
	public function print_hide_styles(): void {
		$selectors = array();
		foreach ( $this->active_rules() as $rule ) {
			$name = $this->sanitize_class( $rule['class'] );
			if ( '' === $name ) {
				continue;
			}
			$selectors[] = '.' . $name;
			$this->record_hidden( $rule, $rule['plugin'] );
		}
		$selectors = array_values( array_unique( $selectors ) );
		if ( array() === $selectors ) {
			return;
		}

		echo '<style id="wpcy-notice-control">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.
		echo esc_html( implode( ',', $selectors ) ) . '{display:none!important;}';
		echo '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.
	}

	/**
	 * Fetch, sanitize, replace the cache. Failure keeps the previous cache.
	 *
	 * @since 4.0.0
	 *
	 * @return array{rules_version: int, issued_at: string, rules: list<array{id: string, plugin: string, hook: string, class: string}>}|null
	 */
	public function refresh() {
		$previous = $this->cached_document();
		if ( '' === $this->source ) {
			return $previous;
		}

		$raw = $this->read_source();
		if ( '' === $raw ) {
			return $previous;
		}

		$decoded = json_decode( $raw, true );
		$clean   = $this->sanitize_document( $decoded );
		if ( ! is_array( $clean ) ) {
			return $previous;
		}

		$this->store( $clean );
		return $clean;
	}

	/**
	 * Last stored document, or null.
	 *
	 * @since 4.0.0
	 *
	 * @return array{rules_version: int, issued_at: string, rules: list<array{id: string, plugin: string, hook: string, class: string}>}|null
	 */
	public function cached_document() {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$stored = get_transient( self::TRANSIENT_KEY );
		$clean  = $this->sanitize_document( $stored );
		return is_array( $clean ) ? $clean : null;
	}

	/**
	 * Rules that passed the iron law.
	 *
	 * @return list<array{id: string, plugin: string, hook: string, class: string}>
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
	 * Append or increment a hidden-notice row.
	 *
	 * @param array{id: string, plugin: string, hook: string, class: string} $rule   Matched rule.
	 * @param string                                                         $plugin Plugin slug override.
	 */
	private function record_hidden( array $rule, string $plugin ): void {
		$id = $rule['id'];
		if ( '' === $id ) {
			return;
		}

		$now    = gmdate( 'Y-m-d\\TH:i:s\\Z' );
		$source = '' !== $plugin ? $plugin : $rule['plugin'];

		if ( ! isset( $this->log[ $id ] ) ) {
			$this->log[ $id ] = array(
				'plugin'       => $source,
				'rule'         => $id,
				'first_hidden' => $now,
				'count'        => 0,
			);
		}

		++$this->log[ $id ]['count'];
		if ( '' !== $source ) {
			$this->log[ $id ]['plugin'] = $source;
		}

		$this->persist_log();
	}

	/**
	 * Keep a spec-shaped rules document. Protected rules are dropped.
	 *
	 * @param mixed $decoded Candidate.
	 * @return array{rules_version: int, issued_at: string, rules: list<array{id: string, plugin: string, hook: string, class: string}>}|null
	 */
	private function sanitize_document( $decoded ) {
		if ( ! is_array( $decoded ) || ! isset( $decoded['rules'] ) || ! is_array( $decoded['rules'] ) ) {
			return null;
		}

		$version = isset( $decoded['rules_version'] ) ? (int) $decoded['rules_version'] : 0;
		if ( $version < 1 ) {
			return null;
		}

		$issued = isset( $decoded['issued_at'] ) && is_string( $decoded['issued_at'] )
			? $decoded['issued_at']
			: '';

		$rules = array();
		foreach ( $decoded['rules'] as $row ) {
			$rule = $this->sanitize_rule( $row );
			if ( is_array( $rule ) ) {
				$rules[] = $rule;
			}
		}

		return array(
			'rules_version' => $version,
			'issued_at'     => $issued,
			'rules'         => $rules,
		);
	}

	/**
	 * One rule. Rejects core / update-nag / Site Health targets.
	 *
	 * @param mixed $row Candidate.
	 * @return array{id: string, plugin: string, hook: string, class: string}|null
	 */
	private function sanitize_rule( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}

		$id = isset( $row['id'] ) && is_string( $row['id'] ) ? trim( $row['id'] ) : '';
		if ( '' === $id || strlen( $id ) > 128 ) {
			return null;
		}

		$plugin = isset( $row['plugin'] ) && is_string( $row['plugin'] ) ? trim( $row['plugin'] ) : '';
		$hook   = isset( $row['hook'] ) && is_string( $row['hook'] ) ? trim( $row['hook'] ) : '';
		$class  = isset( $row['class'] ) && is_string( $row['class'] ) ? trim( $row['class'] ) : '';

		if ( isset( $row['selector'] ) && is_string( $row['selector'] ) && '' !== $row['selector'] ) {
			return null;
		}

		if ( $this->is_protected( $hook, $class, $plugin ) ) {
			return null;
		}

		if ( '' === $hook && '' === $class ) {
			return null;
		}

		return array(
			'id'     => $id,
			'plugin' => $plugin,
			'hook'   => $hook,
			'class'  => $class,
		);
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
	 * Persist a sanitized document.
	 *
	 * @param array{rules_version: int, issued_at: string, rules: list<array{id: string, plugin: string, hook: string, class: string}>} $document Document.
	 */
	private function store( array $document ): void {
		if ( function_exists( 'set_transient' ) ) {
			$ttl = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : self::TTL;
			set_transient( self::TRANSIENT_KEY, $document, $ttl );
		}
	}

	/**
	 * Whether $haystack (hook, class list, or plugin) contains a protected token.
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
	 * Whether a space-separated class list contains $needle.
	 *
	 * @param string $class_list HTML class attribute.
	 * @param string $needle     Rule class.
	 */
	private function class_list_has( string $class_list, string $needle ): bool {
		$needle = strtolower( trim( $needle ) );
		if ( '' === $needle ) {
			return false;
		}
		$parts = preg_split( '/\s+/', strtolower( $class_list ) );
		if ( ! is_array( $parts ) ) {
			return false;
		}
		return in_array( $needle, $parts, true );
	}

	/**
	 * Single HTML class token from a rule class field.
	 *
	 * @param string $css_class Raw class.
	 */
	private function sanitize_class( string $css_class ): string {
		$token = strtok( trim( $css_class ), ' ' );
		if ( ! is_string( $token ) ) {
			return '';
		}
		if ( function_exists( 'sanitize_html_class' ) ) {
			return sanitize_html_class( $token );
		}
		$clean = preg_replace( '/[^A-Za-z0-9_-]/', '', $token );
		return is_string( $clean ) ? $clean : '';
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
	 * Load the persisted hidden log.
	 *
	 * @return array<string, array{plugin: string, rule: string, first_hidden: string, count: int}>
	 */
	private function load_log(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}
		$stored = get_option( self::LOG_OPTION, array() );
		return is_array( $stored ) ? $this->sanitize_log( $stored ) : array();
	}

	/**
	 * Write the hidden log.
	 */
	private function persist_log(): void {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::LOG_OPTION, $this->log, false );
		}
	}

	/**
	 * Keep plugin / rule / first_hidden / count only.
	 *
	 * @param mixed $stored Option value.
	 * @return array<string, array{plugin: string, rule: string, first_hidden: string, count: int}>
	 */
	private function sanitize_log( $stored ): array {
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$out = array();
		foreach ( $stored as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = is_string( $key ) ? $key : '';
			if ( isset( $row['rule'] ) && is_string( $row['rule'] ) && '' !== $row['rule'] ) {
				$id = $row['rule'];
			}
			if ( '' === $id ) {
				continue;
			}
			$out[ $id ] = array(
				'plugin'       => isset( $row['plugin'] ) && is_string( $row['plugin'] ) ? $row['plugin'] : '',
				'rule'         => $id,
				'first_hidden' => isset( $row['first_hidden'] ) && is_string( $row['first_hidden'] ) ? $row['first_hidden'] : '',
				'count'        => isset( $row['count'] ) ? (int) $row['count'] : 0,
			);
		}

		return $out;
	}
}
