<?php
declare(strict_types=1);

namespace {
	defined( "ABSPATH" ) || define( "ABSPATH", __DIR__ );
}

namespace WenPai\ChinaYes {
	function get_settings() {
		return [];
	}
}

namespace {
	$deregistered_scripts = [];

	function add_action() {}
	function add_filter() {}
	function remove_action() {}
	function is_admin() {
		return false;
	}
	function wp_deregister_script( $handle ) {
		global $deregistered_scripts;
		$deregistered_scripts[] = $handle;
	}

	require_once __DIR__ . "/../Service/Performance.php";

	$performance = new \WenPai\ChinaYes\Service\Performance();
	$performance->optimize_wordpress();

	if ( in_array( "jquery-migrate", $deregistered_scripts, true ) ) {
		fwrite( STDERR, "FAIL: jquery-migrate should not be deregistered on front-end requests.\n" );
		exit( 1 );
	}

	echo "PASS: jquery-migrate stays registered on front-end requests.\n";
}
