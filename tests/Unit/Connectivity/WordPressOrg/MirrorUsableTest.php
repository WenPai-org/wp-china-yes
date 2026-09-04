<?php
/**
 * PHPUnit rewrite of tests/test-wporg-mirror-fallback.php.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\WordPressOrg;

require_once dirname( __DIR__ ) . '/wp-error-stub.php';

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\WordPressOrg\MirrorProbe;
use WenPai\ChinaYes\Connectivity\WordPressOrg\Origins;
use WenPai\ChinaYes\Connectivity\WordPressOrg\WordPressOrgModule;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WP_Error;

/**
 * Sections map 1:1 onto tests/test-wporg-mirror-fallback.php.
 */
class MirrorUsableTest extends TestCase {

	/**
	 * Transient bag for the current test.
	 *
	 * @var array<string, mixed>
	 */
	private $transients = array();

	/**
	 * Canned wp_remote_get body.
	 *
	 * @var mixed
	 */
	private $canned;

	/**
	 * HTTP GET count.
	 *
	 * @var int
	 */
	private $http_calls = 0;

	/**
	 * Last GET URL.
	 *
	 * @var string
	 */
	private $last_url = '';

	/**
	 * Last GET args.
	 *
	 * @var array<string, mixed>
	 */
	private $last_args = array();

	/**
	 * Reset canned HTTP and transients.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->transients = array();
		$this->canned     = array();
		$this->http_calls = 0;
		$this->last_url   = '';
		$this->last_args  = array();
	}

	/**
	 * Mirror is usable for 200/206/302 with a binary content type.
	 *
	 * @testdox 镜像可用
	 */
	public function test_mirror_usable_when_status_and_type_match(): void {
		$this->assertTrue(
			$this->probe_with(
				array(
					'code'    => 200,
					'headers' => array( 'content-type' => 'application/octet-stream' ),
				)
			),
			'200 + octet-stream => 可用'
		);
		$this->assertTrue(
			$this->probe_with(
				array(
					'code'    => 206,
					'headers' => array( 'content-type' => 'application/zip' ),
				)
			),
			'206(Range 正常返回) + zip => 可用'
		);
		$this->assertTrue(
			$this->probe_with(
				array(
					'code'    => 302,
					'headers' => array( 'content-type' => 'application/octet-stream' ),
				)
			),
			'302 => 可用'
		);
	}

	/**
	 * Real failure shapes (404 JSON, 504, 404 HTML) are unusable.
	 *
	 * @testdox 镜像不可用：本次故障的真实形态
	 */
	public function test_mirror_unusable_for_real_failure_shapes(): void {
		$this->assertFalse(
			$this->probe_with(
				array(
					'code'    => 404,
					'headers' => array( 'content-type' => 'application/json; charset=UTF-8' ),
				)
			),
			'404 + JSON(rest_no_route) => 不可用'
		);
		$this->assertFalse(
			$this->probe_with(
				array(
					'code'    => 504,
					'headers' => array(),
				)
			),
			'504 网关超时 => 不可用'
		);
		$this->assertFalse(
			$this->probe_with(
				array(
					'code'    => 404,
					'headers' => array( 'content-type' => 'text/html; charset=utf-8' ),
				)
			),
			'404 + HTML(主题化404页) => 不可用'
		);
	}

	/**
	 * HTTP 200 with JSON or HTML is still unusable.
	 *
	 * @testdox 关键：状态码 200 但内容类型不对，也必须判不可用
	 */
	public function test_http_200_wrong_content_type_is_unusable(): void {
		$this->assertFalse(
			$this->probe_with(
				array(
					'code'    => 200,
					'headers' => array( 'content-type' => 'application/json' ),
				)
			),
			'200 + JSON => 不可用（光看状态码会误判）'
		);
		$this->assertFalse(
			$this->probe_with(
				array(
					'code'    => 200,
					'headers' => array( 'content-type' => 'text/html; charset=utf-8' ),
				)
			),
			'200 + HTML => 不可用（同上）'
		);
	}

	/**
	 * Transport-layer WP_Error is unusable.
	 *
	 * @testdox 传输层失败
	 */
	public function test_transport_error_is_unusable(): void {
		$this->assertFalse( $this->probe_with( new WP_Error() ), 'WP_Error => 不可用' );
	}

	/**
	 * Probe URL is a real plugin zip with Range and a self-request flag.
	 *
	 * @testdox 探测路径必须是真实安装包路径
	 */
	public function test_probe_path_is_a_real_package_path(): void {
		$this->probe_with(
			array(
				'code'    => 200,
				'headers' => array( 'content-type' => 'application/zip' ),
			)
		);

		$this->assertNotFalse(
			strpos( $this->last_url, '/plugin/' ),
			'探测 /plugin/ 包路径而非根路径或 API 路径'
		);
		$this->assertNotFalse( strpos( $this->last_url, '.zip' ), '探测路径以 .zip 结尾' );
		$this->assertArrayHasKey( 'Range', $this->last_args['headers'], '带 Range 头，不下载整个安装包' );
		$this->assertNotEmpty( $this->last_args['_wp_china_yes'], '标记自身请求，避免被本过滤器再次改写' );
	}

	/**
	 * Tiny shell bodies with a correct type are still unusable.
	 *
	 * @testdox 体积校验：200 + 正确内容类型但只有十几字节的空壳，也必须判不可用
	 */
	public function test_tiny_shell_response_is_unusable(): void {
		$this->assertFalse(
			$this->probe_with(
				array(
					'code'    => 206,
					'headers' => array(
						'content-type'  => 'application/zip',
						'content-range' => 'bytes 0-0/14',
					),
				)
			),
			'206 + content-range 总体积 14B => 不可用'
		);
		$this->assertTrue(
			$this->probe_with(
				array(
					'code'    => 206,
					'headers' => array(
						'content-type'  => 'application/zip',
						'content-range' => 'bytes 0-0/87533',
					),
				)
			),
			'206 + 总体积 87533B => 可用'
		);
		$this->assertFalse(
			$this->probe_with(
				array(
					'code'    => 200,
					'headers' => array(
						'content-type'   => 'application/octet-stream',
						'content-length' => '14',
					),
				)
			),
			'200 + content-length 14B（上游忽略 Range）=> 不可用'
		);
		$this->assertTrue(
			$this->probe_with(
				array(
					'code'    => 200,
					'headers' => array(
						'content-type'   => 'application/octet-stream',
						'content-length' => '38489',
					),
				)
			),
			'200 + content-length 38489B => 可用'
		);
		$this->assertTrue(
			$this->probe_with(
				array(
					'code'    => 206,
					'headers' => array( 'content-type' => 'application/zip' ),
				)
			),
			'无体积头时不因体积判不可用（避免过度拒绝）'
		);
		$this->assertFalse(
			$this->probe_with(
				array(
					'code'    => 200,
					'headers' => array(
						'content-type'  => 'application/zip',
						'content-range' => 'bytes 0-0/1023',
					),
				)
			),
			'恰好低于阈值(1023B) => 不可用'
		);
	}

	/**
	 * Probe hits the package origin, never the metadata origin.
	 *
	 * @testdox 探测必须打**安装包主机**，不能打元数据主机
	 */
	public function test_probe_hits_package_origin_not_api_origin(): void {
		$this->probe_with(
			array(
				'code'    => 206,
				'headers' => array( 'content-type' => 'application/zip' ),
			)
		);

		$this->assertSame(
			0,
			strpos( $this->last_url, Origins::PACKAGE_ORIGIN ),
			'探测目标是安装包主机 ' . Origins::PACKAGE_ORIGIN
		);
		$this->assertFalse(
			0 === strpos( $this->last_url, Origins::API_ORIGIN . '/plugin/' ),
			'探测不打元数据主机（该路径在那里是永久 404）'
		);
		$this->assertNotSame(
			Origins::API_ORIGIN,
			Origins::PACKAGE_ORIGIN,
			'元数据源与安装包源是两个不同主机'
		);
	}

	/**
	 * Rewrites split by upstream host; query strings are kept.
	 *
	 * @testdox 改写按来源主机分流：元数据走 API 源，安装包走包源
	 */
	public function test_rewrite_splits_by_upstream_host(): void {
		$this->assertSame(
			Origins::PACKAGE_ORIGIN . '/plugin/classic-editor.zip',
			$this->rewritten_url( 'https://downloads.wordpress.org/plugin/classic-editor.zip' ),
			'downloads.wordpress.org 的安装包 => 包源（3.9 曾错写成元数据源，导致 JSON 404）'
		);
		$this->assertSame(
			Origins::PACKAGE_ORIGIN . '/translation/core/6.5/zh_CN.zip',
			$this->rewritten_url( 'https://downloads.wordpress.org/translation/core/6.5/zh_CN.zip' ),
			'downloads.wordpress.org 的语言包 => 包源'
		);
		$this->assertSame(
			Origins::API_ORIGIN . '/plugins/update-check/1.1/',
			$this->rewritten_url( 'https://api.wordpress.org/plugins/update-check/1.1/' ),
			'api.wordpress.org 的元数据 => 元数据源'
		);
		$this->assertSame(
			Origins::API_ORIGIN . '/plugins/info/1.2/?action=query_plugins',
			$this->rewritten_url( 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins' ),
			'查询串被保留'
		);
		$this->assertSame(
			'',
			$this->rewritten_url( 'https://example.com/plugin/foo.zip' ),
			'非 WordPress.org 主机不改写'
		);
	}

	/**
	 * Three is_usable() calls issue one HTTP probe.
	 *
	 * @testdox 状态缓存：不能每次请求都去探测
	 */
	public function test_usable_state_is_cached_across_calls(): void {
		$this->transients = array();
		$this->http_calls = 0;
		$this->canned     = array(
			'code'    => 200,
			'headers' => array( 'content-type' => 'application/zip' ),
		);

		$probe = $this->new_probe();
		$probe->is_usable();
		$probe->is_usable();
		$probe->is_usable();

		$this->assertSame( 1, $this->http_calls, '三次调用只探测一次（实得 ' . $this->http_calls . ' 次）' );
	}

	/**
	 * Down TTL is shorter than up TTL so recovery is faster.
	 *
	 * @testdox 不可用状态同样被缓存，但 TTL 更短以便自动恢复
	 */
	public function test_down_ttl_is_shorter_than_up_ttl(): void {
		$this->assertLessThan(
			Origins::UP_TTL,
			Origins::DOWN_TTL,
			'不可用 TTL 短于可用 TTL（镜像修好后能较快恢复加速）'
		);
	}

	/**
	 * Baseline: metadata still rewrites; packages do not without entitlement.
	 */
	public function test_without_package_entitlement_only_metadata_rewrites(): void {
		$module                                 = $this->new_module( false );
		$this->transients[ Origins::STATE_KEY ] = 'up';

		$this->assertNull(
			$module->rewritten_url( 'https://downloads.wordpress.org/plugin/classic-editor.zip' )
		);
		$this->assertSame(
			Origins::API_ORIGIN . '/plugins/update-check/1.1/',
			$module->rewritten_url( 'https://api.wordpress.org/plugins/update-check/1.1/' )
		);
	}

	/**
	 * Unusable mirror leaves the upstream URL untouched.
	 */
	public function test_unusable_mirror_does_not_rewrite(): void {
		$module                                 = $this->new_module( true );
		$this->transients[ Origins::STATE_KEY ] = 'down';

		$this->assertNull(
			$module->rewritten_url( 'https://api.wordpress.org/plugins/update-check/1.1/' )
		);
		$this->assertNull(
			$module->rewritten_url( 'https://downloads.wordpress.org/plugin/classic-editor.zip' )
		);
	}

	/**
	 * Enabled() is false when off, in recovery, or rewrite is disabled.
	 */
	public function test_enabled_requires_auto_and_not_recovery(): void {
		$module = $this->new_module( true );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertTrue(
			$module->enabled(
				new MapConfig(
					array(
						'connectivity.wordpress_org' => 'auto',
						'recovery_mode'              => false,
					)
				),
				$env
			)
		);
		$this->assertFalse(
			$module->enabled(
				new MapConfig(
					array(
						'connectivity.wordpress_org' => 'off',
						'recovery_mode'              => false,
					)
				),
				$env
			)
		);
		$this->assertFalse(
			$module->enabled(
				new MapConfig(
					array(
						'connectivity.wordpress_org' => 'auto',
						'recovery_mode'              => true,
					)
				),
				$env
			)
		);
		$this->assertFalse(
			$module->enabled(
				new MapConfig(
					array(
						'connectivity.wordpress_org' => 'auto',
						'recovery_mode'              => false,
					)
				),
				new Environment( Environment::ADMIN, false )
			)
		);
	}

	/**
	 * Module id matches the config path.
	 */
	public function test_id_is_connectivity_wordpress_org(): void {
		$this->assertSame( 'connectivity.wordpress_org', $this->new_module( true )->id() );
	}

	/**
	 * Run one probe with a canned HTTP result.
	 *
	 * @param mixed $canned Response array or WP_Error.
	 */
	private function probe_with( $canned ): bool {
		$this->transients = array();
		$this->http_calls = 0;
		$this->canned     = $canned;

		return $this->new_probe()->is_usable();
	}

	/**
	 * Rewrite helper matching 3.x rewritten_url() (packages entitled).
	 *
	 * @param string $url Upstream URL.
	 */
	private function rewritten_url( string $url ): string {
		$this->transients[ Origins::STATE_KEY ] = 'up';
		$module                                 = $this->new_module( true );
		$module->filter_wordpress_org( false, array(), $url );

		return $module->last_request_url();
	}

	/**
	 * Fresh probe bound to this test's HTTP and transient bags.
	 */
	private function new_probe(): MirrorProbe {
		return new MirrorProbe(
			function ( $url, $args ) {
				++$this->http_calls;
				$this->last_url  = (string) $url;
				$this->last_args = is_array( $args ) ? $args : array();
				return $this->canned;
			},
			function ( $key ) {
				return $this->transients[ $key ] ?? false;
			},
			function ( $key, $value, $ttl = 0 ) {
				unset( $ttl );
				$this->transients[ $key ] = $value;
				return true;
			}
		);
	}

	/**
	 * Module with an injectable package-entitlement flag.
	 *
	 * @param bool $packages_allowed Whether install/language packages may rewrite.
	 */
	private function new_module( bool $packages_allowed ): WordPressOrgModule {
		return new WordPressOrgModule(
			$this->new_probe(),
			static function ( $url, $args ) {
				unset( $args );
				return $url;
			},
			static function () use ( $packages_allowed ) {
				return $packages_allowed;
			}
		);
	}
}
