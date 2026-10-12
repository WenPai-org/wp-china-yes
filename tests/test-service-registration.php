<?php

$root  = dirname( __DIR__ );
$base  = file_get_contents( $root . '/Service/Base.php' );
$super = file_get_contents( $root . '/Service/Super.php' );

foreach ( [ 'Fonts', 'Comments', 'Avatar', 'Adblock', 'Migration', 'Language' ] as $service ) {
	if ( false !== strpos( $super, "new {$service}" ) ) {
		fwrite( STDERR, "FAIL Super still creates duplicate {$service} service\n" );
		exit( 1 );
	}
}

foreach ( [ 'Migration', 'Avatar', 'Fonts', 'Comments', 'Language' ] as $service ) {
	if ( 1 !== substr_count( $base, "'{$service}'" ) ) {
		fwrite( STDERR, "FAIL Base must register {$service} exactly once\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $base, "'Monitor'" ) || false !== strpos( $base, "'Widget'" ) ) {
	fwrite( STDERR, "FAIL legacy mutating monitor/dashboard widget still loads\n" );
	exit( 1 );
}

echo "PASS services have one owner and retired services no longer load\n";
