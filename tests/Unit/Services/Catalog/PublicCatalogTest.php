<?php
/**
 * Unit tests for PublicCatalog static CDN catalog consumer.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Services\Catalog;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Services\Catalog\PublicCatalog;
use WenPai\ChinaYes\Tests\Unit\Services\Entitlements\EntitlementsStore;
use WP_Error;

require_once __DIR__ . '/../Entitlements/wp-entitlements-stubs.php';

/**
 * Verifies remote fetch sanitization, cache hit, error backoff, and stale fallback.
 */
class PublicCatalogTest extends TestCase {

	/**
	 * Reset transient and HTTP mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		EntitlementsStore::reset();
	}

	/**
	 * Valid remote JSON is sanitized (strips HTML, rejects non-HTTPS links) and cached.
	 */
	public function test_sync_fetches_sanitizes_and_caches_public_catalog(): void {
		$calls = 0;
		$http  = static function () use ( &$calls ) {
			++$calls;
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => (string) wp_json_encode(
					array(
						'updated_at' => '2026-11-22T07:25:00Z',
						'quotas'     => array(
							array(
								'id'           => 'admincdn',
								'name'         => '<b>adminCDN</b>',
								'usage'        => '免费额度',
								'percent'      => 150,
								'action_label' => '说明',
								'action_url'   => 'https://wpcy.com/admincdn',
							),
						),
						'promos'     => array(
							array(
								'badge'     => '商业授权',
								'title'     => '全套白标',
								'desc'      => '支持代理商交付',
								'pills'     => array( '自有品牌' ),
								'cta_label' => '了解',
								'cta_url'   => 'javascript:alert(1)',
							),
						),
						'toolbox'    => array(
							array(
								'label' => '文档站',
								'icon'  => 'help',
								'href'  => 'https://wpcy.com/docs',
							),
							array(
								'label' => '不安全链接',
								'icon'  => 'help',
								'href'  => 'http://insecure.example/',
							),
						),
					)
				),
			);
		};

		$catalog = new PublicCatalog(
			$http,
			array( EntitlementsStore::class, 'get_transient' ),
			array( EntitlementsStore::class, 'set_transient' )
		);

		$first = $catalog->sync( false );
		$this->assertSame( 'remote', $first['source'] );
		$this->assertSame( 1, $calls );
		$this->assertSame( 'adminCDN', $first['quotas'][0]['name'] );
		$this->assertSame( 100, $first['quotas'][0]['percent'] );
		$this->assertSame( '', $first['promos'][0]['cta_url'] );
		$this->assertCount( 1, $first['toolbox'] );
		$this->assertSame( 'https://wpcy.com/docs', $first['toolbox'][0]['href'] );

		// Second call hits fresh transient with zero HTTP.
		$second = $catalog->sync( false );
		$this->assertSame( 'cache', $second['source'] );
		$this->assertSame( 1, $calls );
	}

	/**
	 * HTTP failure sets backoff and returns stale cache when available.
	 */
	public function test_http_failure_uses_backoff_and_stale_fallback(): void {
		EntitlementsStore::$transients[ PublicCatalog::TRANSIENT_STALE ] = array(
			'updated_at' => '2026-11-20T00:00:00Z',
			'quotas'     => array(
				array(
					'name'  => 'Stale Quota',
					'usage' => 'ok',
				),
			),
			'promos'     => array(),
			'toolbox'    => array(),
		);

		$calls   = 0;
		$failing = static function () use ( &$calls ) {
			++$calls;
			return new WP_Error( 'http_failed', 'timeout' );
		};

		$catalog = new PublicCatalog(
			$failing,
			array( EntitlementsStore::class, 'get_transient' ),
			array( EntitlementsStore::class, 'set_transient' )
		);

		$first = $catalog->sync( false );
		$this->assertSame( 'stale', $first['source'] );
		$this->assertSame( 'Stale Quota', $first['quotas'][0]['name'] );
		$this->assertSame( 1, $calls );

		// Next non-forced sync obeys 1h backoff without another HTTP call.
		$second = $catalog->sync( false );
		$this->assertSame( 'stale', $second['source'] );
		$this->assertSame( 1, $calls );
	}
}
