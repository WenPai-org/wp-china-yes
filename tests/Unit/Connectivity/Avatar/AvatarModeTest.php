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
use WenPai\ChinaYes\Connectivity\MirrorHealth;
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

		foreach ( array( 'get_avatar', 'get_avatar_url', 'avatar_defaults', 'user_profile_picture_description', 'um_user_avatar_url_filter', 'bp_gravatar_url' ) as $tag ) {
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
	 * Discussion default label for Cravatar lines.
	 */
	public function test_defaults_label_is_cravatar() {
		$module = $this->module( 'cravatar_cn' );
		$out    = $module->set_defaults_for_cravatar( array( 'gravatar_default' => 'Gravatar' ) );

		$this->assertSame( '初认头像', $out['gravatar_default'] );
	}

	/**
	 * Off keeps Gravatar on both admin and frontend.
	 */
	public function test_off_keeps_gravatar() {
		$config = new MapConfig(
			array(
				'connectivity.avatar' => 'off',
				'recovery_mode'       => false,
			)
		);
		$module = new AvatarModule( $config );
		$env    = new Environment( Environment::FRONTEND, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
		$this->assertSame( $this->gravatar, $module->get_cravatar_url( $this->gravatar ) );
	}

	/**
	 * Only the get_avatar_url rewrite increments the counter.
	 */
	public function test_only_get_avatar_url_counts_rewrite() {
		$seen = 0;
		add_action(
			'wpcy_stats_increment',
			static function ( $counter, $n ) use ( &$seen ) {
				if ( 'avatar_rewrites_frontend' === $counter || 'avatar_rewrites_admin' === $counter ) {
					$seen += (int) $n;
				}
			}
		);

		$module = $this->module( 'cravatar_cn' );
		$module->get_cravatar_url( $this->gravatar );
		$this->assertSame( 0, $seen );

		$module->filter_get_avatar_url( $this->gravatar );
		$this->assertSame( 1, $seen );

		$module->get_cravatar_url( $this->gravatar );
		$this->assertSame( 1, $seen );
	}

	/**
	 * Unhealthy cn.cravatar.com automatically fails over to avatar.wenpai.com.cn -> avatar.feibisi.com.cn.
	 */
	public function test_cravatar_cn_fails_over_to_owned_backup_domains_when_unhealthy(): void {
		$primary_down = new AvatarModule(
			$this->config( 'cravatar_cn' ),
			new MirrorHealth(
				array(
					'cn.cravatar.com'       => false,
					'avatar.wenpai.com.cn'  => true,
					'avatar.feibisi.com.cn' => true,
				)
			)
		);
		$this->assertSame(
			'https://avatar.wenpai.com.cn/avatar/00000000000000000000000000000000?s=96&d=mm&r=g',
			$primary_down->get_cravatar_url( $this->gravatar )
		);

		ob_start();
		$primary_down->add_avatar_preconnect();
		$preconnect_b1 = (string) ob_get_clean();
		$this->assertStringContainsString( 'avatar.wenpai.com.cn', $preconnect_b1 );
		$this->assertStringNotContainsString( 'cn.cravatar.com', $preconnect_b1 );

		$first_backup_down = new AvatarModule(
			$this->config( 'cravatar_cn' ),
			new MirrorHealth(
				array(
					'cn.cravatar.com'       => false,
					'avatar.wenpai.com.cn'  => false,
					'avatar.feibisi.com.cn' => true,
				)
			)
		);
		$this->assertSame(
			'https://avatar.feibisi.com.cn/avatar/00000000000000000000000000000000?s=96&d=mm&r=g',
			$first_backup_down->get_cravatar_url( $this->gravatar )
		);

		ob_start();
		$first_backup_down->add_avatar_preconnect();
		$preconnect_b2 = (string) ob_get_clean();
		$this->assertStringContainsString( 'avatar.feibisi.com.cn', $preconnect_b2 );

		// When all three hosts are marked down, keep cn.cravatar.com so client onerror can still retry and render C-logo.
		$all_down = new AvatarModule(
			$this->config( 'cravatar_cn' ),
			new MirrorHealth(
				array(
					'cn.cravatar.com'       => false,
					'avatar.wenpai.com.cn'  => false,
					'avatar.feibisi.com.cn' => false,
				)
			)
		);
		$this->assertSame(
			'https://cn.cravatar.com/avatar/00000000000000000000000000000000?s=96&d=mm&r=g',
			$all_down->get_cravatar_url( $this->gravatar )
		);
	}

	/**
	 * Filter get_avatar injects multi-tier onerror with dataset.caIdx loop guard and inline Cravatar C-logo SVG.
	 */
	public function test_get_avatar_injects_onerror_backup_chain_and_c_logo_svg(): void {
		$module = $this->module( 'cravatar_cn' );
		$html   = '<img alt="User" src="https://cn.cravatar.com/avatar/abc123?s=96&amp;d=mm" srcset="https://cn.cravatar.com/avatar/abc123?s=192 2x" class="avatar avatar-96 photo" height="96" width="96" />';
		$out    = $module->filter_get_avatar( $html );

		$this->assertIsString( $out );
		$this->assertStringContainsString( 'onerror="', $out );
		$this->assertStringContainsString( "['avatar.wenpai.com.cn','avatar.feibisi.com.cn']", $out );
		$this->assertStringContainsString( 'dataset.caIdx', $out );
		$this->assertStringContainsString( 'i.onerror=null', $out );
		$this->assertStringContainsString( "i.srcset=''", $out );
		$this->assertStringContainsString( AvatarModule::fallback_svg_data_uri(), $out );
		$this->assertStringNotContainsString( 'gravatar.admincdn.com', $out );

		// Verify inline SVG data-URI decodes to official Cravatar C-logo (#1956d4 + white C + silhouette).
		$svg_uri = AvatarModule::fallback_svg_data_uri();
		$this->assertStringStartsWith( 'data:image/svg+xml,', $svg_uri );
		$decoded_svg = rawurldecode( substr( $svg_uri, strlen( 'data:image/svg+xml,' ) ) );
		$this->assertStringContainsString( '#1956d4', $decoded_svg );
		$this->assertStringContainsString( '<circle ', $decoded_svg );
		$this->assertStringContainsString( '<path ', $decoded_svg );
		$this->assertStringNotContainsString( 'http://cravatar', $decoded_svg );
		$this->assertStringNotContainsString( 'https://', $decoded_svg );

		// When server-side failover already emitted avatar.wenpai.com.cn, onerror skips directly to avatar.feibisi.com.cn.
		$b1_img = '<img src="https://avatar.wenpai.com.cn/avatar/abc123?s=96" />';
		$b1_out = $module->filter_get_avatar( $b1_img );
		$this->assertStringContainsString( "['avatar.feibisi.com.cn']", $b1_out );
		$this->assertStringNotContainsString( "'avatar.wenpai.com.cn'", $b1_out );

		// When server-side failover already emitted avatar.feibisi.com.cn, onerror drops straight to inline C-logo SVG.
		$b2_img = '<img src="https://avatar.feibisi.com.cn/avatar/abc123?s=96">';
		$b2_out = $module->filter_get_avatar( $b2_img );
		$this->assertStringContainsString( '(this,[],', $b2_out );
		$this->assertStringContainsString( $svg_uri, $b2_out );
	}

	/**
	 * Filter get_avatar never modifies non-Cravatar images, off mode, or tags with existing onerror.
	 */
	public function test_get_avatar_skips_non_cravatar_off_mode_and_existing_onerror(): void {
		$module = $this->module( 'cravatar_cn' );

		$local_img = '<img src="https://mysite.example/wp-content/uploads/avatar.jpg" width="96" height="96" />';
		$this->assertSame( $local_img, $module->filter_get_avatar( $local_img ) );

		$gravatar_img = '<img src="https://secure.gravatar.com/avatar/abc123?s=96" />';
		$this->assertSame( $gravatar_img, $module->filter_get_avatar( $gravatar_img ) );

		$existing_onerror = '<img src="https://cn.cravatar.com/avatar/abc123?s=96" onerror="customHandler(this)" />';
		$this->assertSame( $existing_onerror, $module->filter_get_avatar( $existing_onerror ) );

		$off_module   = $this->module( 'off' );
		$cravatar_img = '<img src="https://cn.cravatar.com/avatar/abc123?s=96" />';
		$this->assertSame( $cravatar_img, $off_module->filter_get_avatar( $cravatar_img ) );
	}

	/**
	 * Active WPAvatar companion disables both enabled() and filter_get_avatar() handoff.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wpavatar_companion_handoff_disables_module_and_onerror_filter(): void {
		define( 'WPAVATAR_VERSION', '1.8.0' );
		$config = $this->config( 'cravatar_cn' );
		$module = new AvatarModule( $config );
		$env    = new Environment( Environment::FRONTEND, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
		$img = '<img src="https://cn.cravatar.com/avatar/abc123?s=96" />';
		$this->assertSame( $img, $module->filter_get_avatar( $img ) );
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
