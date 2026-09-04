const { test, expect } = require( '@playwright/test' );
const { wpEval, requireV4Kernel } = require( './helpers' );

test.describe( 'kernel', () => {
	test( 'E9: WPCY_KERNEL=v4 时 get_included_files 不含 /framework/', () => {
		const kernel = requireV4Kernel();
		expect( kernel ).toMatch( /v4/ );

		const out = wpEval(
			'$hits = array_filter( get_included_files(), function ( $p ) { return strpos( $p, "/framework/" ) !== false; } ); echo "FRAMEWORK_COUNT=" . count( $hits );'
		);
		expect( out ).toMatch( /FRAMEWORK_COUNT=0/ );
	} );
} );
