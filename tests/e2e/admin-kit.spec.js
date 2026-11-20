const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

test.describe( 'wpcy 4.0 kit admin screens', () => {
	test( 'renders overview, settings, services, diagnose, and onboarding wizard in .wenpai-app shell', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );

		for ( const slug of [
			'wpcy',
			'wpcy-connect',
			'wpcy-services',
			'wpcy-diagnose',
			'wpcy-onboarding',
		] ) {
			const resp = await openAdminPage( page, slug );
			expect( resp && resp.status() ).toBe( 200 );
			await expect( page.locator( '.wenpai-app' ) ).toBeVisible();
		}
	} );
} );
