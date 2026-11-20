const { test, expect } = require( '@playwright/test' );
const { isRestRoute, loginAsAdmin, openAdminPage, wpEval } = require( './helpers' );

/**
 * M-UI-2 O1 · F3 / D5: settings page read failures show LoadError.
 *
 * Settings themselves are inlined as window.wpcyAdmin, so /wpcy/v1/settings
 * is never requested; the reads that can fail are /wpcy/v1/binding (site
 * settings page) and /wpcy/v1/network-settings (network admin).
 */

const FAILURES = {
	'HTTP 500': ( route ) =>
		route.fulfill( {
			status: 500,
			contentType: 'application/json',
			body: JSON.stringify( {
				code: 'wpcy_e2e_down',
				message: 'down',
				data: { status: 500 },
			} ),
		} ),
	离线: ( route ) => route.abort( 'internetdisconnected' ),
};

/**
 * Fail GET requests for one wpcy/v1 route; count them.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          path Route.
 * @param {Function}                        fail Failure responder.
 * @return {Promise<{hits:number,settings:number}>} Counters.
 */
async function failRead( page, path, fail ) {
	const seen = { hits: 0, settings: 0 };
	await page.route(
		( url ) => String( url ).includes( 'wpcy' ),
		async ( route ) => {
			const req = route.request();
			if ( isRestRoute( req, '/wpcy/v1/settings' ) ) {
				seen.settings++;
			}
			if ( isRestRoute( req, path ) && req.method() === 'GET' ) {
				seen.hits++;
				return fail( route );
			}
			return route.fallback();
		}
	);
	return seen;
}

/**
 * The page shows a LoadError notice for the region, no blank page and no
 * raw WP_Error.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          what Region name.
 */
async function expectLoadError( page, what ) {
	const root = page.locator( '#wpcy-admin-root' );
	const notice = root
		.locator( '.wpcy-notice, [class*="notice"]' )
		.filter( { hasText: `暂时无法读取${ what }，请刷新页面重试。` } )
		.first();
	await expect( notice ).toBeVisible();
	await expect( notice.getByRole( 'button', { name: '重试' } ) ).toBeVisible();
	await expect(
		root.getByRole( 'heading', { name: '设置', level: 1 } )
	).toBeVisible();
	await expect( root ).not.toContainText( 'WP_Error' );
	await expect( root ).not.toContainText( 'wpcy_e2e_down' );
}

test.describe( 'settings LoadError (O1 F3 / D5)', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	for ( const [ label, fail ] of Object.entries( FAILURES ) ) {
		test( `站点设置页 /binding ${ label} → 绑定状态 LoadError`, async ( {
			page,
		} ) => {
			const seen = await failRead( page, '/wpcy/v1/binding', fail );
			await openAdminPage( page, 'wpcy-connect' );
			await expectLoadError( page, '绑定状态' );
			expect( seen.hits ).toBeGreaterThan( 0 );
			expect( seen.settings ).toBe( 0 );
		} );
	}

	for ( const [ label, fail ] of Object.entries( FAILURES ) ) {
		test( `网络后台 /network-settings ${ label } → 设置 LoadError`, async ( {
			page,
		} ) => {
			const out = String(
				wpEval( 'echo is_multisite() ? "WPCY_MS" : "WPCY_SS";' )
			);
			test.skip(
				! out.includes( 'WPCY_MS' ),
				'network admin needs a multisite install'
			);
			const seen = await failRead(
				page,
				'/wpcy/v1/network-settings',
				fail
			);
			await page.goto( '/wp-admin/network/admin.php?page=wpcy-connect', {
				waitUntil: 'domcontentloaded',
			} );
			await page
				.locator( '#wpcy-admin-root' )
				.getByRole( 'heading', { level: 1 } )
				.waitFor( { state: 'visible' } );
			await expectLoadError( page, '设置' );
			expect( seen.hits ).toBeGreaterThan( 0 );
			expect( seen.settings ).toBe( 0 );
		} );
	}
} );
