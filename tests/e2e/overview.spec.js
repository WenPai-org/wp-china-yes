const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

const OK_TARGETS = [
	{
		target: 'api.wenpai.net',
		result: 'ok',
		latency_ms: 229,
		checked_at: '2026-09-06T04:00:00Z',
		suggestion: null,
	},
	{
		target: 'downloads.wenpai.net',
		result: 'ok',
		latency_ms: 200,
		checked_at: '2026-09-06T04:00:00Z',
		suggestion: null,
	},
	{
		target: 'googlefonts.admincdn.com',
		result: 'ok',
		latency_ms: 61,
		checked_at: '2026-09-06T04:00:00Z',
		suggestion: null,
	},
	{
		target: 'googleajax.admincdn.com',
		result: 'ok',
		latency_ms: 55,
		checked_at: '2026-09-06T04:00:00Z',
		suggestion: null,
	},
	{
		target: 'jsd.admincdn.com',
		result: 'ok',
		latency_ms: 58,
		checked_at: '2026-09-06T04:00:00Z',
		suggestion: null,
	},
	{
		target: 'cdnjs.admincdn.com',
		result: 'ok',
		latency_ms: 74,
		checked_at: '2026-09-06T04:00:00Z',
		suggestion: null,
	},
	{
		target: 'cn.cravatar.com',
		result: 'ok',
		latency_ms: 48,
		checked_at: '2026-09-06T04:00:00Z',
		suggestion: null,
	},
];

function restPath( href ) {
	const url = new URL( href );
	const route = url.searchParams.get( 'rest_route' );
	if ( route ) {
		return route;
	}
	const marker = '/wp-json';
	const idx = url.pathname.indexOf( marker );
	if ( idx !== -1 ) {
		return url.pathname.slice( idx + marker.length ) || '/';
	}
	return url.pathname;
}

async function mockOverview( page, overrides = {} ) {
	const bodies = {
		diagnostics: { targets: OK_TARGETS },
		stats: {
			installed_at: '2026-07-31T06:12:00Z',
			days: 14,
			series: {
				mirror_downloads: [
					{ date: '2026-08-31', value: 18 },
					{ date: '2026-09-01', value: 26 },
					{ date: '2026-09-02', value: 22 },
					{ date: '2026-09-03', value: 31 },
					{ date: '2026-09-04', value: 28 },
					{ date: '2026-09-05', value: 24 },
					{ date: '2026-09-06', value: 42 },
				],
			},
		},
		events: { events: [] },
		binding: { status: 'unbound' },
		migration: { status: 'none' },
		probe: { checked_at: null, probes: [] },
		...overrides.bodies,
	};
	const status = { stats: 200, ...overrides.status };

	await page.addInitScript( ( settings ) => {
		let stored;
		Object.defineProperty( window, 'wpcyAdmin', {
			configurable: true,
			enumerable: true,
			set( value ) {
				stored = value || {};
				stored.settings = Object.assign(
					{},
					stored.settings || {},
					settings
				);
			},
			get() {
				return stored;
			},
		} );
	}, overrides.settings || { profile: 'domestic', recovery_mode: false } );

	await page.route(
		( url ) => {
			try {
				return restPath( url.href ).indexOf( '/wpcy/v1/' ) === 0;
			} catch ( error ) {
				void error;
				return false;
			}
		},
		async ( route ) => {
			const raw = restPath( route.request().url() );
			const path = raw.split( '?' )[ 0 ].replace( /\/+$/, '' );
			const method = route.request().method().toUpperCase();
			if ( method !== 'GET' ) {
				await route.continue();
				return;
			}
			if ( path === '/wpcy/v1/diagnostics' ) {
				await route.fulfill( {
					status: status.diagnostics || 200,
					contentType: 'application/json',
					body: JSON.stringify( bodies.diagnostics ),
				} );
				return;
			}
			if ( path === '/wpcy/v1/stats' ) {
				if ( overrides.hangStats ) {
					await new Promise( ( resolve ) => {
						setTimeout( resolve, 20000 );
					} );
				}
				await route.fulfill( {
					status: status.stats,
					contentType: 'application/json',
					body: JSON.stringify( bodies.stats ),
				} );
				return;
			}
			if ( path === '/wpcy/v1/events' ) {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( bodies.events ),
				} );
				return;
			}
			if ( path === '/wpcy/v1/binding' ) {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( bodies.binding ),
				} );
				return;
			}
			if ( path === '/wpcy/v1/migration/report' ) {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( bodies.migration ),
				} );
				return;
			}
			if ( path === '/wpcy/v1/diagnostics/client-probe' ) {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( bodies.probe ),
				} );
				return;
			}
			await route.continue();
		}
	);
}

test.describe( 'overview', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( '页头四标签与 Hero 标题', async ( { page } ) => {
		await mockOverview( page );
		await openAdminPage( page, 'wpcy' );
		const tabs = page.locator( '.wpcy-tabs .wpcy-tab' );
		await expect( tabs ).toHaveCount( 4 );
		await expect( tabs.nth( 0 ) ).toContainText( '概览' );
		await expect( tabs.nth( 1 ) ).toContainText( '设置' );
		await expect( tabs.nth( 2 ) ).toContainText( '服务' );
		await expect( tabs.nth( 3 ) ).toContainText( '诊断' );
		await expect( page.locator( '.hero-l h1' ) ).toContainText(
			'国内访问 WordPress 的事'
		);
		expect( await page.locator( '.btn-primary' ).count() ).toBeLessThanOrEqual(
			1
		);
	} );

	test( '国内正常：核心服务已接通', async ( { page } ) => {
		await mockOverview( page );
		await openAdminPage( page, 'wpcy' );
		await expect( page.getByText( '更新与安装包' ).first() ).toBeVisible();
		await expect( page.getByText( '已接通' ).first() ).toBeVisible();
		await expect( page.getByText( '运行诊断' ) ).toBeVisible();
		await expect( page.locator( '.hero-a-n' ) ).toContainText( '3/4' );
		await expect( page.locator( '.hero-collapse' ) ).toHaveCount( 0 );
	} );

	test( '跨境：下一步绑定本站，主按钮唯一', async ( { page } ) => {
		await mockOverview( page, {
			settings: {
				profile: 'crossborder',
				recovery_mode: false,
				connectivity: {
					wordpress_org: 'off',
					public_assets: { items: [ 'google_fonts' ], scope: 'admin' },
					avatar: { admin: 'cravatar_cn', frontend: 'off' },
				},
			},
		} );
		await openAdminPage( page, 'wpcy' );
		await expect( page.getByText( '人在国内、站在海外' ) ).toBeVisible();
		await expect( page.getByText( '下一步：绑定本站。' ) ).toBeVisible();
		expect( await page.locator( '.btn-primary' ).count() ).toBe( 1 );
		await expect( page.locator( '.btn-primary' ) ).toContainText( '绑定本站' );
	} );

	test( '降级：已回退与立即重试', async ( { page } ) => {
		const targets = OK_TARGETS.map( ( row ) =>
			row.target === 'api.wenpai.net' ||
			row.target === 'downloads.wenpai.net'
				? { ...row, result: 'fallback', latency_ms: 2410 }
				: row
		);
		await mockOverview( page, {
			bodies: { diagnostics: { targets } },
		} );
		await openAdminPage( page, 'wpcy' );
		await expect( page.getByText( '已自动回原始上游' ) ).toBeVisible();
		await expect( page.getByText( '立即重试' ) ).toBeVisible();
		expect( await page.locator( '.btn-primary' ).count() ).toBeLessThanOrEqual(
			1
		);
	} );

	test( '刚安装：还没有数据', async ( { page } ) => {
		await mockOverview( page, {
			bodies: {
				stats: {
					installed_at: new Date().toISOString(),
					days: 14,
					series: { mirror_downloads: [] },
				},
			},
		} );
		await openAdminPage( page, 'wpcy' );
		await expect( page.getByText( '已经开始为你接通' ) ).toBeVisible();
		await expect( page.getByText( '还没有数据' ).first() ).toBeVisible();
	} );

	test( '恢复模式横幅', async ( { page } ) => {
		await mockOverview( page, {
			settings: { profile: 'domestic', recovery_mode: true },
		} );
		await openAdminPage( page, 'wpcy' );
		await expect(
			page.getByText( '恢复模式已开启：', { exact: false } )
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: '退出恢复模式' } )
		).toBeVisible();
		await expect( page.getByText( '叶子现在什么都不做' ) ).toBeVisible();
	} );

	test( '升级站确认场景', async ( { page } ) => {
		await mockOverview( page, {
			settings: {
				profile: 'domestic',
				profile_confirmed_at: null,
				recovery_mode: false,
			},
			bodies: {
				migration: {
					status: 'ok',
					migrated_at: '2026-09-04T08:00:00Z',
				},
			},
		} );
		await openAdminPage( page, 'wpcy' );
		await expect( page.getByText( '确认你的站点场景。' ) ).toBeVisible();
		expect( await page.locator( '.btn-primary' ).count() ).toBe( 1 );
	} );

	test( 'Hero 无折叠，锚点查看详情', async ( { page } ) => {
		await mockOverview( page );
		await openAdminPage( page, 'wpcy' );
		await expect( page.locator( '.hero-collapse' ) ).toHaveCount( 0 );
		await expect( page.locator( '.hero-a-more' ) ).toContainText( '查看详情' );
		await expect( page.locator( '.svc-mx' ) ).toBeVisible();
		await expect( page.locator( '.evx' ) ).toBeVisible();
	} );

	test( '/stats 挂起 10 秒后出现 LoadError', async ( { page } ) => {
		test.setTimeout( 30000 );
		await mockOverview( page, { hangStats: true } );
		await page.goto( '/wp-admin/admin.php?page=wpcy', {
			waitUntil: 'domcontentloaded',
		} );
		await expect(
			page.getByText( '暂时无法读取统计，请刷新页面重试。' )
		).toBeVisible( { timeout: 12000 } );
		await expect( page.getByText( '还没有数据' ) ).toHaveCount( 0 );
	} );

	test( '/stats 500 显示 LoadError 不显示还没有数据', async ( { page } ) => {
		await mockOverview( page, { status: { stats: 500 } } );
		await openAdminPage( page, 'wpcy' );
		await expect(
			page.getByText( '暂时无法读取统计，请刷新页面重试。' )
		).toBeVisible();
		await expect( page.getByText( '还没有数据' ) ).toHaveCount( 0 );
	} );

	test( '/diagnostics 500 Hero 不显示已接通', async ( { page } ) => {
		await mockOverview( page, { status: { diagnostics: 500 } } );
		await openAdminPage( page, 'wpcy' );
		await expect(
			page.getByText( '暂时无法读取线路状态，请刷新页面重试。' )
		).toBeVisible();
		await expect( page.locator( '.hero-svc .pill.ok' ) ).toHaveCount( 0 );
		await expect( page.locator( '.hero-svc' ).getByText( '已接通' ) ).toHaveCount(
			0
		);
	} );

	test( '混合站用 OV-10 混合标题', async ( { page } ) => {
		await mockOverview( page, {
			settings: {
				profile: 'mixed',
				recovery_mode: false,
				connectivity: {
					wordpress_org: 'off',
					public_assets: { items: [ 'google_fonts' ], scope: 'admin' },
					avatar: { admin: 'cravatar_cn', frontend: 'off' },
				},
			},
		} );
		await openAdminPage( page, 'wpcy' );
		await expect( page.getByText( '访客在哪都不等' ) ).toBeVisible();
		await expect( page.getByText( '人在国内、站在海外' ) ).toHaveCount( 0 );
	} );
} );
