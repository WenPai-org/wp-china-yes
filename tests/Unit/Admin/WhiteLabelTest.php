<?php
/**
 * Suite-wide WhiteLabel entitlement gating, 72h stale grace, and display hooks.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\WhiteLabel;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Verifies paid OEM/White-Label gating and cross-plugin `wenpai_plugin_display` contract.
 */
class WhiteLabelTest extends TestCase {

	/**
	 * Reset stores before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		OptionStore::reset();
	}

	/**
	 * Unentitled sites never activate white-label overrides even when DB option `enabled` is true.
	 */
	public function test_unentitled_site_blocks_whitelabel_even_when_enabled_in_db(): void {
		$repo                            = new Repository();
		$settings                        = Defaults::settings();
		$settings['admin']['whitelabel'] = array(
			'enabled'       => true,
			'brand_name'    => '星云建站',
			'help_url'      => 'https://nebula.example.com/help',
			'feedback_url'  => 'https://nebula.example.com/support',
			'docs_url'      => 'https://nebula.example.com/docs',
			'news_feed_url' => 'https://nebula.example.com/news.json',
			'logo_url'      => '',
			'hide_logo'     => false,
			'hide_links'    => true,
		);
		$repo->save_option( Schema::SETTINGS, $settings );

		$resolved = WhiteLabel::resolve( $repo->all() );
		$this->assertFalse( $resolved['entitled'] );
		$this->assertFalse( $resolved['enabled'] );
		$this->assertSame( '文派叶子', WhiteLabel::brand_name( $repo->all() ) );

		$defaults = array(
			'brand'        => '文派素格',
			'plugin_name'  => 'WPSlug',
			'help_url'     => 'https://wpcy.com/slug',
			'feedback_url' => 'https://wpcy.com/support',
			'docs_url'     => 'https://wpcy.com/document',
		);
		$this->assertSame( $defaults, WhiteLabel::filter_display( $defaults, $repo->all() ) );
	}

	/**
	 * Fresh 1h and stale 72h entitlement caches unlock white-label and override suite display & author only.
	 */
	public function test_fresh_and_stale_72h_entitlements_unlock_suite_display_filters(): void {
		$repo                            = new Repository();
		$settings                        = Defaults::settings();
		$settings['admin']['whitelabel'] = array(
			'enabled'       => true,
			'brand_name'    => '星云数字',
			'help_url'      => 'https://nebula.example.com/help',
			'feedback_url'  => '',
			'docs_url'      => 'https://nebula.example.com/docs',
			'news_feed_url' => 'https://nebula.example.com/news.json',
			'logo_url'      => 'https://nebula.example.com/logo.svg',
			'hide_logo'     => false,
			'hide_links'    => true,
		);
		$repo->save_option( Schema::SETTINGS, $settings );

		// 1. Fresh cache active.
		set_transient(
			EntitlementsModule::TRANSIENT_FRESH,
			array(
				array(
					'service' => 'oem',
					'status'  => 'active',
				),
			),
			3600
		);
		$this->assertTrue( WhiteLabel::is_entitled() );

		// 2. Fresh cache expired -> 72h stale grace cache keeps client white-label active.
		delete_transient( EntitlementsModule::TRANSIENT_FRESH );
		set_transient(
			EntitlementsModule::TRANSIENT_STALE,
			array(
				array(
					'service' => 'whitelabel',
					'status'  => 'granted',
				),
			),
			259200
		);
		$this->assertTrue( WhiteLabel::is_entitled() );
		$this->assertSame( '星云数字', WhiteLabel::brand_name( $repo->all() ) );

		WhiteLabel::register( $repo );
		$this->assertArrayHasKey( 'wenpai_plugin_display', AdminStore::$hooks );
		$this->assertArrayHasKey( 'wpslug_brand_name', AdminStore::$hooks );
		$this->assertArrayHasKey( 'all_plugins', AdminStore::$hooks );

		$display_cb = AdminStore::$hooks['wenpai_plugin_display'][0];
		$filtered   = $display_cb(
			array(
				'brand'        => '文派素格',
				'plugin_name'  => 'WPSlug',
				'help_url'     => 'https://wpcy.com/slug',
				'feedback_url' => 'https://wpcy.com/support',
				'docs_url'     => 'https://wpcy.com/document',
			),
			'wpslug'
		);

		$this->assertSame( '星云数字', $filtered['brand'] );
		$this->assertSame( 'WPSlug', $filtered['plugin_name'] );
		$this->assertSame( 'https://nebula.example.com/help', $filtered['help_url'] );
		$this->assertSame( 'https://wpcy.com/support', $filtered['feedback_url'] );
		$this->assertSame( 'https://nebula.example.com/docs', $filtered['docs_url'] );
		$this->assertSame( 'https://nebula.example.com/news.json', $filtered['news_feed_url'] );

		$wpslug_cb = AdminStore::$hooks['wpslug_brand_name'][0];
		$this->assertSame( '星云数字', $wpslug_cb( '文派素格' ) );

		$plugins_cb = AdminStore::$hooks['all_plugins'][0];
		$plugins    = $plugins_cb(
			array(
				'wpsss/wpsss.php'             => array(
					'Name'       => 'WPSSS',
					'Author'     => 'WenPai.org',
					'AuthorName' => 'WenPai.org',
					'AuthorURI'  => 'https://wpcy.com',
					'PluginURI'  => 'https://wpcy.com/wpsss',
					'UpdateURI'  => 'https://updates.wenpai.net/plugins/wpsss',
				),
				'other-vendor/other-tool.php' => array(
					'Name'       => 'Other Tool',
					'Author'     => 'Third Party',
					'AuthorName' => 'Third Party',
					'AuthorURI'  => 'https://third.example.com',
					'PluginURI'  => 'https://third.example.com/tool',
				),
			)
		);

		$this->assertSame( 'WPSSS', $plugins['wpsss/wpsss.php']['Name'] );
		$this->assertSame( 'https://updates.wenpai.net/plugins/wpsss', $plugins['wpsss/wpsss.php']['UpdateURI'] );
		$this->assertSame( '星云数字', $plugins['wpsss/wpsss.php']['Author'] );
		$this->assertSame( 'https://nebula.example.com/docs', $plugins['wpsss/wpsss.php']['PluginURI'] );
		$this->assertSame( 'Third Party', $plugins['other-vendor/other-tool.php']['Author'] );
	}
}
