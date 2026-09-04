#!/usr/bin/env php
<?php
/**
 * Sign a data-residency ruleset JSON (Ed25519 detached).
 *
 * Usage: php scripts/sign-ruleset.php <ruleset.json> <private-key-file> [--kid wpcy-ruleset-2026]
 *
 * TEST ONLY keys live in tests/fixtures/keys/. Never use a production key here.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! is_readable( $autoload ) ) {
	fwrite( STDERR, "vendor/autoload.php is missing; run composer install.\n" );
	exit( 1 );
}
require $autoload;

use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;

const WPCY_SIGN_RULESET_DEFAULT_KID = 'wpcy-ruleset-2026';

/**
 * Print usage and exit 1.
 *
 * @return void
 */
function wpcy_sign_ruleset_usage() {
	fwrite( STDERR, "Usage: php scripts/sign-ruleset.php <ruleset.json> <private-key-file> [--kid wpcy-ruleset-2026]\n" );
	exit( 1 );
}

$args       = array_slice( $argv, 1 );
$kid        = WPCY_SIGN_RULESET_DEFAULT_KID;
$positional = array();
$count      = count( $args );
for ( $i = 0; $i < $count; $i++ ) {
	$arg = $args[ $i ];
	if ( '--kid' === $arg ) {
		if ( $i + 1 >= $count ) {
			wpcy_sign_ruleset_usage();
		}
		++$i;
		$kid = (string) $args[ $i ];
		continue;
	}
	if ( 0 === strpos( $arg, '--kid=' ) ) {
		$kid = substr( $arg, 6 );
		continue;
	}
	if ( 0 === strpos( $arg, '-' ) ) {
		fwrite( STDERR, "Unknown option: {$arg}\n" );
		wpcy_sign_ruleset_usage();
	}
	$positional[] = $arg;
}

if ( 2 !== count( $positional ) || '' === $kid ) {
	wpcy_sign_ruleset_usage();
}

$json_path = $positional[0];
$key_path  = $positional[1];

if ( ! is_readable( $json_path ) ) {
	fwrite( STDERR, "Cannot read ruleset: {$json_path}\n" );
	exit( 1 );
}
if ( ! is_readable( $key_path ) ) {
	fwrite( STDERR, "Cannot read private key: {$key_path}\n" );
	exit( 1 );
}

if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
	fwrite( STDERR, "ext-sodium is required.\n" );
	exit( 1 );
}

$raw = file_get_contents( $json_path );
if ( ! is_string( $raw ) || '' === $raw ) {
	fwrite( STDERR, "Ruleset file is empty: {$json_path}\n" );
	exit( 1 );
}

$decoded = json_decode( $raw, true );
if ( ! is_array( $decoded ) ) {
	fwrite( STDERR, "Ruleset is not a JSON object: {$json_path}\n" );
	exit( 1 );
}

unset( $decoded['signature'] );
$decoded['kid'] = $kid;

$secret = wpcy_sign_ruleset_load_secret( $key_path );
if ( '' === $secret ) {
	exit( 1 );
}

$message = Ruleset::canonicalize( $decoded );
if ( '' === $message ) {
	fwrite( STDERR, "Canonical JSON is empty.\n" );
	exit( 1 );
}

$signature            = sodium_crypto_sign_detached( $message, $secret );
$decoded['signature'] = sodium_bin2base64( $signature, SODIUM_BASE64_VARIANT_ORIGINAL );

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
if ( defined( 'JSON_THROW_ON_ERROR' ) ) {
	$flags |= JSON_THROW_ON_ERROR;
}

$encoded = json_encode( $decoded, $flags );
if ( ! is_string( $encoded ) || '' === $encoded ) {
	fwrite( STDERR, "Failed to encode signed ruleset.\n" );
	exit( 1 );
}

$written = file_put_contents( $json_path, $encoded . "\n" );
if ( false === $written ) {
	fwrite( STDERR, "Failed to write: {$json_path}\n" );
	exit( 1 );
}

exit( 0 );

/**
 * Load a Base64 Ed25519 secret key, skipping `#` comment lines.
 *
 * @param string $path Key file path.
 * @return string Secret key bytes, or empty on failure.
 */
function wpcy_sign_ruleset_load_secret( string $path ): string {
	$lines = file( $path, FILE_IGNORE_NEW_LINES );
	if ( ! is_array( $lines ) ) {
		fwrite( STDERR, "Cannot read private key: {$path}\n" );
		return '';
	}

	$b64 = '';
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line || '#' === $line[0] ) {
			continue;
		}
		$b64 = $line;
		break;
	}

	if ( '' === $b64 ) {
		fwrite( STDERR, "Private key file has no key material: {$path}\n" );
		return '';
	}

	try {
		$secret = sodium_base642bin( $b64, SODIUM_BASE64_VARIANT_ORIGINAL );
	} catch ( SodiumException $e ) {
		unset( $e );
		fwrite( STDERR, "Private key is not valid Base64.\n" );
		return '';
	}

	if ( SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $secret ) ) {
		fwrite( STDERR, "Private key length is not SODIUM_CRYPTO_SIGN_SECRETKEYBYTES.\n" );
		return '';
	}

	return $secret;
}
