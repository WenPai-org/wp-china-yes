const path = require( 'path' );
const { defineConfig, devices } = require( '@playwright/test' );

/**
 * Visual screenshots. Reuses e2e login + BASE_URL.
 * Default Studio preview: http://localhost:8890 (admin / wpcy-preview).
 * CI / wp-env: set BASE_URL=http://localhost:8888.
 */
module.exports = defineConfig( {
	testDir: __dirname,
	timeout: 120000,
	expect: { timeout: 30000 },
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: 'list',
	outputDir: path.join( __dirname, 'test-results' ),
	use: {
		baseURL: process.env.BASE_URL || 'http://localhost:8890',
		locale: 'zh-CN',
		screenshot: 'off',
		trace: 'off',
		video: 'off',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
