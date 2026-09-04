<?php
/**
 * WordPressOrg and DataResidency must not both rewrite api.wordpress.org.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\WordPressOrg\MirrorProbe;
use WenPai\ChinaYes\Connectivity\WordPressOrg\Origins;
use WenPai\ChinaYes\Connectivity\WordPressOrg\WordPressOrgModule;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;

/**
 * Same update-check URL is rewritten once, to the metadata mirror.
 */
class NoDoubleRewriteTest extends TestCase {

	/**
	 * DataResidency does not rewrite api.wordpress.org; WordPressOrg does, once.
	 */
	public function test_update_check_rewritten_once_to_metadata_mirror() {
		$url   = 'https://api.wordpress.org/plugins/update-check/1.1/';
		$seen  = array();
		$probe = new MirrorProbe(
			static function () {
				return array();
			},
			static function () {
				return 'up';
			},
			static function () {
				return true;
			}
		);
		$wporg = new WordPressOrgModule(
			$probe,
			static function ( $request_url, $args ) use ( &$seen ) {
				unset( $args );
				$seen[] = $request_url;
				return array(
					'response' => array(
						'code' => 200,
					),
					'body'     => '',
				);
			}
		);

		$residency = new DataResidencyModule( new Ruleset( null, null, false ), true );

		// WP runs lower priority first: DataResidency is 10, WordPressOrg is 100.
		$preempt = $residency->filter_pre_http_request( false, array(), $url );
		$out     = $wporg->filter_wordpress_org( $preempt, array(), $url );

		$this->assertSame( array( Origins::API_ORIGIN . '/plugins/update-check/1.1/' ), $seen );
		$this->assertIsArray( $out );
		$this->assertArrayNotHasKey( 'api.wordpress.org', $residency->log() );

		$rule = $residency->ruleset()->match( $url );
		$this->assertIsArray( $rule );
		$this->assertNotSame( 'api.wordpress.org', isset( $rule['host'] ) ? $rule['host'] : '' );
	}
}
