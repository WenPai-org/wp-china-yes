<?php
/**
 * Fixed-source announcements: 24h cache, dismiss, no RSS parsing.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Announcements;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;
use WenPai\ChinaYes\Rest\AnnouncementsController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id admin.announcements. Empty source disables production fetch.
 */
final class AnnouncementsModule implements Module {

	/**
	 * Production aggregate URL. Not the default source.
	 *
	 * @since 4.0.0
	 */
	public const PRODUCTION_URL = 'https://wpcy.com/wp-json/wpcy/v1/announcements';

	/**
	 * Transient holding the last successful document.
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_KEY = 'wpcy_announcements';

	/**
	 * Cache TTL in seconds (24h).
	 *
	 * @since 4.0.0
	 */
	public const TTL = 86400;

	/**
	 * Visible list size.
	 *
	 * @since 4.0.0
	 */
	public const VISIBLE_LIMIT = 5;

	/**
	 * Dismissed id cap (schema maxItems).
	 *
	 * @since 4.0.0
	 */
	public const DISMISSED_CAP = 100;

	/**
	 * Cron hook for the daily pull.
	 *
	 * @since 4.0.0
	 */
	public const CRON_HOOK = 'wpcy_announcements_refresh';

	/**
	 * Allowed source field values.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const SOURCES = array( 'wptea', 'one' );

	/**
	 * Settings access.
	 *
	 * @var Repository
	 */
	private Repository $config;

	/**
	 * Local path or https://wpcy.com/… URL. Empty means do not fetch.
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
	 * Constructor. Does not register hooks or fetch.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository    $config  Settings access.
	 * @param string        $source  Fixture path or wpcy.com HTTPS URL. Empty disables fetch.
	 * @param callable|null $fetcher Optional `fn(string $source): string`.
	 */
	public function __construct( Repository $config, string $source = '', $fetcher = null ) {
		$this->config  = $config;
		$this->source  = $source;
		$this->fetcher = is_callable( $fetcher ) ? $fetcher : null;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'admin.announcements';
	}

	/**
	 * REST, admin, and cron. rest_api_init runs before REST_REQUEST.
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
	 * Hook REST and the daily refresh. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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
	 * Register GET /announcements and POST /announcements/{id}/dismiss.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		( new AnnouncementsController( $this ) )->register_routes();
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
	 * Configured source. Empty when production fetch is disabled.
	 *
	 * @since 4.0.0
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * REST payload: cached undismissed items, at most 5.
	 *
	 * No cache → generated_at null and items []. Does not error.
	 *
	 * @since 4.0.0
	 *
	 * @return array{generated_at: string|null, items: list<array<string, string>>}
	 */
	public function payload(): array {
		$cached = $this->cached_document();
		if ( ! is_array( $cached ) && '' !== $this->source ) {
			$this->refresh();
			$cached = $this->cached_document();
		}

		if ( ! is_array( $cached ) ) {
			return array(
				'generated_at' => null,
				'items'        => array(),
			);
		}

		$generated = $cached['generated_at'];
		if ( '' === $generated ) {
			$generated = null;
		}

		return array(
			'generated_at' => $generated,
			'items'        => $this->visible_items( $cached ),
		);
	}

	/**
	 * Append $id to announcements.dismissed (max 100, drop oldest). Unknown ids accepted.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Announcement id.
	 */
	public function dismiss( string $id ): void {
		$id = trim( $id );
		if ( '' === $id || strlen( $id ) > 128 ) {
			return;
		}

		$dismissed = $this->dismissed_ids();
		if ( in_array( $id, $dismissed, true ) ) {
			return;
		}

		$dismissed[] = $id;
		if ( count( $dismissed ) > self::DISMISSED_CAP ) {
			$dismissed = array_slice( $dismissed, -1 * self::DISMISSED_CAP );
		}

		$this->config->set( 'announcements.dismissed', $dismissed );
	}

	/**
	 * Fetch, sanitize, replace the cache. Failure keeps the previous cache.
	 *
	 * @since 4.0.0
	 *
	 * @return array{generated_at: string, items: list<array<string, string>>}|null
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
	 * @return array{generated_at: string, items: list<array<string, string>>}|null
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
	 * Dismissed ids from settings.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dismissed_ids(): array {
		$raw = $this->config->get( 'announcements.dismissed', array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $id ) {
			if ( is_string( $id ) && '' !== $id ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Undismissed items, newest first, at most VISIBLE_LIMIT.
	 *
	 * @param array{generated_at: string, items: list<array<string, string>>} $document Cached document.
	 * @return list<array<string, string>>
	 */
	private function visible_items( array $document ): array {
		$skip    = array_fill_keys( $this->dismissed_ids(), true );
		$visible = array();
		foreach ( $document['items'] as $item ) {
			if ( isset( $skip[ $item['id'] ] ) ) {
				continue;
			}
			$visible[] = $item;
		}

		usort(
			$visible,
			static function ( $left, $right ) {
				return strcmp( $right['published_at'], $left['published_at'] );
			}
		);

		return array_slice( $visible, 0, self::VISIBLE_LIMIT );
	}

	/**
	 * Keep a spec-shaped document or reject.
	 *
	 * @param mixed $decoded Candidate.
	 * @return array{generated_at: string, items: list<array<string, string>>}|null
	 */
	private function sanitize_document( $decoded ) {
		if ( ! is_array( $decoded ) || ! isset( $decoded['items'] ) || ! is_array( $decoded['items'] ) ) {
			return null;
		}

		$items = array();
		foreach ( $decoded['items'] as $row ) {
			$item = $this->sanitize_item( $row );
			if ( is_array( $item ) ) {
				$items[] = $item;
			}
			if ( count( $items ) >= 20 ) {
				break;
			}
		}

		$generated = isset( $decoded['generated_at'] ) && is_string( $decoded['generated_at'] )
			? $decoded['generated_at']
			: '';

		return array(
			'generated_at' => '' !== $generated ? $generated : gmdate( 'Y-m-d\\TH:i:s\\Z' ),
			'items'        => $items,
		);
	}

	/**
	 * One item: id, source, title, https url, summary ≤ 200, published_at.
	 *
	 * @param mixed $row Candidate.
	 * @return array<string, string>|null
	 */
	private function sanitize_item( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}

		$id = isset( $row['id'] ) && is_string( $row['id'] ) ? trim( $row['id'] ) : '';
		if ( '' === $id || strlen( $id ) > 128 ) {
			return null;
		}

		$source = isset( $row['source'] ) && is_string( $row['source'] ) ? $row['source'] : '';
		if ( ! in_array( $source, self::SOURCES, true ) ) {
			return null;
		}

		$title = isset( $row['title'] ) && is_string( $row['title'] ) ? trim( $this->plain_text( $row['title'] ) ) : '';
		if ( '' === $title ) {
			return null;
		}

		$url = isset( $row['url'] ) && is_string( $row['url'] ) ? $row['url'] : '';
		if ( 0 !== strpos( $url, 'https://' ) ) {
			return null;
		}

		$summary = isset( $row['summary'] ) && is_string( $row['summary'] ) ? $this->plain_text( $row['summary'] ) : '';
		$summary = $this->clip( $summary, 200 );

		$published = isset( $row['published_at'] ) && is_string( $row['published_at'] ) ? $row['published_at'] : '';
		if ( '' === $published ) {
			return null;
		}

		return array(
			'id'           => $id,
			'source'       => $source,
			'title'        => $title,
			'url'          => $url,
			'summary'      => $summary,
			'published_at' => $published,
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
	 * @param array{generated_at: string, items: list<array<string, string>>} $document Document.
	 */
	private function store( array $document ): void {
		if ( function_exists( 'set_transient' ) ) {
			$ttl = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : self::TTL;
			set_transient( self::TRANSIENT_KEY, $document, $ttl );
		}
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
	 * Strip tags from remote text.
	 *
	 * @param string $text Raw text.
	 */
	private function plain_text( string $text ): string {
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return wp_strip_all_tags( $text );
		}
		$stripped = preg_replace( '/<[^>]*>/', '', $text );
		return is_string( $stripped ) ? trim( $stripped ) : trim( $text );
	}

	/**
	 * Clip a UTF-8 string to $max characters.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 */
	private function clip( string $text, int $max ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, $max );
		}
		if ( function_exists( 'wp_html_excerpt' ) ) {
			return wp_html_excerpt( $text, $max, '' );
		}
		return substr( $text, 0, $max );
	}
}
