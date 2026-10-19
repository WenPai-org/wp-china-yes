<?php
/**
 * Scheme A + insurance: A-tier reroute only when profile=domestic.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Privacy;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';
require_once __DIR__ . '/wp-http-stubs.php';

/**
 * Profile gate. Tests must not mock geo.
 */
class ProfileGateTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		$GLOBALS['wpcy_privacy_remote_urls'] = array();
		$GLOBALS['wpcy_privacy_remote_args'] = array();
	}

	/**
	 * Domestic + ingest_ready rewrites an A-tier URL.
	 */
	public function test_domestic_ingest_ready_reroutes() {
		$url    = 'https://tracking.woocommerce.com/v1/track';
		$module = new DataResidencyModule( $this->unsigned_ruleset(), true, $this->config( 'domestic' ) );
		$out    = $module->filter_pre_http_request( false, array( 'method' => 'POST' ), $url );

		$this->assertIsArray( $out );
		$this->assertSame(
			array( 'https://updates.wenpai.net/ingest/woo-tracker' ),
			$GLOBALS['wpcy_privacy_remote_urls']
		);
	}

	/**
	 * Crossborder + ingest_ready leaves the original URL.
	 */
	public function test_crossborder_does_not_reroute() {
		$url    = 'https://tracking.woocommerce.com/v1/track';
		$module = new DataResidencyModule( $this->unsigned_ruleset(), true, $this->config( 'crossborder' ) );
		$out    = $module->filter_pre_http_request( false, array( 'method' => 'POST' ), $url );

		$this->assertFalse( $out );
		$this->assertSame( array(), $GLOBALS['wpcy_privacy_remote_urls'] );
	}

	/**
	 * Mixed + ingest_ready leaves the original URL.
	 */
	public function test_mixed_does_not_reroute() {
		$url    = 'https://tracking.woocommerce.com/v1/track';
		$module = new DataResidencyModule( $this->unsigned_ruleset(), true, $this->config( 'mixed' ) );
		$out    = $module->filter_pre_http_request( false, array( 'method' => 'POST' ), $url );

		$this->assertFalse( $out );
		$this->assertSame( array(), $GLOBALS['wpcy_privacy_remote_urls'] );
	}

	/**
	 * B-tier still records under crossborder.
	 */
	public function test_b_tier_still_records_when_crossborder() {
		$url    = 'https://rest.akismet.com/1.1/comment-check';
		$module = new DataResidencyModule( $this->unsigned_ruleset(), true, $this->config( 'crossborder' ) );
		$module->filter_pre_http_request( false, array( 'method' => 'POST' ), $url );

		$this->assertArrayHasKey( 'rest.akismet.com', $module->log() );
		$this->assertSame( array(), $GLOBALS['wpcy_privacy_remote_urls'] );
	}

	/**
	 * Missing config is treated as domestic (no geo lookup).
	 */
	public function test_missing_config_is_domestic() {
		$rule   = array(
			'action'       => 'reroute',
			'enabled_when' => 'ingest_ready',
			'target'       => 'https://updates.wenpai.net/ingest/woo-tracker',
		);
		$module = new DataResidencyModule( $this->unsigned_ruleset(), true );
		$this->assertTrue( $module->reroute_enabled( $rule ) );
	}

	/**
	 * Unsigned baseline for host-table tests.
	 */
	private function unsigned_ruleset(): Ruleset {
		return new Ruleset( null, null, false );
	}

	/**
	 * Config that only answers profile.
	 *
	 * @param string $profile Profile value.
	 */
	private function config( string $profile ): Config {
		return new class( $profile ) implements Config {
			/**
			 * Profile value.
			 *
			 * @var string
			 */
			private $profile;

			/**
			 * Constructor.
			 *
			 * @param string $profile Profile.
			 */
			public function __construct( string $profile ) {
				$this->profile = $profile;
			}

			/**
			 * Dotted get.
			 *
			 * @param string $path     Path.
			 * @param mixed  $fallback Fallback.
			 * @return mixed
			 */
			public function get( string $path, $fallback = null ) {
				return 'profile' === $path ? $this->profile : $fallback;
			}

			/**
			 * Has.
			 *
			 * @param string $path Path.
			 */
			public function has( string $path ): bool {
				return 'profile' === $path;
			}
		};
	}
}
