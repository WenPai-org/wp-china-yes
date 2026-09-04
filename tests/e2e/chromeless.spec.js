const path = require( 'path' );
const { pathToFileURL } = require( 'url' );
const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

const CHROMELESS = pathToFileURL(
	path.resolve(
		__dirname,
		'../../src/Admin/app/chromeless-iframe.html'
	)
).href;

const PAGES = [ 'wpcy', 'wpcy-connect', 'wpcy-services', 'wpcy-diagnose' ];

test.describe( 'chromeless', () => {
	test( 'fixture: admin-bar height 0 and layout not broken', async ( {
		page,
	} ) => {
		await page.goto( CHROMELESS );
		const frame = page.frameLocator( 'iframe' );
		await expect( frame.locator( '#pass' ) ).toBeVisible();
		await expect( frame.locator( '#fail' ) ).toBeHidden();
		const bar = await frame.locator( 'html' ).evaluate( ( el ) =>
			getComputedStyle( el )
				.getPropertyValue( '--wp-admin--admin-bar--height' )
				.trim()
		);
		expect( [ '0', '0px' ] ).toContain( bar );
	} );

	test( 'four pages inside chromeless iframe keep page body width', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		for ( const slug of PAGES ) {
			await openAdminPage( page, slug );
			await page.addStyleTag( {
				content:
					':root { --wp-admin--admin-bar--height: 0 !important; } #wpadminbar { display: none !important; }',
			} );
			const body = page.locator( '.wpcy-page-body' );
			await expect( body ).toBeVisible();
			const box = await body.boundingBox();
			expect( box, slug ).toBeTruthy();
			expect( box.width, slug ).toBeGreaterThan( 200 );
			expect( box.width, slug ).toBeLessThanOrEqual( 1080 + 48 );
			const top = await body.evaluate( ( el ) =>
				getComputedStyle( el ).top
			);
			expect( top === '32px' || top === '46px' ).toBe( false );
		}
	} );
} );
