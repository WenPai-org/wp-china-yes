<?php
/**
 * Scaffold smoke test: autoload wiring, not product behavior.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;

/**
 * Confirms the 4.0 Composer map is live and Core\Plugin is not shipped yet.
 */
class SmokeTest extends TestCase {

	/**
	 * Core\Plugin is absent and src/ is on the PSR-4 prefix list.
	 */
	public function test_scaffold_autoload() {
		$this->assertFalse( class_exists( 'WenPai\\ChinaYes\\Core\\Plugin' ) );

		$paths = array();
		foreach ( ClassLoader::getRegisteredLoaders() as $loader ) {
			$map = $loader->getPrefixesPsr4();
			if ( isset( $map['WenPai\\ChinaYes\\'] ) ) {
				$paths = array_merge( $paths, $map['WenPai\\ChinaYes\\'] );
			}
		}

		$src = realpath( dirname( __DIR__, 2 ) . '/src' );
		$this->assertNotFalse( $src, 'src/ directory must exist' );

		$matched = false;
		foreach ( $paths as $path ) {
			$resolved = realpath( $path );
			if ( false !== $resolved && $resolved === $src ) {
				$matched = true;
				break;
			}
		}

		$this->assertTrue( $matched, 'Composer PSR-4 paths for WenPai\\ChinaYes\\ must include src/' );
	}
}
