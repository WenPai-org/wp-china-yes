<?php
/**
 * Profile::matrix() equals the shared fixture that the admin app's
 * sceneDefaults() jest test also reads (M-UI-2).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Profile;

/**
 * One fixture, two mirrors: PHP matrix and ui/profiles.js.
 */
class ProfileMatrixFixtureTest extends TestCase {

	/**
	 * Every scene in the fixture matches Profile::matrix() cell for cell.
	 */
	public function test_matrix_matches_shared_fixture() {
		$path = dirname( __DIR__, 2 ) . '/fixtures/profile-matrix.json';
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertIsString( $raw );
		$fixture = json_decode( $raw, true );
		$this->assertIsArray( $fixture );

		$flat = array();
		foreach ( Profile::matrix() as $scene => $cell ) {
			$row              = $cell['connectivity'];
			$row['windfonts'] = $cell['modules']['windfonts'];
			$flat[ $scene ]   = $row;
		}

		$this->assertSame( array_keys( $fixture ), array_keys( $flat ) );
		foreach ( $fixture as $scene => $expected ) {
			$this->assertEquals( $expected, $flat[ $scene ], 'scene ' . $scene );
		}
	}
}
