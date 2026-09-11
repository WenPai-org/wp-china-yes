const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

const GO_PREFIX = 'https://wpcy.com/go/';
const SITE_HASH = 'sitehashABCDEFGH';
const HASH_TAIL = SITE_HASH.slice( -8 );

/**
 * Local mock-app URL. Uses the opposite of BASE_URL host (localhost vs 127.0.0.1)
 * so the sandbox iframe is cross-origin to the admin page.
 *
 * wp-env: http://localhost:8888/wp-content/plugins/wp-china-yes/tests/fixtures/mock-app/index.html
 *
 * @return {string} Absolute entry URL.
 */
function fixtureEntryUrl() {
	if ( process.env.WPCY_E2E_MOCK_APP_URL ) {
		return process.env.WPCY_E2E_MOCK_APP_URL;
	}
	const base = process.env.BASE_URL || 'http://localhost:8888';
	const url = new URL( base );
	const altHost = url.hostname === 'localhost' ? '127.0.0.1' : 'localhost';
	return `${ url.protocol }//${ altHost }${
		url.port ? ':' + url.port : ''
	}/wp-content/plugins/wp-china-yes/tests/fixtures/mock-app/index.html`;
}

/**
 * REST path from a pretty /wp-json URL or ?rest_route=.
 *
 * @param {string} href Absolute request URL.
 * @return {string} Path such as /wpcy/v1/binding.
 */
function restPath( href ) {
	const url = new URL( href );
	const route = url.searchParams.get( 'rest_route' );
	if ( route ) {
		return route;
	}
	const marker = '/wp-json';
	const idx = url.pathname.indexOf( marker );
	if ( idx !== -1 ) {
		const path = url.pathname.slice( idx + marker.length );
		return path || '/';
	}
	return url.pathname;
}

/**
 * @param {import('@playwright/test').Page|import('@playwright/test').BrowserContext} target Route host.
 * @param {(path: string, method: string) => boolean}                                 match  Path matcher.
 * @param {Function}                                                                  handler Route handler.
 */
async function restRoute( target, match, handler ) {
	await target.route(
		( url ) => {
			try {
				const path = restPath( url.href );
				return path.indexOf( '/wpcy/v1/' ) === 0 && match( path, '' );
			} catch ( error ) {
				void error;
				return false;
			}
		},
		handler
	);
}

/**
 * @param {import('@playwright/test').Page|import('@playwright/test').BrowserContext} target Route host.
 */
async function mockBound( target ) {
	await restRoute(
		target,
		( path ) =>
			path === '/wpcy/v1/binding' || path === '/wpcy/v1/binding/',
		async ( route ) => {
			const method = route.request().method();
			if ( method === 'GET' ) {
				await route.fulfill( {
					json: {
						status: 'bound',
						site_hash: SITE_HASH,
						bound_at: '2026-09-05T12:00:00Z',
					},
				} );
				return;
			}
			if ( method === 'DELETE' ) {
				await route.fulfill( {
					json: {
						status: 'revoked',
						site_hash: null,
						bound_at: null,
					},
				} );
				return;
			}
			await route.continue();
		}
	);
	await restRoute(
		target,
		( path ) => path.indexOf( '/wpcy/v1/binding/start' ) === 0,
		async ( route ) => {
			await route.fulfill( {
				json: {
					status: 'bound',
					site_hash: SITE_HASH,
					bound_at: '2026-09-05T12:00:00Z',
				},
			} );
		}
	);
}

/**
 * @param {import('@playwright/test').Page|import('@playwright/test').BrowserContext} target Route host.
 * @param {Object}                                                                    extras Extra list fields.
 */
async function mockApps( target, extras ) {
	const entryUrl = ( extras && extras.entryUrl ) || fixtureEntryUrl();
	const indexStatus = ( extras && extras.indexStatus ) || 'ok';
	const apps =
		extras && Array.isArray( extras.apps )
			? extras.apps
			: [
					{
						id: 'mock-app',
						name: { zh_CN: '站点体检', en_US: 'Site Check' },
						description: {
							zh_CN: '检查站点健康与连接状态。',
							en_US: 'Check site health.',
						},
						entry_url: entryUrl,
						version: '1.2.0',
						tier: 'free',
						entitlement: null,
						permissions: [
							'site:read',
							'data:read',
							'data:write',
							'data:delete',
							'entitlement:read',
							'go:open',
						],
						go_service: 'mock-app',
						entitlement_status: {
							status: 'active',
							quota: {},
						},
					},
					{
						id: 'paidtool',
						name: { zh_CN: '付费工具', en_US: 'Paid Tool' },
						description: {
							zh_CN: '需要权益才能使用。',
							en_US: 'Needs an entitlement.',
						},
						entry_url: entryUrl,
						version: '1.0.0',
						tier: 'paid',
						entitlement: 'wpcy-leaf-paid',
						permissions: [ 'data:read', 'go:open' ],
						go_service: 'paidtool',
						entitlement_status: {
							status: 'expired',
							quota: {},
						},
					},
			  ];

	const store = {};
	let writes = 0;

	await restRoute(
		target,
		( path ) => path === '/wpcy/v1/apps' || path.indexOf( '/wpcy/v1/apps/' ) === 0,
		async ( route ) => {
		const request = route.request();
		const override = request.headers()[ 'x-http-method-override' ];
		const method = (
			request.method() === 'POST' && override
				? override
				: request.method()
		).toUpperCase();
		const path = restPath( request.url() );

		if ( method === 'GET' && path === '/wpcy/v1/apps' ) {
			await route.fulfill( {
				json: { apps, index_status: indexStatus },
			} );
			return;
		}

		if ( path.indexOf( '/context' ) !== -1 ) {
			await route.fulfill( {
				json: {
					site_url: 'http://example.test',
					wp_version: '6.5',
					locale: 'zh_CN',
					is_multisite: false,
					user_can: { manage_options: true },
					active_plugins: [],
				},
			} );
			return;
		}

		if ( path.indexOf( '/entitlement' ) !== -1 ) {
			await route.fulfill( {
				json: { status: 'active', quota: {} },
			} );
			return;
		}

		if ( method === 'POST' && path.indexOf( '/go' ) !== -1 ) {
			await route.fulfill( {
				json: {
					url: GO_PREFIX + 'mock-app?utm_source=wpcy-plugin',
				},
			} );
			return;
		}

		const dataMatch = path.match( /\/data\/(.+)$/ );
		if ( dataMatch ) {
			const key = decodeURIComponent( dataMatch[ 1 ] );
			if ( method === 'PUT' ) {
				writes += 1;
				const body = request.postDataJSON() || {};
				store[ key ] = body.value;
				await route.fulfill( {
					json: { key, value: store[ key ] },
				} );
				return;
			}
			if ( method === 'GET' ) {
				await route.fulfill( {
					json: { key, value: store[ key ] },
				} );
				return;
			}
			if ( method === 'DELETE' ) {
				delete store[ key ];
				await route.fulfill( { json: { deleted: true } } );
				return;
			}
		}

		if ( method === 'GET' && /\/data$/.test( path ) ) {
			await route.fulfill( { json: Object.keys( store ) } );
			return;
		}

		await route.continue();
	}
	);

	return {
		writes() {
			return writes;
		},
	};
}

/**
 * @param {import('@playwright/test').Page|import('@playwright/test').BrowserContext} target Route host.
 */
async function mockQuotas( target ) {
	await restRoute(
		target,
		( path ) =>
			path === '/wpcy/v1/entitlements' ||
			path === '/wpcy/v1/entitlements/',
		async ( route ) => {
		await route.fulfill( {
			json: {
				entitlements: [
					{
						id: 'wpcy-leaf-motusnap-100',
						service: 'motusnap',
						status: 'active',
						quota: {
							used: 12,
							limit: 100,
							period: 'month',
							resets_at: '2026-10-01T00:00:00Z',
						},
					},
					{
						id: 'wpcy-leaf-windfonts',
						service: 'windfonts',
						status: 'exhausted',
						quota: {
							used: 100,
							limit: 100,
							period: 'month',
							resets_at: '2026-10-01T00:00:00Z',
						},
					},
					{
						id: 'wpcy-leaf-admincdn',
						service: 'admincdn',
						status: 'expired',
						quota: {
							used: 0,
							limit: 100,
							period: 'month',
							resets_at: null,
						},
					},
				],
			},
		} );
	} );
}

test.describe( 'apps A1–A10', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'A1: 未绑定打开文派服务', async ( { page } ) => {
		const response = await openAdminPage( page, 'wpcy-services' );
		expect( response && response.status() ).toBe( 200 );
		await expect(
			page.getByRole( 'heading', { name: '服务', level: 1 } )
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: '绑定本站' } )
		).toBeVisible();
		await expect( page.getByTestId( 'wpcy-quota-empty' ) ).toContainText(
			'绑定后显示'
		);
		await expect( page.getByTestId( 'wpcy-apps-empty' ) ).toBeVisible();
	} );

	test( 'A2: mock 绑定成功', async ( { page } ) => {
		await mockBound( page );
		await openAdminPage( page, 'wpcy-services' );
		await expect( page.getByText( '已绑定' ) ).toBeVisible();
		await expect( page.getByTestId( 'wpcy-site-hash-tail' ) ).toHaveText(
			HASH_TAIL
		);
	} );

	test( 'A3: 绑定后解锁与可用服务检测行', async ( { page } ) => {
		await mockBound( page );
		await mockApps( page );
		await openAdminPage( page, 'wpcy-services' );
		await expect( page.getByText( '可用服务' ) ).toBeVisible();
		await expect( page.locator( '.detect-line' ) ).toBeVisible();
		await expect( page.getByText( '权益与配额' ) ).toHaveCount( 0 );
	} );

	test( 'A4: 加载 mock 工具', async ( { page } ) => {
		const entryUrl = fixtureEntryUrl();
		await mockBound( page );
		await mockApps( page, { entryUrl } );
		await openAdminPage( page, 'wpcy-services' );
		await page.getByRole( 'button', { name: '站点体检' } ).click();

		const iframe = page.getByTestId( 'wpcy-app-iframe' );
		await expect( iframe ).toBeVisible();
		await expect( iframe ).toHaveAttribute(
			'sandbox',
			'allow-scripts allow-forms'
		);
		await expect( iframe ).toHaveAttribute(
			'referrerpolicy',
			'strict-origin'
		);
		const sandbox = await iframe.getAttribute( 'sandbox' );
		expect( sandbox ).not.toContain( 'allow-same-origin' );

		const frame = page.frameLocator( '[data-testid="wpcy-app-iframe"]' );
		await expect( frame.getByTestId( 'log' ) ).toContainText( 'ready' );
	} );

	test( 'A5: 读写删数据', async ( { page } ) => {
		const entryUrl = fixtureEntryUrl();
		await mockBound( page );
		await mockApps( page, { entryUrl } );
		await openAdminPage( page, 'wpcy-services' );
		await page.getByRole( 'button', { name: '站点体检' } ).click();

		const frame = page.frameLocator( '[data-testid="wpcy-app-iframe"]' );
		await expect( frame.getByTestId( 'log' ) ).toContainText( 'set ok' );
		await expect( frame.getByTestId( 'log' ) ).toContainText( 'get ok' );
		await expect( frame.getByTestId( 'log' ) ).toContainText( 'delete ok' );
	} );

	test( 'A6: 无权益工具点击', async ( { page } ) => {
		await mockBound( page );
		await mockApps( page );
		await openAdminPage( page, 'wpcy-services' );
		await page.getByRole( 'button', { name: '付费工具' } ).click();

		const dialog = page.getByRole( 'dialog' );
		await expect( dialog ).toBeVisible();
		await expect( dialog.getByText( '需要权益才能使用' ) ).toBeVisible();
		await expect(
			dialog.getByRole( 'link', { name: '了解 →' } )
		).toHaveAttribute( 'href', new RegExp( '^' + GO_PREFIX ) );
		await expect( page.getByTestId( 'wpcy-app-iframe' ) ).toHaveCount( 0 );
	} );

	test( 'A7: 跨 origin 消息', async ( { page } ) => {
		const entryUrl = fixtureEntryUrl();
		await mockBound( page );
		const bag = await mockApps( page, { entryUrl } );
		await openAdminPage( page, 'wpcy-services' );
		await page.getByRole( 'button', { name: '站点体检' } ).click();
		await expect( page.getByTestId( 'wpcy-app-iframe' ) ).toBeVisible();
		const frame = page.frameLocator( '[data-testid="wpcy-app-iframe"]' );
		await expect( frame.getByTestId( 'log' ) ).toContainText( 'set ok' );

		const before = bag.writes();
		await page.evaluate( () => {
			const iframe = document.querySelector(
				'[data-testid="wpcy-app-iframe"]'
			);
			const event = new MessageEvent( 'message', {
				data: {
					wpcy: 1,
					type: 'data.set',
					request_id: 'evil',
					payload: { key: 'hack', value: 1 },
				},
				origin: 'https://evil.example',
				source: iframe && iframe.contentWindow,
			} );
			window.dispatchEvent( event );
		} );
		await page.waitForTimeout( 500 );
		expect( bag.writes() ).toBe( before );
	} );

	test( 'A8: 索引不可达', async ( { page } ) => {
		await mockBound( page );
		await mockApps( page, { apps: [], indexStatus: 'unreachable' } );
		await openAdminPage( page, 'wpcy-services' );

		await expect(
			page.getByText( '小工具目录暂时不可用' )
		).toBeVisible();
		await expect( page.getByText( '已绑定' ).first() ).toBeVisible();
	} );

	test( 'A9: 双层 iframe', async ( { page, context } ) => {
		const entryUrl = fixtureEntryUrl();
		await mockBound( context );
		await mockApps( context, { entryUrl } );

		const base = process.env.BASE_URL || 'http://localhost:8888';
		const wrapper = await context.newPage();
		// Same-origin wrapper so WordPress X-Frame-Options: SAMEORIGIN
		// allows the admin iframe. about:blank is blocked and times out.
		await wrapper.goto( base + '/', { waitUntil: 'domcontentloaded' } );
		await wrapper.setContent(
			`<!DOCTYPE html><html><body style="margin:0"><iframe id="host" title="chromeless host" src="${ base }/wp-admin/admin.php?page=wpcy-services" style="width:100%;height:100vh;border:0"></iframe></body></html>`
		);
		await wrapper.evaluate( () => {
			window.__wpcyFromChild = [];
			window.addEventListener( 'message', ( event ) => {
				if ( event.data && event.data.wpcy === 1 ) {
					window.__wpcyFromChild.push( event.data );
				}
			} );
		} );

		const host = wrapper.frameLocator( '#host' );
		await host.locator( '#wpcy-admin-root' ).waitFor( { state: 'attached' } );
		await host
			.getByRole( 'heading', { name: '服务', level: 1 } )
			.waitFor( { state: 'visible' } );
		await host.getByRole( 'button', { name: '站点体检' } ).click();
		await expect( host.getByTestId( 'wpcy-app-iframe' ) ).toBeVisible();
		await expect(
			host
				.frameLocator( '[data-testid="wpcy-app-iframe"]' )
				.getByTestId( 'log' )
		).toContainText( 'ready' );

		const fromChild = await wrapper.evaluate(
			() => window.__wpcyFromChild || []
		);
		const hostTypes = fromChild.filter( ( message ) =>
			[ 'init', 'result', 'error' ].includes( message.type )
		);
		expect( hostTypes ).toEqual( [] );
	} );

	test( 'A10: 错误 session_token 被拒绝', async ( { page } ) => {
		const entryUrl = fixtureEntryUrl();
		const sep = entryUrl.indexOf( '?' ) === -1 ? '?' : '&';
		await mockBound( page );
		const bag = await mockApps( page, {
			entryUrl: entryUrl + sep + 'bad_token=1',
		} );
		await openAdminPage( page, 'wpcy-services' );
		await page.getByRole( 'button', { name: '站点体检' } ).click();

		const before = bag.writes();
		const frame = page.frameLocator( '[data-testid="wpcy-app-iframe"]' );
		await expect( frame.getByTestId( 'log' ) ).toContainText(
			'session_invalid'
		);
		expect( bag.writes() ).toBe( before );
	} );
} );
