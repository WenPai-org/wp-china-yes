<?php
/**
 * Data residency: record has no body; ignore is silent; reroute idle when ingest is false.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Privacy;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';
require_once __DIR__ . '/wp-http-stubs.php';

/**
 * Acceptance 3 of M1-09.
 */
class RecordIgnoreTest extends TestCase {

	/**
	 * Reset option bags and remote URL log.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		$GLOBALS['wpcy_privacy_remote_urls'] = array();
	}

	/**
	 * Built-in baseline verifies with the test public key.
	 */
	public function test_baseline_verifies() {
		$ruleset = new Ruleset();
		$this->assertTrue( $ruleset->verified() );
		$this->assertSame( 1, $ruleset->version() );
	}

	/**
	 * WooCommerce Tracker URL is not rewritten while ingest_ready is false.
	 */
	public function test_woo_tracker_url_unchanged_when_ingest_not_ready() {
		$url    = 'https://tracking.woocommerce.com/v1/track?user=secret';
		$module = new DataResidencyModule( new Ruleset(), false );
		$out    = $module->filter_pre_http_request( false, array( 'method' => 'POST' ), $url );

		$this->assertFalse( $out );
		$this->assertSame( array(), $GLOBALS['wpcy_privacy_remote_urls'] );
		$this->assertArrayNotHasKey( 'tracking.woocommerce.com', $module->log() );

		$rule = $module->ruleset()->match( $url );
		$this->assertIsArray( $rule );
		$this->assertSame( 'reroute', $rule['action'] );
		$this->assertFalse( $module->reroute_enabled( $rule ) );
	}

	/**
	 * B-tier log stores host / data_class / count / last_seen only — no query string, no body.
	 */
	public function test_record_log_has_no_query_string_or_body() {
		$url    = 'https://rest.akismet.com/1.1/comment-check?api_key=secret&comment=hello';
		$module = new DataResidencyModule( new Ruleset(), false );
		$module->filter_pre_http_request(
			false,
			array(
				'method' => 'POST',
				'body'   => 'comment_content=this+is+the+comment+body',
			),
			$url
		);

		$log = $module->log();
		$this->assertArrayHasKey( 'rest.akismet.com', $log );
		$row = $log['rest.akismet.com'];
		$this->assertSame( array( 'host', 'data_class', 'count', 'last_seen' ), array_keys( $row ) );
		$this->assertSame( 'rest.akismet.com', $row['host'] );
		$this->assertSame( 'comments', $row['data_class'] );
		$this->assertSame( 1, $row['count'] );
		$this->assertNotSame( '', $row['last_seen'] );

		$encoded = $this->encode( $log );
		$this->assertStringNotContainsString( 'api_key', $encoded );
		$this->assertStringNotContainsString( 'comment=', $encoded );
		$this->assertStringNotContainsString( 'comment_content', $encoded );
		$this->assertStringNotContainsString( '?', $encoded );
		$this->assertStringNotContainsString( 'secret', $encoded );
		$this->assertStringNotContainsString( 'hello', $encoded );

		$module->filter_pre_http_request( false, array(), 'https://foo.rest.akismet.com/1.1/comment-check' );
		$this->assertSame( 1, $module->log()['foo.rest.akismet.com']['count'] );
	}

	/**
	 * C-tier payment hosts are ignored and never appear in the B-tier log.
	 */
	public function test_ignore_payment_hosts_are_absent_from_log() {
		$module = new DataResidencyModule( new Ruleset(), false );
		$hosts  = array(
			'https://api.stripe.com/v1/charges?key=sk_test',
			'https://www.paypal.com/cgi-bin/webscr?cmd=_notify',
			'https://openapi.alipay.com/gateway.do?biz=1',
			'https://api.captcha.example/verify?token=abc',
		);
		foreach ( $hosts as $url ) {
			$out = $module->filter_pre_http_request( false, array(), $url );
			$this->assertFalse( $out );
		}

		$log = $module->log();
		$this->assertArrayNotHasKey( 'api.stripe.com', $log );
		$this->assertArrayNotHasKey( 'www.paypal.com', $log );
		$this->assertArrayNotHasKey( 'openapi.alipay.com', $log );
		$this->assertArrayNotHasKey( 'api.captcha.example', $log );
		$this->assertSame( array(), $log );
	}

	/**
	 * A-tier miss does not fall back to record.
	 */
	public function test_reroute_miss_does_not_fall_back_to_record() {
		$module = new DataResidencyModule( new Ruleset(), false );
		$module->filter_pre_http_request( false, array(), 'https://pixel.wp.com/t.gif?id=1' );
		$module->filter_pre_http_request( false, array(), 'https://stats.wp.com/t.gif?id=1' );
		$module->filter_pre_http_request( false, array(), 'https://api.wordpress.org/core/version-check/1.7/' );
		$this->assertSame( array(), $module->log() );
	}

	/**
	 * WordPress.org API paths outside the three update-check prefixes are not A-tier.
	 */
	public function test_api_wordpress_org_non_update_path_is_not_a_tier() {
		$ruleset = new Ruleset();
		$rule    = $ruleset->match( 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information' );
		$this->assertIsArray( $rule );
		$this->assertSame( 'ignore', $rule['action'] );
		$this->assertSame( '*', $rule['host'] );
	}

	/**
	 * Reroute branch exists: when the probe is true the original host is not contacted.
	 */
	public function test_reroute_branch_rewrites_when_ingest_ready() {
		$url    = 'https://tracking.woocommerce.com/v1/track';
		$module = new DataResidencyModule( new Ruleset(), true );
		$out    = $module->filter_pre_http_request( false, array( 'method' => 'POST' ), $url );

		$this->assertIsArray( $out );
		$this->assertSame(
			array( 'https://updates.wenpai.net/ingest/woo-tracker' ),
			$GLOBALS['wpcy_privacy_remote_urls']
		);
		$this->assertArrayNotHasKey( 'tracking.woocommerce.com', $module->log() );
	}

	/**
	 * Empty Tracks target stays idle even if ingest is ready.
	 */
	public function test_empty_target_does_not_rewrite() {
		$module = new DataResidencyModule( new Ruleset(), true );
		$out    = $module->filter_pre_http_request( false, array(), 'https://pixel.wp.com/t.gif' );
		$this->assertFalse( $out );
		$this->assertSame( array(), $GLOBALS['wpcy_privacy_remote_urls'] );
	}

	/**
	 * Encode a log for substring assertions.
	 *
	 * @param mixed $value Value.
	 */
	private function encode( $value ): string {
		$encoded = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no WordPress.
		return is_string( $encoded ) ? $encoded : '';
	}
}
