<?php
/**
 * REST endpoints for signed mirror manifest status and manual refresh (W7).
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Connectivity\PublicAssets\Manifest;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes GET /wpcy/v1/manifest and POST /wpcy/v1/manifest/refresh.
 */
final class ManifestController {

	/**
	 * Signed mirror manifest client.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Constructor.
	 *
	 * @param Manifest|null $manifest Optional manifest client.
	 */
	public function __construct( ?Manifest $manifest = null ) {
		$this->manifest = null !== $manifest ? $manifest : new Manifest();
	}

	/**
	 * Return current manifest status snapshot.
	 *
	 * @return WP_REST_Response
	 */
	public function get_item(): WP_REST_Response {
		return new WP_REST_Response( $this->manifest->status_snapshot(), 200 );
	}

	/**
	 * Trigger immediate manifest fetch + Ed25519 verification and return updated snapshot.
	 *
	 * @return WP_REST_Response
	 */
	public function refresh(): WP_REST_Response {
		$sync = $this->manifest->sync( true );
		$data = array(
			'sync'     => $sync,
			'manifest' => $this->manifest->status_snapshot(),
		);
		$code = $sync['ok'] ? 200 : 502;
		return new WP_REST_Response( $data, $code );
	}
}
