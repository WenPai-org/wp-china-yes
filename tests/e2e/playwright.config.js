const path = require( 'path' );
const { defineConfig, devices } = require( '@playwright/test' );

/**
 * wp-env default URL is http://localhost:8888.
 * Local Studio: BASE_URL=http://127.0.0.1:8891 (tunnel to wenpai).
 */
module.exports = defineConfig( {
	testDir: __dirname,
	globalSetup: path.join( __dirname, 'global-setup.js' ),
	timeout: 120000,
	expect: { timeout: 30000 },
	fullyParallel: false,
	workers: 1,
	retries: process.env.GITHUB_ACTIONS ? 1 : 0,
	reporter: process.env.GITHUB_ACTIONS ? [ [ 'github' ], [ 'list' ] ] : 'list',
	outputDir: path.join( __dirname, 'test-results' ),
	use: {
		baseURL: process.env.BASE_URL || 'http://localhost:8888',
		locale: 'zh-CN',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		video: 'off',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
