const { test, expect } = require( '@playwright/test' );
const {
	isRestRoute,
	loginAsAdmin,
	openAdminPage,
	restMethod,
} = require( './helpers' );

/**
 * WZ-01…WZ-05 on the live wizard. Every write is answered by page.route so
 * the Studio site keeps its real settings and binding.
 */

function json( route, body, status = 200 ) {
	return route.fulfill( {
		status,
		contentType: 'application/json',
		body: JSON.stringify( body ),
	} );
}

function merge( base, patch ) {
	const out = { ...base };
	for ( const [ key, value ] of Object.entries( patch || {} ) ) {
		out[ key ] =
			value && typeof value === 'object' && ! Array.isArray( value )
				? merge( base?.[ key ] || {}, value )
				: value;
	}
	return out;
}

/**
 * Mock settings writes and the binding flow.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {Object}                          opts Behaviour.
 * @return {Object} Counters.
 */
async function mockWizard( page, opts = {} ) {
	const seen = { puts: 0, polls: 0, deletes: 0 };
	await page.route(
		( url ) => String( url ).includes( 'wpcy' ),
		async ( route ) => {
			const req = route.request();
			const method = restMethod( req );
			if ( isRestRoute( req, '/wpcy/v1/settings' ) && method === 'PUT' ) {
				seen.puts++;
				if ( opts.saveFails ) {
					return json(
						route,
						{ code: 'wpcy_e2e_down', message: 'down' },
						500
					);
				}
				const headers = { ...req.headers() };
				delete headers[ 'x-http-method-override' ];
				delete headers[ 'content-type' ];
				const current = await route.fetch( { method: 'GET', headers } );
				const body = merge(
					await current.json(),
					req.postDataJSON() || {}
				);
				return json( route, body );
			}
			if ( isRestRoute( req, '/wpcy/v1/binding/start' ) ) {
				return json( route, { status: 'pending', site_hash: null } );
			}
			if ( isRestRoute( req, '/wpcy/v1/binding' ) ) {
				if ( method === 'DELETE' ) {
					seen.deletes++;
					return json( route, { status: 'unbound', site_hash: null } );
				}
				seen.polls++;
				// First GET is the mount read; later ones are WZ-03 polls.
				if ( seen.polls > 1 && opts.bindOnPoll ) {
					return json( route, {
						status: 'bound',
						site_hash: 'abcdef0123456789',
					} );
				}
				return json( route, {
					status: seen.polls > 1 ? 'pending' : 'unbound',
					site_hash: null,
				} );
			}
			return route.fallback();
		}
	);
	return seen;
}

async function toBindStep( page ) {
	await page.getByRole( 'button', { name: '继续' } ).click();
	await expect(
		page.getByRole( 'heading', { name: /已按「.+」配置好/ } )
	).toBeVisible();
	await page.getByRole( 'button', { name: '继续' } ).click();
	await page.getByRole( 'button', { name: '继续' } ).click();
	await expect(
		page.getByRole( 'heading', { name: '要绑定本站吗？' } )
	).toBeVisible();
}

test.describe( 'onboarding wizard', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'WZ-01 保存失败停在第 1 步并提示', async ( { page } ) => {
		await mockWizard( page, { saveFails: true } );
		await openAdminPage( page, 'wpcy-onboarding' );
		await page.locator( '.wizard label.choice' ).first().click();
		await page.getByRole( 'button', { name: '继续' } ).click();
		await expect( page.getByRole( 'alert' ) ).toContainText(
			'暂时无法保存设置'
		);
		await expect(
			page.getByRole( 'heading', { name: '你的站点在哪里？' } )
		).toBeVisible();
	} );

	test( 'WZ-03 绑定等待态只有取消，轮询到已绑定进入完成页', async ( {
		page,
	} ) => {
		const seen = await mockWizard( page, { bindOnPoll: true } );
		await openAdminPage( page, 'wpcy-onboarding' );
		await page.locator( '.wizard label.choice' ).first().click();
		await toBindStep( page );
		await page.getByRole( 'button', { name: '绑定本站' } ).click();
		const wizard = page.locator( '.wizard' );
		await expect( wizard ).toContainText( '正在绑定本站' );
		await expect(
			wizard.locator( '.actions' ).getByRole( 'button' )
		).toHaveText( [ '取消' ] );
		await expect(
			page.getByRole( 'heading', { name: '一切就绪' } )
		).toBeVisible( { timeout: 15000 } );
		expect( seen.polls ).toBeGreaterThan( 1 );
		await expect( wizard ).toContainText( '已绑定' );
	} );

	test( 'WZ-03 取消绑定发 DELETE 并回到绑定介绍', async ( { page } ) => {
		const seen = await mockWizard( page );
		await openAdminPage( page, 'wpcy-onboarding' );
		await page.locator( '.wizard label.choice' ).first().click();
		await toBindStep( page );
		await page.getByRole( 'button', { name: '绑定本站' } ).click();
		await page.getByRole( 'button', { name: '取消' } ).click();
		await expect(
			page.getByRole( 'button', { name: '绑定本站' } )
		).toBeVisible();
		expect( seen.deletes ).toBe( 1 );
	} );

	test( 'WZ-04 完成页计数来自设置，进入概览不再写入', async ( { page } ) => {
		const seen = await mockWizard( page );
		await openAdminPage( page, 'wpcy-onboarding' );
		await page.locator( '.wizard label.choice' ).first().click();
		await toBindStep( page );
		await page.getByRole( 'button', { name: '暂不，稍后再说' } ).click();
		await expect(
			page.locator( '[data-testid="wpcy-wz-count"]' )
		).toHaveText( /^\d+ 项$/ );
		const putsBefore = seen.puts;
		await page.getByRole( 'link', { name: '进入概览' } ).click();
		await page.waitForURL( /page=wpcy(&|$)/ );
		expect( seen.puts ).toBe( putsBefore );
	} );
} );
