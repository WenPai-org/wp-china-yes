<?php
/**
 * Apps bridge contract: every postMessage type, origin, permission, timeout.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Apps\Bridge;
use WenPai\ChinaYes\Apps\CachedEntitlements;
use WenPai\ChinaYes\Apps\EntitlementsClient;
use WenPai\ChinaYes\Apps\ManifestVerifier;
use WenPai\ChinaYes\Apps\Registry;

require_once __DIR__ . '/wp-apps-stubs.php';

/**
 * Spec §3 / §3.1 / §3.1a. PHP classify() is the host decision table.
 */
class BridgeContractTest extends TestCase {

	/**
	 * Spec §3.2 lists every type exactly once.
	 *
	 * @return list<string>
	 */
	private function spec_types(): array {
		return array(
			'ready',
			'context.get',
			'data.get',
			'data.set',
			'data.delete',
			'data.list',
			'entitlement.get',
			'go.open',
			'resize',
			'init',
			'result',
			'error',
		);
	}

	/**
	 * Message table coverage.
	 */
	public function test_message_table_lists_every_type() {
		$types = Bridge::message_types();
		sort( $types );
		$expected = $this->spec_types();
		sort( $expected );
		$this->assertSame( $expected, $types );
	}

	/**
	 * Ready and resize do not require a permission.
	 */
	public function test_ready_and_resize_need_no_permission() {
		$this->assertSame( '', Bridge::permission_for( 'ready' ) );
		$this->assertSame( '', Bridge::permission_for( 'resize' ) );
		$this->assertSame( '', Bridge::permission_for( 'init' ) );
		$this->assertSame( '', Bridge::permission_for( 'result' ) );
		$this->assertSame( '', Bridge::permission_for( 'error' ) );
	}

	/**
	 * Permission map for tool → host types.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function permission_map(): array {
		return array(
			'context'     => array( 'context.get', 'site:read' ),
			'data_get'    => array( 'data.get', 'data:read' ),
			'data_set'    => array( 'data.set', 'data:write' ),
			'data_delete' => array( 'data.delete', 'data:delete' ),
			'data_list'   => array( 'data.list', 'data:read' ),
			'quota'       => array( 'entitlement.get', 'entitlement:read' ),
			'go'          => array( 'go.open', 'go:open' ),
		);
	}

	/**
	 * Each REST-backed type maps to its permission.
	 *
	 * @dataProvider permission_map
	 *
	 * @param string $type       Message type.
	 * @param string $permission Manifest permission.
	 */
	public function test_type_permission( string $type, string $permission ) {
		$this->assertSame( $permission, Bridge::permission_for( $type ) );
	}

	/**
	 * Ready with matching origin and iframe source yields init.
	 */
	public function test_ready_sends_init() {
		$out = Bridge::classify( $this->event( 'ready' ) );
		$this->assertSame( 'init', $out['action'] );
	}

	/**
	 * Context.get forwards GET /apps/{id}/context.
	 */
	public function test_context_get_forwards_rest() {
		$out = Bridge::classify( $this->event( 'context.get', array(), 'req-1', true ) );
		$this->assertSame( 'rest', $out['action'] );
		$this->assertSame( 'GET', $out['rest']['method'] );
		$this->assertSame( '/wpcy/v1/apps/mock-app/context', $out['rest']['path'] );
		$this->assertFalse( $out['retry'] );
		$this->assertSame( Bridge::HOST_TIMEOUT_MS, $out['timeout_ms'] );
	}

	/**
	 * Data.get / data.list / data.set / data.delete REST paths.
	 */
	public function test_data_types_forward_rest() {
		$get = Bridge::classify( $this->event( 'data.get', array( 'key' => 'smoke' ), 'r', true ) );
		$this->assertSame( '/wpcy/v1/apps/mock-app/data/smoke', $get['rest']['path'] );
		$this->assertSame( 'GET', $get['rest']['method'] );

		$list = Bridge::classify( $this->event( 'data.list', array(), 'r', true ) );
		$this->assertSame( '/wpcy/v1/apps/mock-app/data', $list['rest']['path'] );

		$set = Bridge::classify(
			$this->event(
				'data.set',
				array(
					'key'   => 'smoke',
					'value' => array( 'ok' => true ),
				),
				'r',
				true
			)
		);
		$this->assertSame( 'PUT', $set['rest']['method'] );
		$this->assertTrue( $set['write'] );
		$this->assertFalse( $set['retry'] );

		$del = Bridge::classify( $this->event( 'data.delete', array( 'key' => 'smoke' ), 'r', true ) );
		$this->assertSame( 'DELETE', $del['rest']['method'] );
		$this->assertTrue( $del['write'] );
		$this->assertFalse( $del['retry'] );
	}

	/**
	 * Entitlement.get and go.open.
	 */
	public function test_quota_and_go_forward_rest() {
		$quota = Bridge::classify( $this->event( 'entitlement.get', array(), 'r', true ) );
		$this->assertSame( 'rest', $quota['action'] );
		$this->assertSame( '/wpcy/v1/apps/mock-app/entitlement', $quota['rest']['path'] );

		$go = Bridge::classify( $this->event( 'go.open', array(), 'r', true ) );
		$this->assertSame( 'POST', $go['rest']['method'] );
		$this->assertTrue( $go['write'] );
		$this->assertFalse( $go['retry'] );
	}

	/**
	 * Resize clamps above 4000.
	 */
	public function test_resize_clamps_at_4000() {
		$ok = Bridge::classify( $this->event( 'resize', array( 'height' => 320 ), '', true ) );
		$this->assertSame( 'resize', $ok['action'] );
		$this->assertSame( 320, $ok['height'] );

		$hi = Bridge::classify( $this->event( 'resize', array( 'height' => 9000 ), '', true ) );
		$this->assertSame( 4000, $hi['height'] );
		$this->assertSame( 4000, Bridge::clamp_height( 4001 ) );
	}

	/**
	 * Host types are not inbound REST.
	 */
	public function test_host_types_are_not_inbound_rest() {
		foreach ( array( 'init', 'result', 'error' ) as $type ) {
			$out = Bridge::classify( $this->event( $type, array(), 'r', true ) );
			$this->assertSame( 'discard', $out['action'], $type );
		}
	}

	/**
	 * Protocol version other than 1 is discarded.
	 */
	public function test_wrong_protocol_is_discarded() {
		$out = Bridge::classify(
			array(
				'data'             => array(
					'wpcy' => 2,
					'type' => 'ready',
				),
				'origin'           => 'https://apps.wpcy.com',
				'entry_origin'     => 'https://apps.wpcy.com',
				'source_is_iframe' => true,
				'ready'            => false,
				'permissions'      => array(),
				'app_id'           => 'mock-app',
			)
		);
		$this->assertSame( 'discard', $out['action'] );
		$this->assertFalse( Bridge::envelope_valid( array( 'type' => 'ready' ) ) );
	}

	/**
	 * Origin mismatch with request_id returns wpcy_apps_origin_mismatch.
	 */
	public function test_origin_mismatch_with_request_id_errors() {
		$event           = $this->event( 'data.get', array( 'key' => 'smoke' ), 'req-x', true );
		$event['origin'] = 'https://evil.example';
		$out             = Bridge::classify( $event );
		$this->assertSame( 'error', $out['action'] );
		$this->assertSame( Bridge::ERR_ORIGIN_MISMATCH, $out['code'] );
		$this->assertSame( 'req-x', $out['request_id'] );
	}

	/**
	 * Origin mismatch without request_id is silent.
	 */
	public function test_origin_mismatch_without_request_id_is_silent() {
		$event           = $this->event( 'ready' );
		$event['origin'] = 'https://evil.example';
		$out             = Bridge::classify( $event );
		$this->assertSame( 'discard', $out['action'] );
	}

	/**
	 * Messages before ready are discarded.
	 */
	public function test_messages_before_ready_are_discarded() {
		$out = Bridge::classify( $this->event( 'data.get', array( 'key' => 'smoke' ), 'r', false ) );
		$this->assertSame( 'discard', $out['action'] );
	}

	/**
	 * Event.source must be the iframe contentWindow.
	 */
	public function test_non_iframe_source_is_discarded() {
		$event                     = $this->event( 'ready' );
		$event['source_is_iframe'] = false;
		$out                       = Bridge::classify( $event );
		$this->assertSame( 'discard', $out['action'] );
	}

	/**
	 * Messages from window.parent are not tool messages.
	 */
	public function test_parent_source_is_discarded() {
		$event                     = $this->event( 'ready' );
		$event['source_is_parent'] = true;
		$out                       = Bridge::classify( $event );
		$this->assertSame( 'discard', $out['action'] );
	}

	/**
	 * Undeclared data.set is wpcy_apps_forbidden_permission.
	 */
	public function test_undeclared_data_set_is_forbidden() {
		$event                = $this->event(
			'data.set',
			array(
				'key'   => 'smoke',
				'value' => 1,
			),
			'r',
			true
		);
		$event['permissions'] = array( 'data:read' );
		$out                  = Bridge::classify( $event );
		$this->assertSame( 'error', $out['action'] );
		$this->assertSame( Bridge::ERR_FORBIDDEN, $out['code'] );
	}

	/**
	 * Write types never auto-retry.
	 */
	public function test_write_types_do_not_retry() {
		$this->assertTrue( Bridge::is_write_type( 'data.set' ) );
		$this->assertTrue( Bridge::is_write_type( 'data.delete' ) );
		$this->assertTrue( Bridge::is_write_type( 'go.open' ) );
		$this->assertFalse( Bridge::is_write_type( 'data.get' ) );
	}

	/**
	 * REST timeout constant is 10s. Resize debounce is 200ms.
	 */
	public function test_timeout_and_debounce_constants() {
		$this->assertSame( 10000, Bridge::HOST_TIMEOUT_MS );
		$this->assertSame( 200, Bridge::RESIZE_DEBOUNCE_MS );
		$this->assertSame( 'wpcy_apps_host_timeout', Bridge::ERR_HOST_TIMEOUT );
	}

	/**
	 * Iframe sandbox has no allow-same-origin.
	 */
	public function test_iframe_sandbox_has_no_same_origin() {
		$this->assertSame( 'allow-scripts allow-forms', Bridge::IFRAME_SANDBOX );
		$this->assertSame( 'allow-scripts allow-forms', Registry::iframe_sandbox() );
		$this->assertFalse( strpos( Bridge::IFRAME_SANDBOX, 'allow-same-origin' ) );
		$this->assertSame( 'strict-origin', Bridge::IFRAME_REFERRERPOLICY );
	}

	/**
	 * Origin from entry_url.
	 */
	public function test_origin_from_entry_url() {
		$this->assertSame(
			'https://apps.wpcy.com',
			Bridge::origin_from_entry_url( 'https://apps.wpcy.com/mock-app/' )
		);
	}

	/**
	 * Signed mock-app manifest verifies.
	 */
	public function test_mock_app_manifest_verifies() {
		$path = dirname( __DIR__, 2 ) . '/fixtures/mock-app/manifest.json';
		$this->assertFileExists( $path );
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$doc = json_decode( (string) $raw, true );
		$this->assertIsArray( $doc );
		$this->assertTrue( ( new ManifestVerifier() )->verify( $doc ) );
		$this->assertSame( 'mock-app', $doc['id'] );
	}

	/**
	 * Mock tool page runs the smoke sequence in order.
	 */
	public function test_mock_app_page_runs_sequence() {
		$path = dirname( __DIR__, 2 ) . '/fixtures/mock-app/index.html';
		$html = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertNotFalse( $html );
		$this->assertNotFalse( strpos( $html, 'id="log"' ) );
		foreach ( array( 'ready', 'context.get', 'data.set', 'data.get', 'data.delete', 'entitlement.get', 'go.open', 'resize' ) as $type ) {
			$this->assertNotFalse( strpos( $html, $type ), $type );
		}
		$this->assertNotFalse( strpos( $html, 'smoke' ) );
		$this->assertNotFalse( strpos( $html, 'height: 320' ) );
	}

	/**
	 * Host JS never copies a REST nonce into an envelope.
	 */
	public function test_host_js_does_not_put_nonce_in_envelope() {
		$js = file_get_contents( dirname( __DIR__, 3 ) . '/src/Admin/app/apps/Bridge.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source.
		$this->assertNotFalse( $js );
		$this->assertSame( 0, preg_match( '/\\bnonce\\b/', $js ) );
		$this->assertSame( 0, preg_match( '/window\.top/', $js ) );
		$this->assertSame( 0, preg_match( '/window\.parent\.postMessage/', $js ) );
		$this->assertNotFalse( strpos( $js, 'allow-scripts allow-forms' ) );
		$this->assertSame( 0, preg_match( '/allow-same-origin/', $js ) );
		$this->assertNotFalse( strpos( $js, 'source === iframe.contentWindow' ) );
		$this->assertNotFalse( strpos( $js, '200' ) );
		$this->assertNotFalse( strpos( $js, '10000' ) );
	}

	/**
	 * CachedEntitlements implements EntitlementsClient.
	 */
	public function test_cached_entitlements_is_client() {
		$this->assertTrue( is_subclass_of( CachedEntitlements::class, EntitlementsClient::class ) );
		$source = file_get_contents( dirname( __DIR__, 3 ) . '/src/Apps/CachedEntitlements.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source.
		$this->assertNotFalse( $source );
		$this->assertNotFalse( strpos( $source, 'EntitlementsModule' ) );
		$this->assertNotFalse( strpos( $source, 'function get' ) );
	}

	/**
	 * Envelope helper.
	 *
	 * @param string               $type        Type.
	 * @param array<string, mixed> $payload     Payload.
	 * @param string               $request_id  Request id.
	 * @param bool                 $ready       Host has seen ready.
	 * @return array<string, mixed>
	 */
	private function event( string $type, array $payload = array(), string $request_id = '', bool $ready = false ): array {
		$data = array(
			'wpcy'    => 1,
			'type'    => $type,
			'payload' => $payload,
		);
		if ( '' !== $request_id ) {
			$data['request_id'] = $request_id;
		}
		return array(
			'data'             => $data,
			'origin'           => 'https://apps.wpcy.com',
			'entry_origin'     => 'https://apps.wpcy.com',
			'source_is_iframe' => true,
			'source_is_parent' => false,
			'ready'            => $ready,
			'permissions'      => array(
				'site:read',
				'data:read',
				'data:write',
				'data:delete',
				'entitlement:read',
				'go:open',
			),
			'app_id'           => 'mock-app',
		);
	}
}
