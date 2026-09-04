/**
 * Shared Playwright helpers for the v4 admin app.
 *
 * Local Studio (preferred):
 *   BASE_URL=http://127.0.0.1:8891
 *   WP_USERNAME / WP_PASSWORD
 *   WPCY_E2E_SSH_HOST=wenpai
 *   WPCY_E2E_WP='/home/parallels/.studio/bin/studio wp --path /home/parallels/Studio/wpcy-40'
 *
 * CI: wp-env at http://localhost:8888 after `wp config set WPCY_KERNEL v4`.
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
	try {
		if ( sshHost ) {
			const remoteWp =
				process.env.WPCY_E2E_WP ||
				'/home/parallels/.studio/bin/studio wp --path /home/parallels/Studio/wpcy-40';
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
 * Fail the suite when the kernel is not v4. Never fall back to 3.x UI.
 *
 * @return {string} Kernel value.
 */
function requireV4Kernel() {
	const out = wpEval(
		'echo defined("WPCY_KERNEL") ? (string) WPCY_KERNEL : "undefined";'
	);
	const match = String( out ).match( /\b(v4|undefined)\b/ );
	if ( ! match || match[ 1 ] !== 'v4' ) {
		throw new Error(
			'WPCY_KERNEL is not v4; refusing to run 4.0 e2e against 3.x UI. Output: ' +
				out
		);
	}
	return match[ 1 ];
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

module.exports = {
	repoRoot,
	wpCli,
	wpEval,
	requireV4Kernel,
	loginAsAdmin,
	openAdminPage,
};
