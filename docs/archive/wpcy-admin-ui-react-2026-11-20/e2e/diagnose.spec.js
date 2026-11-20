const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

test.describe( 'diagnose', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( '进入诊断页不点按钮就有行', async ( { page } ) => {
		await page.route(
			( url ) => {
				try {
					const href = new URL( url.href );
					const route = href.searchParams.get( 'rest_route' ) || '';
					return (
						route.indexOf( '/wpcy/v1/diagnostics' ) === 0 ||
						href.pathname.indexOf( '/wpcy/v1/diagnostics' ) !== -1
					);
				} catch ( error ) {
					void error;
					return false;
				}
			},
			async ( route ) => {
				if ( route.request().method().toUpperCase() !== 'GET' ) {
					await route.continue();
					return;
				}
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( {
						targets: [
							{
								target: 'api.wenpai.net',
								result: 'ok',
								latency_ms: 120,
								checked_at: '2026-09-06T04:00:00Z',
								suggestion: null,
							},
						],
					} ),
				} );
			}
		);
		await openAdminPage( page, 'wpcy-diagnose' );
		await expect( page.getByText( 'api.wenpai.net' ) ).toBeVisible( {
			timeout: 15000,
		} );
		expect( await page.locator( '.tbl tbody tr' ).count() ).toBeGreaterThan(
			0
		);
		await expect( page.getByText( 'status.wpcy.com' ) ).toBeVisible();
	} );

	test( 'E5: 立即检查后表格至少一行目标', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-diagnose' );
		await page.getByRole( 'button', { name: '立即检查' } ).click();
		await expect( page.getByText( 'api.wenpai.net' ) ).toBeVisible( {
			timeout: 90000,
		} );
		expect( await page.locator( '.tbl tbody tr' ).count() ).toBeGreaterThan(
			0
		);
	} );

	test( 'E6: 进入恢复模式到达 wpcy-recovery', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-diagnose' );
		await page.getByRole( 'link', { name: '进入恢复模式' } ).click();
		await expect( page ).toHaveURL( /page=wpcy-recovery/ );
		await expect(
			page.getByRole( 'heading', { name: '文派叶子 · 恢复模式' } )
		).toBeVisible();
	} );

	test( 'hash 不改 ?page=', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-diagnose' );
		const before = new URL( page.url() );
		expect( before.searchParams.get( 'page' ) ).toBe( 'wpcy-diagnose' );
		await page.locator( '#probe' ).waitFor();
		const after = new URL( page.url() );
		expect( after.searchParams.get( 'page' ) ).toBe( 'wpcy-diagnose' );
		expect( after.pathname ).toBe( before.pathname );
	} );
} );
