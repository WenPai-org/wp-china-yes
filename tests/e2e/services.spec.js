const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

test.describe( 'services', () => {
	test( 'E4: 文派服务页可达：未绑定空态与绑定按钮', async ( { page } ) => {
		await loginAsAdmin( page );
		const response = await openAdminPage( page, 'wpcy-services' );
		expect( response && response.status() ).toBe( 200 );
		await expect(
			page.getByRole( 'heading', { name: '文派服务', level: 1 } )
		).toBeVisible();
		// M2-05 起为真实页：未绑定态显示「绑定本站」主动作（原型 03-services-unbound）。
		await expect(
			page.getByRole( 'button', { name: '绑定本站' } )
		).toBeVisible();
		const root = page.locator( '#wpcy-admin-root' );
		await expect( root ).not.toBeEmpty();
		const box = await root.boundingBox();
		expect( box && box.height ).toBeGreaterThan( 40 );
	} );
} );
