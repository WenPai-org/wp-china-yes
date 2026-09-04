const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

test.describe( 'services', () => {
	test( 'E4: 文派服务占位页可达且无白屏', async ( { page } ) => {
		await loginAsAdmin( page );
		const response = await openAdminPage( page, 'wpcy-services' );
		expect( response && response.status() ).toBe( 200 );
		await expect(
			page.getByRole( 'heading', { name: '文派服务', level: 1 } )
		).toBeVisible();
		await expect(
			page.getByText( '绑定与小工具将在后续版本提供' )
		).toBeVisible();
		const root = page.locator( '#wpcy-admin-root' );
		await expect( root ).not.toBeEmpty();
		const box = await root.boundingBox();
		expect( box && box.height ).toBeGreaterThan( 40 );
	} );
} );
