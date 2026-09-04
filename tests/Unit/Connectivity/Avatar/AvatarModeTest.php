<?php
/**
 * Avatar modes: cravatar_cn, cravatar_global, off.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\Avatar;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\Avatar\AvatarModule;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Connectivity\HookStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';

/**
 * Unit: three documented modes; off does not register hooks.
 */
class AvatarModeTest extends TestCase {

	/**
	 * Sample gravatar URL using a source from 3.x $sources.
	 *
	 * @var string
	 */
	private $gravatar = 'https://secure.gravatar.com/avatar/00000000000000000000000000000000?s=96&d=mm&r=g';

	/**
	 * Reset hook bag.
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
	}

	/**
	 * Cravatar CN rewrites to cn.cravatar.com.
	 */
	public function test_cravatar_cn_rewrites_to_cn_host() {
		$module = $this->module( 'cravatar_cn' );
		$out    = $module->get_cravatar_url( $this->gravatar );

		$this->assertSame(
			'https://cn.cravatar.com/avatar/00000000000000000000000000000000?s=96&d=mm&r=g',
			$out
		);
	}

	/**
	 * Cravatar global rewrites to en.cravatar.com.
	 */
	public function test_cravatar_global_rewrites_to_en_host() {
		$module = $this->module( 'cravatar_global' );
		$out    = $module->get_cravatar_url( $this->gravatar );

		$this->assertSame(
			'https://en.cravatar.com/avatar/00000000000000000000000000000000?s=96&d=mm&r=g',
			$out
		);
	}

	/**
	 * Off does not rewrite even if get_cravatar_url is called directly.
	 */
	public function test_off_does_not_rewrite_url() {
		$module = $this->module( 'off' );

		$this->assertSame( $this->gravatar, $module->get_cravatar_url( $this->gravatar ) );
	}

	/**
	 * Off is not enabled, so the registry will not call register().
	 */
	public function test_off_does_not_hook() {
		$config = $this->config( 'off' );
		$module = new AvatarModule( $config );
		$env    = new Environment( Environment::FRONTEND, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
		$this->assertSame( array(), HookStore::$hooks );
	}

	/**
	 * Cravatar CN registers the 3.x filter set.
	 */
	public function test_cravatar_cn_registers_hooks() {
		$module = $this->module( 'cravatar_cn' );
		$config = $this->config( 'cravatar_cn' );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertTrue( $module->enabled( $config, $env ) );
		$module->register();

		foreach ( array( 'get_avatar_url', 'avatar_defaults', 'user_profile_picture_description', 'um_user_avatar_url_filter', 'bp_gravatar_url' ) as $tag ) {
			$this->assertArrayHasKey( $tag, HookStore::$hooks, $tag );
		}
		$this->assertArrayHasKey( 'wp_head', HookStore::$hooks );
	}

	/**
	 * All 3.x $sources hosts are rewritten.
	 *
	 * @dataProvider source_host_provider
	 *
	 * @param string $host Source host.
	 */
	public function test_replace_covers_legacy_sources( string $host ) {
		$module = $this->module( 'cravatar_cn' );
		$url    = 'https://' . $host . '/avatar/abc';

		$this->assertSame( 'https://cn.cravatar.com/avatar/abc', $module->get_cravatar_url( $url ) );
	}

	/**
	 * Hosts from Service\Avatar::replace_avatar_url $sources.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function source_host_provider(): array {
		return array(
			'www'     => array( 'www.gravatar.com' ),
			'0'       => array( '0.gravatar.com' ),
			'1'       => array( '1.gravatar.com' ),
			'2'       => array( '2.gravatar.com' ),
			's'       => array( 's.gravatar.com' ),
			'secure'  => array( 'secure.gravatar.com' ),
			'cn'      => array( 'cn.gravatar.com' ),
			'en'      => array( 'en.gravatar.com' ),
			'bare'    => array( 'gravatar.com' ),
			'geekzu'  => array( 'sdn.geekzu.org' ),
			'duoshuo' => array( 'gravatar.duoshuo.com' ),
			'loli'    => array( 'gravatar.loli.net' ),
			'qiniu'   => array( 'dn-qiniu-avatar.qbox.me' ),
		);
	}

	/**
	 * Recovery mode disables the module.
	 */
	public function test_enabled_false_in_recovery_mode() {
		$config = new MapConfig(
			array(
				'connectivity.avatar' => 'cravatar_cn',
				'recovery_mode'       => true,
			)
		);
		$module = new AvatarModule( $config );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * AllowsUrlRewrite false disables the module.
	 */
	public function test_enabled_false_when_rewrite_disallowed() {
		$config = $this->config( 'cravatar_cn' );
		$module = new AvatarModule( $config );
		$env    = new Environment( Environment::ADMIN, false );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Weavatar is in schema but not implemented here: do not hook.
	 */
	public function test_weavatar_does_not_hook() {
		$config = $this->config( 'weavatar' );
		$module = new AvatarModule( $config );
		$env    = new Environment( Environment::FRONTEND, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
		$this->assertSame( $this->gravatar, $module->get_cravatar_url( $this->gravatar ) );
	}

	/**
	 * Discussion default label for Cravatar lines.
	 */
	public function test_defaults_label_is_cravatar() {
		$module = $this->module( 'cravatar_cn' );
		$out    = $module->set_defaults_for_cravatar( array( 'gravatar_default' => 'Gravatar' ) );

		$this->assertSame( '初认头像', $out['gravatar_default'] );
	}

	/**
	 * Module under the given avatar mode.
	 *
	 * @param string $mode connectivity.avatar value.
	 */
	private function module( string $mode ): AvatarModule {
		return new AvatarModule( $this->config( $mode ) );
	}

	/**
	 * Config under the given avatar mode.
	 *
	 * @param string $mode connectivity.avatar value.
	 */
	private function config( string $mode ): MapConfig {
		return new MapConfig(
			array(
				'connectivity.avatar' => $mode,
				'recovery_mode'       => false,
			)
		);
	}
}
