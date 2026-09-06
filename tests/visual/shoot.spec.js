/**
 * Visual acceptance screenshots.
 *
 * Reuses tests/e2e login + BASE_URL. Default Studio preview:
 *   BASE_URL=http://localhost:8890
 *   WP_USERNAME=admin
 *   WP_PASSWORD=wpcy-preview
 *
 * Output: docs/design/screens/<run>/  (run from screens.json or WPCY_VISUAL_RUN)
 *
 * State prep: screens.json `prep.kind` is `none` | `rest-mock` | `need-fixture`.
 * WP-CLI persist is not invoked unless WPCY_VISUAL_WP / WPCY_E2E_WP / WPCY_E2E_SSH_HOST
 * is set (this Mac has no Docker; Studio CLI is missing main.mjs).
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( '../e2e/helpers' );
const catalog = require( './screens.json' );

if ( ! process.env.WP_USERNAME ) {
	process.env.WP_USERNAME = 'admin';
}
if ( ! process.env.WP_PASSWORD ) {
	process.env.WP_PASSWORD = 'wpcy-preview';
}

const repoRoot = path.resolve( __dirname, '../..' );
const runId = process.env.WPCY_VISUAL_RUN || catalog.run || 'sop-baseline';
const outDir = path.join( repoRoot, 'docs/design/screens', runId );

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
		const extracted = url.pathname.slice( idx + marker.length );
		return extracted || '/';
	}
	return url.pathname;
}

/**
 * True when `path` equals `expected` ignoring a trailing slash.
 *
 * @param {string} actual   Request path.
 * @param {string} expected Catalog path.
 * @return {boolean} Match.
 */
function pathEquals( actual, expected ) {
	const a = actual.replace( /\/+$/, '' );
	const b = expected.replace( /\/+$/, '' );
	return a === b;
}

/**
 * Install session REST mocks from screens.json.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {Array<Object>}                   routes Catalog routes.
 * @return {Promise<void>}
 */
async function mockRoutes( page, routes ) {
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
			const request = route.request();
			const current = restPath( request.url() );
			const method = request.method().toUpperCase();
			const match = ( routes || [] ).find(
				( item ) =>
					( item.method || 'GET' ).toUpperCase() === method &&
					pathEquals( current, item.path )
			);
			if ( match ) {
				await route.fulfill( {
					status: match.status || 200,
					contentType: 'application/json',
					body: JSON.stringify( match.body ),
				} );
				return;
			}
			await route.continue();
		}
	);
}

test.describe( 'visual screenshots', () => {
	test.beforeAll( () => {
		fs.mkdirSync( outDir, { recursive: true } );
	} );

	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	for ( const screen of catalog.screens ) {
		test( `${ screen.id } → ${ screen.filename }`, async ( { page } ) => {
			const prep = screen.prep || { kind: 'none' };

			if ( prep.kind === 'need-fixture' ) {
				test.skip( true, prep.reason || screen.notes || '需 fixture' );
				return;
			}

			if ( prep.kind === 'rest-mock' ) {
				await mockRoutes( page, prep.routes || [] );
			}

			await openAdminPage( page, screen.page );

			if ( screen.waitForText ) {
				await expect(
					page.getByText( screen.waitForText, { exact: false } ).first()
				).toBeVisible();
			}

			const dest = path.join( outDir, screen.filename );
			await page.screenshot( { path: dest, fullPage: true } );
			expect( fs.existsSync( dest ), dest ).toBe( true );
		} );
	}
} );
