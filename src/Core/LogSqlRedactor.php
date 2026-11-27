<?php
/**
 * Quoted-literal redaction for SQL snippets in log lines.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

/**
 * Masks quoted string literals inside SQL statements in linear time.
 */
final class LogSqlRedactor {

	/**
	 * Quoted literals in an SQL statement become '***'.
	 *
	 * @param string $text Text.
	 */
	public function redact( string $text ): string {
		if ( ! preg_match( '/\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b.*\b(?:FROM|INTO|SET|WHERE|VALUES)\b/is', $text ) ) {
			return $text;
		}

		return $this->mask_quoted( $text );
	}

	/**
	 * Quoted literals ('...' or "...") become '***'.
	 *
	 * Two readings of the quotes are masked together (the union of their
	 * byte ranges):
	 * - plain: each ' or " closes at the next one of its kind;
	 * - contractions: a ' between a letter and an English contraction ending
	 *   (s, t, re, ve, ll, d, m, then a word end: doesn't, it's) is an
	 *   apostrophe, so 'it's here' is one literal.
	 * Then a quote left outside every masked range (one that never pairs up)
	 * masks the rest of the text from the start of its word. More masking,
	 * no leak: the text after doesn't may be masked as well.
	 *
	 * Linear: one match per literal in each reading, then one merge pass.
	 *
	 * @param string $text Text.
	 */
	private function mask_quoted( string $text ): string {
		$apostrophe = '(?<=[A-Za-z])\'(?=(?i:s|t|re|ve|ll|d|m)\b)';
		$plain      = '\'(?:[^\'\\\\]++|\\\\.)*+\'|\'';
		$contracted = '(?!' . $apostrophe . ')\'(?:[^\'\\\\]++|\\\\.|' . $apostrophe . ')*+\'|(?!' . $apostrophe . ')\'';
		$first      = $this->quoted_spans( $text, array( $plain ) );
		$second     = $this->quoted_spans( $text, array( $contracted, $plain ) );

		// Both lists run left to right: merge them by start in one pass.
		$spans = array();
		for ( $i = 0, $j = 0, $n1 = count( $first ), $n2 = count( $second ); $i < $n1 || $j < $n2; ) {
			$spans[] = ( $j >= $n2 || ( $i < $n1 && $first[ $i ][0] <= $second[ $j ][0] ) ) ? $first[ $i++ ] : $second[ $j++ ];
		}

		$out = '';
		$end = 0;
		foreach ( $spans as $span ) {
			if ( $span[0] < $end ) {
				// Overlaps the masked range before it: extend that range.
				$end = max( $end, $span[1] );
				continue;
			}
			$gap = substr( $text, $end, $span[0] - $end );
			if ( false !== strpbrk( $gap, '\'"' ) ) {
				break;
			}
			$out .= ( 0 === $end ? '' : "'***'" ) . $gap;
			$end  = $span[1];
		}
		if ( 0 !== $end ) {
			$out .= "'***'";
		}

		$rest  = substr( $text, $end );
		$quote = strcspn( $rest, '\'"' );
		if ( $quote < strlen( $rest ) ) {
			// A quote that never pairs up: mask from the start of its word
			// ("hunter2pass'" after 'O'Brien' AND p=') to the end.
			$word = $quote - strcspn( strrev( substr( $rest, 0, $quote ) ), " \t\n\r\v\f" );
			return $out . substr( $rest, 0, $word ) . "'***";
		}

		return $out . $rest;
	}

	/**
	 * Byte ranges [start, end) of quoted literals, left to right.
	 *
	 * One match per literal. An unclosed " masks from the first " (word start)
	 * to the end. An unclosed ' advances the reading in $single; after the last
	 * reading no later ' can close.
	 *
	 * @param string   $text   Text.
	 * @param string[] $single Patterns for a ' literal, each ending in |' (an unclosed quote).
	 * @return array<int, array{0: int, 1: int}>
	 */
	private function quoted_spans( string $text, array $single ): array {
		// Only \\ and \" are escapes inside "..."; \/ is not (SQL, not JSON), so a
		// \/ inside a "..." keeps the quote open and an unclosed " can still hide
		// secrets that a false close would leave in clear (review-169 samples).
		$double = '"(?:[^"\\\\]++|\\\\["\\\\])*+"|"';
		$state  = 0;
		$offset = 0;
		$spans  = array();
		// Prefer while (true): unclosed " breaks without nulling $double, which
		// would make `null !== $double` always-true for phpstan.
		while ( true ) {
			$pattern = '/' . implode( '|', array_filter( array( $double, $single[ $state ] ?? null ) ) ) . '/s';
			if ( 1 !== preg_match( $pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
				break;
			}
			$found = $m[0][0];
			if ( 1 === strlen( $found ) ) {
				if ( '"' === $found ) {
					// Unclosed ": mask from the first " of the text (word start) to the end.
					$first = strpos( $text, '"' );
					if ( false === $first ) {
						$first = $m[0][1];
					}
					$word = $first - strcspn( strrev( substr( $text, 0, $first ) ), " \t\n\r\v\f" );
					// Drop earlier closed spans: an unclosed " wins over them
					// (more masking, no leak of text between the first " and here).
					$spans = array( array( $word, strlen( $text ) ) );
					break;
				}
				++$state;
				continue;
			}
			$spans[] = array( $m[0][1], $m[0][1] + strlen( $found ) );
			$offset  = $m[0][1] + strlen( $found );
		}

		return $spans;
	}
}
