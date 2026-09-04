<?php
/**
 * Register `wp wpcy` commands when WP-CLI is the current SAPI.
 *
 * Plugin::create() wiring is M1-05b. This file is composer autoload.files
 * so status|doctor|config work on CLI without that wiring. No-op on web.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

if ( defined( 'WPCY_CLI_REGISTERED' ) ) {
	return;
}

define( 'WPCY_CLI_REGISTERED', true );

$config  = new \WenPai\ChinaYes\Config\Repository();
$checker = new \WenPai\ChinaYes\Diagnostics\Checker( null, null, null, $config );

\WP_CLI::add_command( 'wpcy status', new \WenPai\ChinaYes\Cli\StatusCommand( $checker, $config ) );
\WP_CLI::add_command( 'wpcy doctor', new \WenPai\ChinaYes\Cli\DoctorCommand( $checker, $config ) );
\WP_CLI::add_command( 'wpcy config', new \WenPai\ChinaYes\Cli\ConfigCommand( $config ) );
\WP_CLI::add_command( 'wpcy migrate', new \WenPai\ChinaYes\Cli\MigrateCommand() );

( new \WenPai\ChinaYes\Diagnostics\SiteHealth( $checker ) )->register();
