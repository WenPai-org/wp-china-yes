<?php
/**
 * GET /wpcy/v1/profile/suggest
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\ProfileSuggest;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * D4 suggestion only. Does not write profile.
 */
final class ProfileSuggestController {

	/**
	 * Suggestion engine.
	 *
	 * @var ProfileSuggest
	 */
	private ProfileSuggest $suggest;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param ProfileSuggest|null $suggest Suggestion engine.
	 */
	public function __construct( $suggest = null ) {
		$this->suggest = $suggest instanceof ProfileSuggest ? $suggest : new ProfileSuggest();
	}

	/**
	 * Suggestion payload. Never includes IP.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		$locale = $request->get_param( 'locale' );
		if ( ! is_string( $locale ) || '' === trim( $locale ) ) {
			$header = $request->get_header( 'accept-language' );
			$locale = is_string( $header ) ? ProfileSuggest::locale_from_accept_language( $header ) : null;
		}

		$timezone = $request->get_param( 'timezone' );
		$timezone = is_string( $timezone ) ? $timezone : null;

		return RestError::ok( $this->suggest->suggest( is_string( $locale ) ? $locale : null, $timezone ) );
	}
}
