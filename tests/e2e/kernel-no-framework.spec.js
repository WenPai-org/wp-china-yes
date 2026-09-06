const { test, expect } = require( '@playwright/test' );
const { wpEval } = require( './helpers' );

test.describe( 'kernel', () => {
	test( 'E9: get_included_files 不含 /framework/', () => {
		const out = wpEval(
			'$hits = array_filter( get_included_files(), function ( $p ) { return strpos( $p, "/framework/" ) !== false; } ); echo "FRAMEWORK_COUNT=" . count( $hits );'
		);
		expect( out ).toMatch( /FRAMEWORK_COUNT=0/ );
	} );
} );
