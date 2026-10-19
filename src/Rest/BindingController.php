<?php
/**
 * GET/DELETE /binding, POST /binding/start, GET /binding/challenge.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Binding REST. Public challenge is read-only; writes need manage_options.
 */
final class BindingController {

	/**
	 * Challenge state machine.
	 *
	 * @var SiteBindingModule
	 */
	private SiteBindingModule $binding;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository|null        $repository Identity access.
	 * @param SiteBindingModule|null $binding    State machine. Null builds the default.
	 * @param Logger|null            $logger     Failure sink.
	 */
	public function __construct( $repository = null, $binding = null, $logger = null ) {
		if ( $binding instanceof SiteBindingModule ) {
			$this->binding = $binding;
			return;
		}

		$repo          = $repository instanceof Repository ? $repository : new Repository( $logger );
		$this->binding = new SiteBindingModule( $repo, $logger );
	}

	/**
	 * GET /binding. Never includes credential or challenge_token.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->binding->snapshot() );
	}

	/**
	 * POST /binding/start.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		unset( $request );
		return $this->respond( $this->binding->start() );
	}

	/**
	 * DELETE /binding. Local revoke only (server path 待定 M0).
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function delete_item( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->binding->revoke() );
	}

	/**
	 * GET /binding/challenge?id=  Public, pending and unexpired only.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function challenge( WP_REST_Request $request ) {
		$id = (string) $request->get_param( 'id' );
		return $this->respond( $this->binding->public_challenge( $id ) );
	}

	/**
	 * Open permission for the public challenge endpoint.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public static function public_read( WP_REST_Request $request ): bool {
		unset( $request );
		return true;
	}

	/**
	 * Wrap a module result as REST.
	 *
	 * @param array<string, mixed>|WP_Error $result Module result.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 502;
			return RestError::make(
				(string) $result->get_error_code(),
				(string) $result->get_error_message(),
				$status
			);
		}

		return RestError::ok( $result );
	}
}
