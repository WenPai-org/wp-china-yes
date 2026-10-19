#!/usr/bin/env php
<?php
/**
 * §4.5 naming audit. PHP CLI, no WordPress runtime.
 *
 * Scans PHP/JS paths plus browser-facing identifiers. Server-internal names
 * (PHP classes, option/transient keys, repository names) are out of scope.
 *
 * Usage:
 *   php scripts/lint-naming.php [extra-file...]
 *   php scripts/lint-naming.php --self-test
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$args = array_slice( $argv, 1 );

if ( in_array( '--self-test', $args, true ) ) {
	exit( wpcy_lint_naming_self_test( $root ) );
}

$extra = array();
foreach ( $args as $arg ) {
	if ( 0 === strpos( $arg, '-' ) ) {
		fwrite( STDERR, "Unknown option: {$arg}\n" );
		exit( 1 );
	}
	$extra[] = $arg;
}

$keep    = wpcy_lint_naming_load_keep( $root . '/scripts/lint-naming-keep.json' );
$findings = wpcy_lint_naming_scan( $root, $extra );
$open     = wpcy_lint_naming_apply_keep( $findings, $keep );

if ( array() === $open ) {
	fwrite( STDOUT, "lint-naming: clean\n" );
	exit( 0 );
}

foreach ( $open as $row ) {
	fwrite(
		STDERR,
		$row['path'] . ':' . $row['line'] . ': ' . $row['token'] . ' in ' . $row['kind'] . ' "' . $row['value'] . "\"\n"
	);
}
fwrite( STDERR, 'lint-naming: ' . count( $open ) . " hit(s)\n" );
exit( 1 );

/**
 * Banned morphemes. `admin` / `upload` / `loading` are whole tokens and stay clean.
 *
 * @return list<string>
 */
function wpcy_lint_naming_banned_tokens(): array {
	return array(
		'ad',
		'ads',
		'adblock',
		'advert',
		'banner',
		'sponsor',
		'promo',
		'promotion',
		'guanggao',
	);
}

/**
 * Split $text into lowercase morphemes. Underscore/hyphen/camelCase; not substrings.
 *
 * @param string $text Raw identifier or path.
 * @return list<string>
 */
function wpcy_lint_naming_tokens( string $text ): array {
	$tokens = array();
	if ( 1 === preg_match( '/(?:^|[^A-Za-z0-9])gg_/i', $text ) ) {
		$tokens[] = 'gg_';
	}

	$parts = preg_split( '/[^A-Za-z0-9]+/', $text );
	if ( ! is_array( $parts ) ) {
		return $tokens;
	}
	foreach ( $parts as $part ) {
		if ( '' === $part ) {
			continue;
		}
		$bits = preg_split( '/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', $part );
		if ( ! is_array( $bits ) ) {
			$bits = array( $part );
		}
		foreach ( $bits as $bit ) {
			$bit = strtolower( $bit );
			if ( '' !== $bit ) {
				$tokens[] = $bit;
			}
		}
	}

	return $tokens;
}

/**
 * Banned tokens present in $text.
 *
 * @param string $text Raw value.
 * @return list<string>
 */
function wpcy_lint_naming_hits_in( string $text ): array {
	$banned = wpcy_lint_naming_banned_tokens();
	$found  = array();
	foreach ( wpcy_lint_naming_tokens( $text ) as $token ) {
		if ( 'gg_' === $token || in_array( $token, $banned, true ) ) {
			$found[] = $token;
		}
	}
	return array_values( array_unique( $found ) );
}

/**
 * Load keep-list entries.
 *
 * @param string $path JSON path.
 * @return list<array{path: string, token: string, reason: string}>
 */
function wpcy_lint_naming_load_keep( string $path ): array {
	if ( ! is_readable( $path ) ) {
		return array();
	}
	$raw = file_get_contents( $path );
	if ( ! is_string( $raw ) ) {
		return array();
	}
	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) || ! isset( $decoded['entries'] ) || ! is_array( $decoded['entries'] ) ) {
		return array();
	}
	$out = array();
	foreach ( $decoded['entries'] as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$p = isset( $row['path'] ) && is_string( $row['path'] ) ? $row['path'] : '';
		$t = isset( $row['token'] ) && is_string( $row['token'] ) ? $row['token'] : '';
		$r = isset( $row['reason'] ) && is_string( $row['reason'] ) ? $row['reason'] : '';
		if ( '' === $p || '' === $t || '' === $r ) {
			fwrite( STDERR, "lint-naming: keep-list entry missing path/token/reason\n" );
			exit( 1 );
		}
		$out[] = array(
			'path'   => $p,
			'token'  => $t,
			'reason' => $r,
		);
	}
	return $out;
}

/**
 * Drop keep-listed findings.
 *
 * @param list<array{path: string, line: int, token: string, kind: string, value: string}> $findings Findings.
 * @param list<array{path: string, token: string, reason: string}>                         $keep     Keep-list.
 * @return list<array{path: string, line: int, token: string, kind: string, value: string}>
 */
function wpcy_lint_naming_apply_keep( array $findings, array $keep ): array {
	$open = array();
	foreach ( $findings as $row ) {
		if ( wpcy_lint_naming_is_kept( $row, $keep ) ) {
			continue;
		}
		$open[] = $row;
	}
	return $open;
}

/**
 * Whether $row is covered by the keep-list.
 *
 * @param array{path: string, line: int, token: string, kind: string, value: string} $row  Finding.
 * @param list<array{path: string, token: string, reason: string}>                    $keep Keep-list.
 */
function wpcy_lint_naming_is_kept( array $row, array $keep ): bool {
	foreach ( $keep as $entry ) {
		if ( $row['path'] !== $entry['path'] ) {
			continue;
		}
		if ( $row['token'] === $entry['token'] ) {
			return true;
		}
	}
	return false;
}

/**
 * Scan the repository plus optional extra files.
 *
 * @param string       $root  Repo root.
 * @param list<string> $extra Extra files (absolute or relative).
 * @return list<array{path: string, line: int, token: string, kind: string, value: string}>
 */
function wpcy_lint_naming_scan( string $root, array $extra ): array {
	$findings = array();
	foreach ( wpcy_lint_naming_collect_files( $root ) as $abs ) {
		$rel        = ltrim( str_replace( '\\', '/', substr( $abs, strlen( $root ) ) ), '/' );
		$findings   = array_merge( $findings, wpcy_lint_naming_scan_path( $rel ) );
		$findings   = array_merge( $findings, wpcy_lint_naming_scan_file( $abs, $rel ) );
	}
	foreach ( $extra as $path ) {
		$abs = $path;
		if ( 0 !== strpos( $path, '/' ) ) {
			$abs = $root . '/' . $path;
		}
		if ( ! is_readable( $abs ) ) {
			fwrite( STDERR, "lint-naming: cannot read {$path}\n" );
			exit( 1 );
		}
		$rel      = 0 === strpos( $abs, $root . '/' ) ? substr( $abs, strlen( $root ) + 1 ) : $abs;
		$rel      = str_replace( '\\', '/', $rel );
		$findings = array_merge( $findings, wpcy_lint_naming_scan_path( $rel ) );
		$findings = array_merge( $findings, wpcy_lint_naming_scan_file( $abs, $rel ) );
	}
	return $findings;
}

/**
 * PHP and JS files under $root, excluding vendor / node_modules / build.
 *
 * @param string $root Repo root.
 * @return list<string>
 */
function wpcy_lint_naming_collect_files( string $root ): array {
	$skip  = array( '/vendor/', '/node_modules/', '/build/', '/.git/' );
	$out   = array();
	$iter  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iter as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
			continue;
		}
		$abs  = str_replace( '\\', '/', $file->getPathname() );
		$rel  = substr( $abs, strlen( $root ) );
		$keep = true;
		foreach ( $skip as $needle ) {
			if ( false !== strpos( $rel, $needle ) ) {
				$keep = false;
				break;
			}
		}
		if ( ! $keep ) {
			continue;
		}
		$ext = strtolower( (string) $file->getExtension() );
		if ( 'php' !== $ext && 'js' !== $ext && 'json' !== $ext ) {
			continue;
		}
		if ( 'json' === $ext && 0 !== strpos( ltrim( $rel, '/' ), 'tests/fixtures/' ) ) {
			continue;
		}
		$out[] = $abs;
	}
	sort( $out );
	return $out;
}

/**
 * Path-level hits.
 *
 * @param string $rel Relative path.
 * @return list<array{path: string, line: int, token: string, kind: string, value: string}>
 */
function wpcy_lint_naming_scan_path( string $rel ): array {
	$out = array();
	foreach ( wpcy_lint_naming_hits_in( $rel ) as $token ) {
		$out[] = array(
			'path'  => $rel,
			'line'  => 0,
			'token' => $token,
			'kind'  => 'path',
			'value' => $rel,
		);
	}
	return $out;
}

/**
 * File-content hits for browser-facing identifiers.
 *
 * @param string $abs Absolute path.
 * @param string $rel Relative path.
 * @return list<array{path: string, line: int, token: string, kind: string, value: string}>
 */
function wpcy_lint_naming_scan_file( string $abs, string $rel ): array {
	$raw = file_get_contents( $abs );
	if ( ! is_string( $raw ) ) {
		return array();
	}
	$ext = strtolower( (string) pathinfo( $abs, PATHINFO_EXTENSION ) );
	if ( 'php' === $ext ) {
		return wpcy_lint_naming_scan_php( $raw, $rel );
	}
	if ( 'js' === $ext ) {
		return wpcy_lint_naming_scan_js( $raw, $rel );
	}
	if ( 'json' === $ext ) {
		return wpcy_lint_naming_scan_json( $raw, $rel );
	}
	return array();
}

/**
 * PHP: enqueue handles, style/script ids, REST routes/params, rule id/class/selector.
 *
 * @param string $raw File contents.
 * @param string $rel Relative path.
 * @return list<array{path: string, line: int, token: string, kind: string, value: string}>
 */
function wpcy_lint_naming_scan_php( string $raw, string $rel ): array {
	$out = array();
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/wp_enqueue_(?:script|style)\s*\(\s*[\'"]([^\'"]+)[\'"]/', 'enqueue-handle' ) );
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/<(?:style|script)\s+id=["\']([^"\']+)["\']/', 'dom-id' ) );
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/register_rest_route\s*\(\s*[^,]+,\s*[\'"]([^\'"]+)[\'"]/', 'rest-route' ) );
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/[\'"](?:id|class|selector|handle)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', 'rule-id' ) );
	return $out;
}

/**
 * JS: DOM id/class construction strings.
 *
 * @param string $raw File contents.
 * @param string $rel Relative path.
 * @return list<array{path: string, line: int, token: string, kind: string, value: string}>
 */
function wpcy_lint_naming_scan_js( string $raw, string $rel ): array {
	$out = array();
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/\b(?:id|className|class)\s*=\s*["\']([^"\']+)["\']/', 'dom-id' ) );
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/\b(?:id|className)\s*=\s*\{\s*[`\'"]([^`\'"]+)[`\'"]/', 'dom-id' ) );
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/getElementById\s*\(\s*[\'"]([^\'"]+)[\'"]/', 'dom-id' ) );
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/querySelector(?:All)?\s*\(\s*[\'"]([^\'"]+)[\'"]/', 'dom-id' ) );
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/classList\.add\s*\(\s*[\'"]([^\'"]+)[\'"]/', 'dom-id' ) );
	$out = array_merge( $out, wpcy_lint_naming_match_all( $raw, $rel, '/[\'"]#([A-Za-z][\w:-]*)[\'"]/', 'dom-id' ) );
	return $out;
}

/**
 * Fixture JSON: id / class / selector string values (interception targets).
 *
 * @param string $raw File contents.
 * @param string $rel Relative path.
 * @return list<array{path: string, line: int, token: string, kind: string, value: string}>
 */
function wpcy_lint_naming_scan_json( string $raw, string $rel ): array {
	return wpcy_lint_naming_match_all( $raw, $rel, '/"(?:id|class|selector)"\s*:\s*"([^"]+)"/', 'rule-id' );
}

/**
 * Collect banned tokens from every `$regex` capture.
 *
 * @param string $raw  File contents.
 * @param string $rel  Relative path.
 * @param string $regex Pattern with one capture.
 * @param string $kind  Finding kind.
 * @return list<array{path: string, line: int, token: string, kind: string, value: string}>
 */
function wpcy_lint_naming_match_all( string $raw, string $rel, string $regex, string $kind ): array {
	$out     = array();
	$matches = array();
	if ( preg_match_all( $regex, $raw, $matches, PREG_OFFSET_CAPTURE ) < 1 ) {
		return $out;
	}
	foreach ( $matches[1] as $cap ) {
		$value = $cap[0];
		$line  = wpcy_lint_naming_line_at( $raw, (int) $cap[1] );
		foreach ( wpcy_lint_naming_hits_in( $value ) as $token ) {
			$out[] = array(
				'path'  => $rel,
				'line'  => $line,
				'token' => $token,
				'kind'  => $kind,
				'value' => $value,
			);
		}
	}
	return $out;
}

/**
 * 1-based line number of byte offset $offset.
 *
 * @param string $raw    File contents.
 * @param int    $offset Byte offset.
 */
function wpcy_lint_naming_line_at( string $raw, int $offset ): int {
	$slice = substr( $raw, 0, $offset );
	return 1 + substr_count( $slice, "\n" );
}

/**
 * Built-in self-test: intentional hit (exit 1 path) plus keep-list allow.
 *
 * @param string $root Repo root.
 */
function wpcy_lint_naming_self_test( string $root ): int {
	$dir = sys_get_temp_dir() . '/wpcy-lint-naming-' . getmypid();
	if ( ! mkdir( $dir, 0700 ) && ! is_dir( $dir ) ) {
		fwrite( STDERR, "lint-naming self-test: cannot mkdir\n" );
		return 1;
	}
	$hit = $dir . '/hit.php';
	$ok  = $dir . '/kept.php';
	file_put_contents( $hit, "<?php echo '<style id=\"ad-banner\">';\n" );
	file_put_contents( $ok, "<?php echo '<style id=\"ad-banner\">';\n" );

	$findings = wpcy_lint_naming_scan_file( $hit, 'hit.php' );
	$tokens   = array();
	foreach ( $findings as $row ) {
		$tokens[] = $row['token'];
	}
	$pass_hit = in_array( 'ad', $tokens, true ) && in_array( 'banner', $tokens, true );

	$keep = array(
		array(
			'path'   => 'kept.php',
			'token'  => 'ad',
			'reason' => 'self-test allow',
		),
		array(
			'path'   => 'kept.php',
			'token'  => 'banner',
			'reason' => 'self-test allow',
		),
	);
	$kept_findings = wpcy_lint_naming_scan_file( $ok, 'kept.php' );
	$open          = wpcy_lint_naming_apply_keep( $kept_findings, $keep );

	$admin_tokens = wpcy_lint_naming_hits_in( 'wpcy-admin-root' );
	$load_tokens  = wpcy_lint_naming_hits_in( 'loading-upload' );
	$clean_false  = array() === $admin_tokens && array() === $load_tokens;

	@unlink( $hit );
	@unlink( $ok );
	@rmdir( $dir );

	if ( ! $pass_hit ) {
		fwrite( STDERR, "lint-naming self-test: expected ad+banner hit, got: " . implode( ',', $tokens ) . "\n" );
		return 1;
	}
	if ( array() !== $open ) {
		fwrite( STDERR, "lint-naming self-test: keep-list did not allow the sample\n" );
		return 1;
	}
	if ( ! $clean_false ) {
		fwrite( STDERR, "lint-naming self-test: admin/upload/loading were injured\n" );
		return 1;
	}

	fwrite( STDOUT, "lint-naming self-test: hit sample + keep-list allow + no admin/upload/loading false positive\n" );
	return 0;
}
