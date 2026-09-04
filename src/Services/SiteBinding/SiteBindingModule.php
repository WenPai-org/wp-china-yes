<?php
/**
 * Anonymous site-binding challenge flow. Does not auto-start without a mock URL.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Services\SiteBinding;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Core\Module;
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
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}
		$this->repository->get_identity();
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
	 * @return array{status: string, challenge_id: string, expires_at: string}|WP_Error
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

		return $this->snapshot();
	}

	/**
	 * Local revoke. Does not call the license server (path 待定 M0).
	 *
	 * @since 4.0.0
	 *
	 * @return array{status: string, site_hash: string|null, bound_at: string|null}
	 */
	public function revoke(): array {
		$identity     = $this->repository->get_identity();
		$binding      = $this->binding_from( $identity );
		$challenge_id = (string) $binding['challenge_id'];

		$binding['status']       = 'revoked';
		$binding['credential']   = null;
		$binding['challenge_id'] = null;
		$identity['binding']     = $binding;
		$this->repository->set_identity( $identity );

		if ( '' !== $challenge_id ) {
			$this->delete_transient( $challenge_id );
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
			__( 'No pending site-binding challenge.', 'wp-china-yes' ),
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
}
