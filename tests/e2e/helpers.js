/**
 * Shared Playwright helpers for the v4 admin app.
 *
 * Local Studio (preferred):
 *   BASE_URL=http://127.0.0.1:8891
 *   WP_USERNAME / WP_PASSWORD
 *   WPCY_E2E_SSH_HOST=wenpai
 *   WPCY_E2E_WP='"$HOME/.studio/bin/studio" wp --path "$HOME/Studio/wpcy-40"'
 *   (required whenever WPCY_E2E_SSH_HOST is set; expanded by the remote shell)
 *
 * CI: wp-env at http://localhost:8888.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const repoRoot = path.resolve( __dirname, '../..' );

/**
 * POSIX single-quote wrap so ssh remote shell keeps PHP intact.
 *
 * @param {string} value Raw argument.
 * @return {string} Quoted argument.
 */
function shQuote( value ) {
	return `'${ String( value ).replace( /'/g, `'\\''` ) }'`;
}

/**
 * Run WP-CLI. Throws if the command fails.
 *
 * @param {string[]} args Arguments after `wp` (e.g. ['eval', 'echo 1;']).
 * @return {string} Combined stdout.
 */
function wpCli( args ) {
	const sshHost = process.env.WPCY_E2E_SSH_HOST;
	const remoteWp = process.env.WPCY_E2E_WP;
	if ( sshHost && ! remoteWp ) {
		throw new Error(
			`WPCY_E2E_SSH_HOST=${ sshHost } requires WPCY_E2E_WP, the WP-CLI command to run on that host ` +
				`(e.g. WPCY_E2E_WP='"$HOME/.studio/bin/studio" wp --path "$HOME/Studio/wpcy-40"').`
		);
	}
	try {
		if ( sshHost ) {
			const remote = `${ remoteWp } ${ args.map( shQuote ).join( ' ' ) }`;
			return execFileSync( 'ssh', [ '-o', 'BatchMode=yes', sshHost, remote ], {
				encoding: 'utf8',
				timeout: 120000,
			} );
		}

		return execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
			encoding: 'utf8',
			cwd: repoRoot,
			timeout: 120000,
		} );
	} catch ( error ) {
		throw new Error(
			`wp-cli failed (${ args.join( ' ' ) }): ${ error.stdout || '' }\n${
				error.stderr || error.message
			}`
		);
	}
}

/**
 * `wp eval` a PHP snippet.
 *
 * @param {string} php PHP to evaluate.
 * @return {string} Command output.
 */
function wpEval( php ) {
	return wpCli( [ 'eval', php ] );
}

/**
 * Fail the suite when Core\\Plugin is not loaded.
 *
 * @return {string} Kernel marker.
 */
function requireCoreKernel() {
	const out = wpEval(
		'echo class_exists("WenPai\\\\ChinaYes\\\\Core\\\\Plugin") ? "core" : "missing";'
	);
	if ( ! /\bcore\b/.test( String( out ) ) ) {
		throw new Error(
			'4.0 kernel Core\\Plugin is not loaded; refusing to run e2e. Output: ' +
				out
		);
	}
	return 'core';
}

/**
 * Log in as the site administrator.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function loginAsAdmin( page ) {
	const user = process.env.WP_USERNAME || 'admin';
	const pass = process.env.WP_PASSWORD || 'password';
	// Visit first so WordPress can set wordpress_test_cookie.
	await page.goto( '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	if ( await page.locator( '#wpadminbar' ).count() ) {
		return;
	}
	await page.request.post( '/wp-login.php', {
		form: {
			log: user,
			pwd: pass,
			rememberme: 'forever',
			'wp-submit': 'Log In',
			redirect_to: '/wp-admin/',
			testcookie: '1',
		},
	} );
	await page.goto( '/wp-admin/', { waitUntil: 'domcontentloaded' } );
	await page.locator( '#wpadminbar' ).waitFor( { state: 'visible' } );
}

/**
 * Open a WPCY admin screen and wait for the React root.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          slug Menu slug (wpcy, wpcy-connect, …).
 * @return {import('@playwright/test').Response|null} Navigation response.
 */
async function openAdminPage( page, slug ) {
	const response = await page.goto( `/wp-admin/admin.php?page=${ slug }`, {
		waitUntil: 'domcontentloaded',
	} );
	if ( slug !== 'wpcy-recovery' ) {
		await page.locator( '#wpcy-admin-root' ).waitFor( { state: 'attached' } );
		await page.locator( '#wpcy-admin-root' ).getByRole( 'heading', { level: 1 } ).waitFor( {
			state: 'visible',
		} );
	}
	return response;
}

/**
 * Does a request hit the given wpcy/v1 route (pretty or ?rest_route= URLs)?
 *
 * @param {import('@playwright/test').Request} request Request.
 * @param {string}                             route   Route, e.g. "/wpcy/v1/settings".
 * @return {boolean} Match.
 */
function isRestRoute( request, route ) {
	const url = new URL( request.url() );
	const rest = url.searchParams.get( 'rest_route' );
	const pathName = rest || url.pathname.replace( /^.*\/wp-json/, '' );
	return pathName.replace( /\/+$/, '' ) === route;
}

/**
 * HTTP verb as WordPress sees it (apiFetch may tunnel PUT/DELETE via POST).
 *
 * @param {import('@playwright/test').Request} request Request.
 * @return {string} Upper-case method.
 */
function restMethod( request ) {
	const override = request.headers()[ 'x-http-method-override' ];
	return ( override || request.method() ).toUpperCase();
}

module.exports = {
	isRestRoute,
	restMethod,
	repoRoot,
	wpCli,
	wpEval,
	requireCoreKernel,
	loginAsAdmin,
	openAdminPage,
};
