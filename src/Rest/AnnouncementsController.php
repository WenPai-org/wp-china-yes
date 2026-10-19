<?php
/**
 * GET /announcements and POST /announcements/{id}/dismiss.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Admin\Announcements\AnnouncementsModule;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cached announcement list. Unknown dismiss ids still return 200.
 */
final class AnnouncementsController {

	/**
	 * Announcement id path pattern.
	 *
	 * @since 4.0.0
	 */
	public const ID_PATTERN = '[A-Za-z0-9_.-]+';

	/**
	 * Announcements module.
	 *
	 * @var AnnouncementsModule
	 */
	private AnnouncementsModule $module;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param AnnouncementsModule $module Cached list and dismiss.
	 */
	public function __construct( AnnouncementsModule $module ) {
		$this->module = $module;
	}

	/**
	 * Register GET /announcements and POST /announcements/{id}/dismiss.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		$ns = RestModule::NAMESPACE;
		$id = '(?P<id>' . self::ID_PATTERN . ')';

		register_rest_route(
			$ns,
			'/announcements',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);

		register_rest_route(
			$ns,
			'/announcements/' . $id . '/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'dismiss' ),
				'permission_callback' => array( Permissions::class, 'manage_options_write' ),
			)
		);
	}

	/**
	 * Cached undismissed items, at most 5. No cache → generated_at null, items [].
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->module->payload() );
	}

	/**
	 * Append id to announcements.dismissed. Unknown ids still 200.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function dismiss( WP_REST_Request $request ): WP_REST_Response {
		$id = $request->get_param( 'id' );
		$this->module->dismiss( is_string( $id ) ? $id : '' );
		return RestError::ok( $this->module->payload() );
	}
}
