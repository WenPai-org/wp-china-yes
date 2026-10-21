<?php
/**
 * Element hide, notice control and announcements: verification never throws,
 * failures never re-store the cache, and the cache name is bound to the trusted keys.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\Announcements\AnnouncementsModule;
use WenPai\ChinaYes\Admin\ElementHide\ElementHideModule;
use WenPai\ChinaYes\Admin\NoticeControl\NoticeControlModule;
use WenPai\ChinaYes\Apps\ManifestVerifier;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Support\TestKeys;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Same contract for the three signed admin feeds.
 */
class FeedHardeningTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		OptionStore::reset();
		RestError::reset();
	}

	/**
	 * The three feeds: fixture directory and transient base name.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function feeds(): array {
		return array(
			'element hide'   => array( 'element-hide', 'wpcy_element_hide_rules' ),
			'notice control' => array( 'notice-rules', 'wpcy_notice_rules' ),
			'announcements'  => array( 'announcements', 'wpcy_announcements' ),
		);
	}

	/**
	 * A signed feed with an injected 1e999 is rejected; refresh() does not throw.
	 *
	 * @dataProvider feeds
	 *
	 * @param string $fixture Fixture directory.
	 * @param string $base    Transient base name.
	 */
	public function test_feed_with_inf_is_rejected_without_exception( string $fixture, string $base ) {
		unset( $base );
		$json   = '{"zz":1e999,' . substr( $this->signed( $fixture ), 1 );
		$module = $this->module( $fixture, array( $json ) );
		$this->assertNull( $module->refresh() );
		$this->assertNull( $module->cached_document() );

		$control = $this->module( $fixture, array( $this->signed( $fixture ) ) );
		$this->assertIsArray( $control->refresh(), 'control' );
	}

	/**
	 * Empty body, invalid JSON, bad signature and a network error return the
	 * previous document without writing the cache.
	 *
	 * @dataProvider feeds
	 *
	 * @param string $fixture Fixture directory.
	 * @param string $base    Transient base name.
	 */
	public function test_feed_failures_do_not_restore_previous_cache( string $fixture, string $base ) {
		unset( $base );
		$bad              = json_decode( $this->signed( $fixture ), true );
		$bad['signature'] = sodium_bin2base64( str_repeat( "\x01", SODIUM_CRYPTO_SIGN_BYTES ), SODIUM_BASE64_VARIANT_ORIGINAL );
		$module           = $this->module( $fixture, array( $this->signed( $fixture ), '', '{"rules":', (string) wp_json_encode( $bad ) ) );

		$first = $module->refresh();
		$this->assertIsArray( $first );
		$name = $module->cache_key();
		$this->assertArrayHasKey( $name, AdminStore::$transients );
		AdminStore::$transients[ $name ]['wpcy_test_marker'] = 1;

		foreach ( array( 'empty body', 'invalid JSON', 'bad signature' ) as $label ) {
			$this->assertSame( $first, $module->refresh(), $label );
			$this->assertSame( 1, AdminStore::$transients[ $name ]['wpcy_test_marker'] ?? 0, $label . ': cache was re-stored' );
		}

		$network = $this->module( $fixture, null, 'https://wpcy.com/' . $fixture . '.json' );
		$this->assertSame( $first, $network->refresh(), 'network error' );
		$this->assertNotSame( array(), AdminStore::$http, 'network path was exercised' );
		$this->assertSame( 1, AdminStore::$transients[ $name ]['wpcy_test_marker'] ?? 0, 'network error: cache was re-stored' );
	}

	/**
	 * A document cached under other trusted keys is never read.
	 *
	 * @dataProvider feeds
	 *
	 * @param string $fixture Fixture directory.
	 * @param string $base    Transient base name.
	 */
	public function test_feed_cache_written_under_other_keys_is_not_read( string $fixture, string $base ) {
		unset( $base );
		$this->assertIsArray( $this->module( $fixture, array( $this->signed( $fixture ) ) )->refresh() );

		$pk    = sodium_bin2base64( sodium_crypto_sign_publickey( sodium_crypto_sign_keypair() ), SODIUM_BASE64_VARIANT_ORIGINAL );
		$other = $this->module( $fixture, array(), 'mock://feed', new ManifestVerifier( $pk, array_fill_keys( TestKeys::KIDS, $pk ) ) );
		$this->assertNull( $other->cached_document() );
	}

	/**
	 * A cache from an older release (bare base name) is ignored and dropped on the next store.
	 *
	 * @dataProvider feeds
	 *
	 * @param string $fixture Fixture directory.
	 * @param string $base    Transient base name.
	 */
	public function test_feed_legacy_unversioned_cache_is_ignored_and_dropped( string $fixture, string $base ) {
		$seed = $this->module( $fixture, array( $this->signed( $fixture ) ) );
		$doc  = $seed->refresh();
		$this->assertIsArray( $doc );
		$stored = AdminStore::$transients[ $seed->cache_key() ];

		AdminStore::reset();
		OptionStore::reset();
		AdminStore::$transients[ $base ] = $stored;

		$module = $this->module( $fixture, array( $this->signed( $fixture ) ) );
		$this->assertNotSame( $base, $module->cache_key() );
		$this->assertNull( $module->cached_document() );
		$this->assertIsArray( $module->refresh() );
		$this->assertArrayNotHasKey( $base, AdminStore::$transients );
	}

	/**
	 * Fixture document re-signed with the throwaway key, as JSON.
	 *
	 * @param string $fixture Fixture directory.
	 */
	private function signed( string $fixture ): string {
		$raw = file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/' . $fixture . '/sample.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$doc = json_decode( (string) $raw, true );
		$this->assertIsArray( $doc );
		unset( $doc['signature'], $doc['kid'] );
		return SignedPayload::encode( $doc );
	}

	/**
	 * Feed module with a fetcher answering $bodies in order, or no fetcher.
	 *
	 * @param string                  $fixture  Fixture directory.
	 * @param array<int, string>|null $bodies   Bodies, or null for the HTTP path.
	 * @param string                  $source   Source.
	 * @param ManifestVerifier|null   $verifier Verifier; null trusts the throwaway key.
	 * @return ElementHideModule|NoticeControlModule|AnnouncementsModule
	 */
	private function module( string $fixture, ?array $bodies, string $source = 'mock://feed', ?ManifestVerifier $verifier = null ) {
		$verifier = $verifier instanceof ManifestVerifier ? $verifier : SignedPayload::verifier();
		$fetcher  = null;
		if ( null !== $bodies ) {
			$fetcher = static function () use ( &$bodies ) {
				return (string) array_shift( $bodies );
			};
		}
		if ( 'element-hide' === $fixture ) {
			return new ElementHideModule( new Repository(), $source, $fetcher, null, $verifier );
		}
		if ( 'notice-rules' === $fixture ) {
			return new NoticeControlModule( new Repository(), $source, $fetcher, null, $verifier );
		}
		return new AnnouncementsModule( new Repository(), $source, $fetcher, $verifier );
	}
}
