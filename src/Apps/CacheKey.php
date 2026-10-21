<?php
/**
 * Transient names for caches of signature-verified documents.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Apps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `<base>_<VERSION>_<trust fingerprint>`.
 *
 * The apps index and the element hide, notice control and announcements feeds
 * cache documents only after Ed25519 verification. Binding the transient name
 * to the verifier's trusted keys means a cache written under different keys
 * (an older release, or before a key change) is never read again; it expires
 * on its own TTL. VERSION invalidates every such cache when the cache format
 * or acceptance rules change without a key change.
 */
final class CacheKey {

	/**
	 * Cache format version, part of every name.
	 *
	 * @since 4.0.0
	 */
	public const VERSION = 'v2';

	/**
	 * Transient name for $base under the keys trusted by $verifier.
	 *
	 * @since 4.0.0
	 *
	 * @param string           $base     Unversioned name, e.g. Index::TRANSIENT_KEY.
	 * @param ManifestVerifier $verifier Verifier whose trusted keys guard the cache.
	 */
	public static function for( string $base, ManifestVerifier $verifier ): string {
		return $base . '_' . self::VERSION . '_' . $verifier->trust_fingerprint();
	}
}
