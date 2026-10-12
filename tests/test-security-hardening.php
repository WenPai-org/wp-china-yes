<?php

$root = dirname( __DIR__ );
$actions = file_get_contents( $root . '/framework/functions/actions.php' );
$comments = file_get_contents( $root . '/Service/Comments.php' );
$database = file_get_contents( $root . '/Service/Database.php' );
$admin_options = file_get_contents( $root . '/framework/classes/admin-options.class.php' );
$plugin = file_get_contents( $root . '/Plugin.php' );

$checks = [
	'backup endpoints require a capability and fixed option key' =>
		false !== strpos( $actions, "current_user_can( \$capability ) && 'wp_china_yes' === \$unique" ),
	'comment moderation requires moderate_comments' =>
		false !== strpos( $comments, "current_user_can( 'moderate_comments' )" ),
	'database service no longer exposes repair.php' =>
		false === strpos( $database, "define( 'WP_ALLOW_REPAIR'" ),
	'admin options save checks menu capability' =>
		false !== strpos( $admin_options, "current_user_can( \$this->args['menu_capability'] )" ),
	'activation does not disable third-party plugins' =>
		false === strpos( $plugin, "deactivate_plugins( 'wp-china-no/" )
		&& false === strpos( $plugin, "deactivate_plugins( 'wp-china-plus/" )
		&& false === strpos( $plugin, "deactivate_plugins( 'kill-429/" ),
];

foreach ( $checks as $message => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "FAIL {$message}\n" );
		exit( 1 );
	}
	echo "PASS {$message}\n";
}
