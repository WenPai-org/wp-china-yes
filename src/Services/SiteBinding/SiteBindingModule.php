<?php
/**
 * Anonymous site-binding challenge flow. Does not auto-start without a mock URL.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Services\SiteBinding;

use WenPai\ChinaYes\Admin\AdminModule;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Core\Module;
use WenPai\ChinaYes\Services\Catalog\CatalogModule;
use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * State machine: unbound → pending → bound | revoked.
 *
 * Challenge token lives in a transient (not wpcy_site_identity). The public
 * REST endpoint only returns it while status is pending and expires_at is
 * in the future. Credentials are sealed before they touch the option.
 */
final class SiteBindingModule implements Module {

	/**
	 * Transient key prefix. Token is not a long-lived identity field.
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_PREFIX = 'wpcy_binding_challenge_';

	/**
	 * One-shot cron hook that retries confirm() after start().
	 *
	 * @since 4.0.0
	 */
	public const CRON_HOOK = 'wpcy_binding_confirm';

	/**
	 * Maximum confirm attempts (in-request plus cron retries).
	 *
	 * @since 4.0.0
	 */
	public const MAX_CONFIRM_ATTEMPTS = 10;

	/**
	 * Seconds before the next confirm retry.
	 *
	 * @since 4.0.0
	 */
	public const CONFIRM_RETRY_DELAY = 60;

	/**
	 * Allowed scopes for `wenpai_sign_cloud_request`.
	 *
	 * @since 4.0.0
	 *
	 * @var list<string>
	 */
	public const CLOUD_SIGN_SCOPES = array(
		'mind:gateway',
		'wpmind:gateway',
	);

	/**
	 * Default trusted hosts for `wenpai_sign_cloud_request`.
	 *
	 * @since 4.0.0
	 *
	 * @var list<string>
	 */
	public const CLOUD_SIGN_DEFAULT_HOSTS = array(
		'mind-api.wenpai.net',
	);

	/**
	 * Short-lived derived bearer token TTL in seconds.
	 *
	 * @since 4.0.0
	 */
	public const CLOUD_SIGN_TTL = 300;

	/**
	 * Domain separation prefix when deriving per-scope HMAC keys from the site credential.
	 *
	 * @since 4.0.0
	 */
	public const CLOUD_SIGN_KDF_PREFIX = 'wenpai-cloud-sign-v1:';

	/**
	 * Settings / identity access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * License-server client.
	 *
	 * @var ChallengeClient
	 */
	private ChallengeClient $client;

	/**
	 * Sodium sealer for binding.credential.
	 *
	 * @var CredentialStore
	 */
	private CredentialStore $credentials;

	/**
	 * Kernel logger. Null skips log lines.
	 *
	 * PHP 7.4 has no union property types.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Constructor. Does not register hooks and does not start a challenge.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository           $repository  Identity access.
	 * @param Logger|null          $logger      Failure sink.
	 * @param ChallengeClient|null $client      HTTP client. Null builds the default.
	 * @param CredentialStore|null $credentials Sealer. Null builds the default.
	 */
	public function __construct( Repository $repository, $logger = null, $client = null, $credentials = null ) {
		$this->repository  = $repository;
		$this->logger      = $logger instanceof Logger ? $logger : null;
		$this->client      = $client instanceof ChallengeClient ? $client : new ChallengeClient( $this->logger );
		$this->credentials = $credentials instanceof CredentialStore ? $credentials : new CredentialStore();
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'services.site_binding';
	}

	/**
	 * Binding is consulted from REST, cron, and admin.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}

	/**
	 * Ensure site_uuid exists when option writes are available. Never auto-POSTs.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'cron_confirm' ) );
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'wenpai_site_binding', array( $this, 'filter_suite_binding' ) );
			add_filter( 'wenpai_sign_cloud_request', array( $this, 'filter_sign_cloud_request' ), 10, 2 );
		}
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}
		$this->repository->get_identity();
	}

	/**
	 * Suite-wide read-only site binding snapshot for companion WenPai plugins.
	 *
	 * Never exposes the sealed credential; standalone plugins inspect `managed`
	 * and `bound` so they do not render duplicate site-binding prompts when WPCY is active.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $binding Default binding payload from caller.
	 * @return array{managed: bool, provider: string, status: string, bound: bool, site_uuid: string, site_hash: string|null, bound_at: string|null, manage_url: string}
	 */
	public function filter_suite_binding( $binding = array() ): array {
		unset( $binding );
		$identity = $this->repository->get_identity();
		$snap     = $this->snapshot();

		return array(
			'managed'    => true,
			'provider'   => 'wp-china-yes',
			'status'     => $snap['status'],
			'bound'      => 'bound' === $snap['status'],
			'site_uuid'  => isset( $identity['site_uuid'] ) && is_string( $identity['site_uuid'] ) ? $identity['site_uuid'] : '',
			'site_hash'  => $snap['site_hash'],
			'bound_at'   => $snap['bound_at'],
			'manage_url' => AdminModule::app_url( 'wpcy-licenses' ),
		);
	}

	/**
	 * Derive a short-lived HMAC-SHA256 bearer token for trusted WenPai cloud endpoints.
	 *
	 * Never returns the raw site-binding credential. Passes through any already-populated
	 * non-empty token string unchanged, and returns `$token` untouched when the site is
	 * unbound, the credential cannot be unsealed, or scope/target validation fails.
	 *
	 * Token format: `v1.<base64url(payload_json)>.<base64url(hmac_sha256_raw)>`.
	 * Signing key: `hash_hmac( 'sha256', self::CLOUD_SIGN_KDF_PREFIX . $scope, $raw_credential, true )`.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $token   Incoming bearer token from caller or earlier filter.
	 * @param mixed $context Request context array containing `scope` and `target`.
	 * @return mixed
	 */
	public function filter_sign_cloud_request( $token = '', $context = array() ) {
		if ( is_string( $token ) && '' !== trim( $token ) ) {
			return $token;
		}

		if ( ! is_array( $context ) ) {
			return $token;
		}

		$scope = isset( $context['scope'] ) && is_string( $context['scope'] ) ? trim( $context['scope'] ) : '';
		if ( ! in_array( $scope, self::CLOUD_SIGN_SCOPES, true ) ) {
			return $token;
		}

		$target = isset( $context['target'] ) && is_string( $context['target'] ) ? trim( $context['target'] ) : '';
		if ( ! $this->is_allowed_cloud_target( $target, $scope ) ) {
			return $token;
		}

		$identity  = $this->repository->get_identity();
		$binding   = $this->binding_from( $identity );
		$site_uuid = isset( $identity['site_uuid'] ) && is_string( $identity['site_uuid'] ) ? trim( $identity['site_uuid'] ) : '';
		if ( 'bound' !== $binding['status'] || '' === $site_uuid ) {
			return $token;
		}

		if ( ! is_string( $binding['credential'] ) || '' === $binding['credential'] ) {
			return $token;
		}

		$raw_credential = $this->credentials->open( $binding['credential'] );
		if ( ! is_string( $raw_credential ) || '' === $raw_credential ) {
			return $token;
		}

		$iat     = time();
		$payload = array(
			'site_uuid' => $site_uuid,
			'site_hash' => $binding['site_hash'],
			'scope'     => $scope,
			'target'    => $target,
			'iat'       => $iat,
			'exp'       => $iat + self::CLOUD_SIGN_TTL,
			'nonce'     => bin2hex( random_bytes( 16 ) ),
		);

		$json = function_exists( 'wp_json_encode' )
			? wp_json_encode( $payload )
			: json_encode( $payload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fallback when WordPress is not loaded.
		if ( ! is_string( $json ) ) {
			return $token;
		}

		$signing_input = 'v1.' . self::base64url_encode( $json );
		$secret        = hash_hmac( 'sha256', self::CLOUD_SIGN_KDF_PREFIX . $scope, $raw_credential, true );
		$signature     = hash_hmac( 'sha256', $signing_input, $secret, true );

		return $signing_input . '.' . self::base64url_encode( $signature );
	}

	/**
	 * Public snapshot for GET /binding. No credential, no challenge_token.
	 *
	 * @since 4.0.0
	 *
	 * @return array{status: string, site_hash: string|null, bound_at: string|null}
	 */
	public function snapshot(): array {
		$binding = $this->binding();

		return array(
			'status'    => $binding['status'],
			'site_hash' => $binding['site_hash'],
			'bound_at'  => $binding['bound_at'],
		);
	}

	/**
	 * POST a challenge and store pending state. Token goes to a transient.
	 *
	 * @since 4.0.0
	 *
	 * @return array{status: string, challenge_id?: string, expires_at?: string, site_hash?: string|null, bound_at?: string|null}|WP_Error
	 */
	public function start() {
		if ( ! ChallengeClient::outbound_allowed() ) {
			return ChallengeClient::unavailable();
		}

		$identity = $this->repository->get_identity();
		$body     = array(
			'site_url'       => $this->site_url(),
			'site_uuid'      => (string) $identity['site_uuid'],
			'plugin_version' => defined( 'CHINA_YES_VERSION' ) ? (string) CHINA_YES_VERSION : '',
		);

		$result = $this->client->start( $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->store_pending( $identity, $result );
		$this->write_attempts( $result['challenge_id'], 0 );

		$confirmed = $this->confirm();
		if ( ! is_wp_error( $confirmed ) && 'bound' === $confirmed['status'] ) {
			return $confirmed;
		}

		$this->bump_attempts( $result['challenge_id'] );
		$this->schedule_confirm_retry();

		return array(
			'status'       => 'pending',
			'challenge_id' => $result['challenge_id'],
			'expires_at'   => $result['expires_at'],
		);
	}

	/**
	 * Public challenge token. Only pending and unexpired.
	 *
	 * @since 4.0.0
	 *
	 * @param string $challenge_id Query id.
	 * @return array{challenge_token: string}|WP_Error
	 */
	public function public_challenge( string $challenge_id ) {
		$id = ChallengeClient::sanitize_id( $challenge_id );
		if ( '' === $id ) {
			return $this->not_pending();
		}

		$binding = $this->binding();
		if ( 'pending' !== $binding['status'] || $id !== (string) $binding['challenge_id'] ) {
			return $this->not_pending();
		}

		$stored = $this->read_transient( $id );
		if ( ! is_array( $stored ) ) {
			return $this->not_pending();
		}

		$token   = isset( $stored['challenge_token'] ) && is_string( $stored['challenge_token'] ) ? $stored['challenge_token'] : '';
		$expires = isset( $stored['expires_at'] ) && is_string( $stored['expires_at'] ) ? $stored['expires_at'] : '';
		if ( '' === $token || $this->is_expired( $expires ) ) {
			return $this->not_pending();
		}

		return array(
			'challenge_token' => $token,
		);
	}

	/**
	 * Confirm the current pending challenge. Seals credential, status=bound.
	 *
	 * @since 4.0.0
	 *
	 * @return array{status: string, site_hash: string|null, bound_at: string|null}|WP_Error
	 */
	public function confirm() {
		if ( ! ChallengeClient::outbound_allowed() ) {
			return ChallengeClient::unavailable();
		}

		$identity     = $this->repository->get_identity();
		$binding      = $this->binding_from( $identity );
		$challenge_id = (string) $binding['challenge_id'];
		if ( 'pending' !== $binding['status'] || '' === ChallengeClient::sanitize_id( $challenge_id ) ) {
			return $this->not_pending();
		}

		$stored  = $this->read_transient( $challenge_id );
		$expires = is_array( $stored ) && isset( $stored['expires_at'] ) && is_string( $stored['expires_at'] )
			? $stored['expires_at']
			: '';
		if ( $this->is_expired( $expires ) ) {
			return $this->not_pending();
		}

		$result = $this->client->confirm( $challenge_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$sealed = $this->credentials->seal( $result['credential'] );
		if ( ! is_string( $sealed ) || '' === $sealed ) {
			$this->warn( 'Site binding credential could not be sealed.', array() );
			return ChallengeClient::unavailable();
		}

		$now     = gmdate( 'Y-m-d\TH:i:s\Z' );
		$binding = array(
			'status'       => 'bound',
			'site_hash'    => $result['site_hash'],
			'credential'   => $sealed,
			'bound_at'     => $now,
			'challenge_id' => null,
		);

		$identity['binding'] = $binding;
		$this->repository->set_identity( $identity );
		$this->delete_transient( $challenge_id );
		$this->clear_confirm_retry( $challenge_id );

		return $this->snapshot();
	}

	/**
	 * Cron callback: retry confirm() while pending; mark failed after 10 attempts.
	 *
	 * @since 4.0.0
	 */
	public function cron_confirm(): void {
		$binding = $this->binding();
		if ( 'pending' !== $binding['status'] ) {
			return;
		}

		$challenge_id = (string) $binding['challenge_id'];
		$confirmed    = $this->confirm();
		if ( ! is_wp_error( $confirmed ) && 'bound' === $confirmed['status'] ) {
			return;
		}

		$attempts = $this->bump_attempts( $challenge_id );
		if ( $attempts >= self::MAX_CONFIRM_ATTEMPTS ) {
			$this->mark_failed( $challenge_id );
			return;
		}

		$this->schedule_confirm_retry();
	}

	/**
	 * Local revoke plus best-effort server connection revoke (`DELETE /v1/sites/{site_hash}/connection`).
	 *
	 * Local state is cleared first so offline or server-error revokes always succeed immediately.
	 *
	 * @since 4.0.0
	 *
	 * @return array{status: string, site_hash: string|null, bound_at: string|null}
	 */
	public function revoke(): array {
		$identity     = $this->repository->get_identity();
		$binding      = $this->binding_from( $identity );
		$challenge_id = (string) $binding['challenge_id'];
		$prev_status  = $binding['status'];
		$prev_hash    = is_string( $binding['site_hash'] ) ? $binding['site_hash'] : '';
		$prev_cred    = is_string( $binding['credential'] ) && '' !== $binding['credential']
			? $this->credentials->open( $binding['credential'] )
			: null;

		$binding['status']       = 'revoked';
		$binding['credential']   = null;
		$binding['challenge_id'] = null;
		$identity['binding']     = $binding;
		$this->repository->set_identity( $identity );

		if ( '' !== $challenge_id ) {
			$this->delete_transient( $challenge_id );
			$this->clear_confirm_retry( $challenge_id );
		} else {
			$this->clear_confirm_retry( '' );
		}

		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( EntitlementsModule::TRANSIENT_FRESH );
			delete_transient( EntitlementsModule::TRANSIENT_STALE );
			delete_transient( CatalogModule::TRANSIENT_FRESH );
			delete_transient( CatalogModule::TRANSIENT_STALE );
		}

		if ( 'bound' === $prev_status && '' !== $prev_hash && is_string( $prev_cred ) && '' !== $prev_cred ) {
			$this->client->revoke( $prev_hash, $prev_cred );
		}

		return $this->snapshot();
	}

	/**
	 * Current binding object with safe defaults.
	 *
	 * @return array{status: string, site_hash: string|null, credential: string|null, bound_at: string|null, challenge_id: string|null}
	 */
	private function binding(): array {
		return $this->binding_from( $this->repository->get_identity() );
	}

	/**
	 * Binding segment of an identity document.
	 *
	 * @param array<string, mixed> $identity Identity.
	 * @return array{status: string, site_hash: string|null, credential: string|null, bound_at: string|null, challenge_id: string|null}
	 */
	private function binding_from( array $identity ): array {
		$raw = isset( $identity['binding'] ) && is_array( $identity['binding'] ) ? $identity['binding'] : array();

		return array(
			'status'       => isset( $raw['status'] ) && is_string( $raw['status'] ) ? $raw['status'] : 'unbound',
			'site_hash'    => isset( $raw['site_hash'] ) && is_string( $raw['site_hash'] ) ? $raw['site_hash'] : null,
			'credential'   => isset( $raw['credential'] ) && is_string( $raw['credential'] ) ? $raw['credential'] : null,
			'bound_at'     => isset( $raw['bound_at'] ) && is_string( $raw['bound_at'] ) ? $raw['bound_at'] : null,
			'challenge_id' => isset( $raw['challenge_id'] ) && is_string( $raw['challenge_id'] ) ? $raw['challenge_id'] : null,
		);
	}

	/**
	 * Persist pending identity fields and the short-lived token transient.
	 *
	 * @param array<string, mixed>                                                     $identity Current identity.
	 * @param array{challenge_id: string, challenge_token: string, expires_at: string} $result   Challenge response.
	 */
	private function store_pending( array $identity, array $result ): void {
		$previous = $this->binding_from( $identity );
		if ( is_string( $previous['challenge_id'] ) && '' !== $previous['challenge_id'] ) {
			$this->delete_transient( $previous['challenge_id'] );
		}

		$identity['binding'] = array(
			'status'       => 'pending',
			'site_hash'    => $previous['site_hash'],
			'credential'   => $previous['credential'],
			'bound_at'     => $previous['bound_at'],
			'challenge_id' => $result['challenge_id'],
		);
		$this->repository->set_identity( $identity );

		$ttl = $this->ttl_seconds( $result['expires_at'] );
		set_transient(
			self::TRANSIENT_PREFIX . $result['challenge_id'],
			array(
				'challenge_token' => $result['challenge_token'],
				'expires_at'      => $result['expires_at'],
			),
			$ttl
		);
	}

	/**
	 * Transient payload or null.
	 *
	 * @param string $challenge_id Id.
	 * @return array<string, mixed>|null
	 */
	private function read_transient( string $challenge_id ) {
		$stored = get_transient( self::TRANSIENT_PREFIX . $challenge_id );
		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Drop the short-lived token.
	 *
	 * @param string $challenge_id Id.
	 */
	private function delete_transient( string $challenge_id ): void {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::TRANSIENT_PREFIX . $challenge_id );
		}
	}

	/**
	 * Seconds until expires_at. Minimum 1 so set_transient still writes.
	 *
	 * @param string $expires_at UTC ISO 8601.
	 */
	private function ttl_seconds( string $expires_at ): int {
		$ts = strtotime( $expires_at );
		if ( false === $ts ) {
			return 1;
		}
		$ttl = $ts - time();
		return $ttl > 0 ? $ttl : 1;
	}

	/**
	 * Whether expires_at is missing or in the past.
	 *
	 * @param string $expires_at UTC ISO 8601.
	 */
	private function is_expired( string $expires_at ): bool {
		if ( '' === $expires_at ) {
			return true;
		}
		$ts = strtotime( $expires_at );
		if ( false === $ts ) {
			return true;
		}

		return $ts <= time();
	}

	/**
	 * Public site URL used in the challenge body.
	 */
	private function site_url(): string {
		if ( function_exists( 'site_url' ) ) {
			return (string) site_url();
		}

		return '';
	}

	/**
	 * Frozen public-endpoint error. HTTP 409 matches rest-api.md.
	 *
	 * @return WP_Error
	 */
	private function not_pending(): WP_Error {
		return new WP_Error(
			'wpcy_binding_not_pending',
			__( '暂时无法验证站点绑定，请重新发起绑定。', 'wp-china-yes' ),
			array(
				'status' => 409,
			)
		);
	}

	/**
	 * Warning without secrets.
	 *
	 * @param string               $message English log line.
	 * @param array<string, mixed> $context Host / path / request_id only.
	 */
	private function warn( string $message, array $context ): void {
		if ( $this->logger instanceof Logger ) {
			$this->logger->log( 'warning', $message, $context );
		}
	}

	/**
	 * Schedule a one-shot confirm retry in 60 seconds.
	 */
	private function schedule_confirm_retry(): void {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			return;
		}
		if ( function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_single_event( time() + self::CONFIRM_RETRY_DELAY, self::CRON_HOOK );
	}

	/**
	 * Drop confirm retry state for a challenge.
	 *
	 * @param string $challenge_id Challenge id, or empty to only unschedule.
	 */
	private function clear_confirm_retry( string $challenge_id ): void {
		if ( '' !== $challenge_id ) {
			$this->delete_transient( 'attempts_' . $challenge_id );
		}
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Persist attempt count for a challenge.
	 *
	 * @param string $challenge_id Challenge id.
	 * @param int    $attempts     Count.
	 */
	private function write_attempts( string $challenge_id, int $attempts ): void {
		$id = ChallengeClient::sanitize_id( $challenge_id );
		if ( '' === $id || ! function_exists( 'set_transient' ) ) {
			return;
		}
		$ttl = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
		set_transient( self::TRANSIENT_PREFIX . 'attempts_' . $id, $attempts, $ttl );
	}

	/**
	 * Increment and return the confirm attempt count.
	 *
	 * @param string $challenge_id Challenge id.
	 */
	private function bump_attempts( string $challenge_id ): int {
		$id = ChallengeClient::sanitize_id( $challenge_id );
		if ( '' === $id ) {
			return self::MAX_CONFIRM_ATTEMPTS;
		}
		$key    = self::TRANSIENT_PREFIX . 'attempts_' . $id;
		$stored = function_exists( 'get_transient' ) ? get_transient( $key ) : 0;
		$count  = is_numeric( $stored ) ? (int) $stored : 0;
		++$count;
		$this->write_attempts( $id, $count );
		return $count;
	}

	/**
	 * Mark the pending challenge failed so the user can start again.
	 *
	 * @param string $challenge_id Challenge id.
	 */
	private function mark_failed( string $challenge_id ): void {
		$identity                = $this->repository->get_identity();
		$binding                 = $this->binding_from( $identity );
		$binding['status']       = 'failed';
		$binding['challenge_id'] = null;
		$identity['binding']     = $binding;
		$this->repository->set_identity( $identity );
		$this->delete_transient( $challenge_id );
		$this->clear_confirm_retry( $challenge_id );
		$this->warn(
			'Site binding confirm retries exhausted.',
			array(
				'status' => 'failed',
			)
		);
	}

	/**
	 * Validate that a cloud-signing target is an HTTPS URL on an allowlisted host.
	 *
	 * @param string $target Target URL.
	 * @param string $scope  Validated request scope.
	 * @return bool
	 */
	private function is_allowed_cloud_target( string $target, string $scope ): bool {
		if ( '' === $target ) {
			return false;
		}

		$parts = function_exists( 'wp_parse_url' )
			? wp_parse_url( $target )
			: parse_url( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- fallback when WordPress is not loaded.
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = isset( $parts['scheme'] ) ? (string) $parts['scheme'] : '';
		$host   = isset( $parts['host'] ) ? strtolower( trim( (string) $parts['host'] ) ) : '';
		if ( 0 !== strcasecmp( $scheme, 'https' ) || '' === $host ) {
			return false;
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}

		$allowed_hosts = self::CLOUD_SIGN_DEFAULT_HOSTS;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpcy_cloud_sign_allowed_hosts', $allowed_hosts, $scope, $target );
			if ( is_array( $filtered ) ) {
				$allowed_hosts = array();
				foreach ( $filtered as $candidate ) {
					if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
						$allowed_hosts[] = strtolower( trim( $candidate ) );
					}
				}
			}
		}

		return in_array( $host, $allowed_hosts, true );
	}

	/**
	 * Encode binary or UTF-8 bytes as unpadded RFC 4648 URL-safe Base64.
	 *
	 * @param string $raw Input bytes.
	 * @return string
	 */
	private static function base64url_encode( string $raw ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe base64 encoding for signed bearer token segments.
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}
}
