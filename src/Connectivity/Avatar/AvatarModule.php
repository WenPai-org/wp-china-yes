<?php
/**
 * Cravatar URL rewrite. Modes: cravatar_cn, cravatar_global, off.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\Avatar;

use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Connectivity\Scope;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id connectivity.avatar.
 */
final class AvatarModule implements ConditionalModule {

	/**
	 * Owned dual-backup hosts for Cravatar failover in priority order.
	 *
	 * Never uses apex (`@`) or `www.` hosts.
	 *
	 * @since 4.1.0
	 *
	 * @var list<string>
	 */
	public const BACKUP_HOSTS = array(
		'avatar.wenpai.com.cn',
		'avatar.feibisi.com.cn',
	);

	/**
	 * Inline official Cravatar C-logo SVG (`#1956d4` blue background + white C + silhouette).
	 *
	 * Used as zero-network final fallback inside `get_avatar` `onerror` guard.
	 *
	 * @since 4.1.0
	 */
	public const FALLBACK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#1956d4"/><path d="M46 21a18 18 0 1 0 0 22" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round"/><circle cx="32" cy="26" r="5.5" fill="#fff"/><path d="M23 41c0-5 4-8 9-8s9 3 9 8" fill="#fff"/></svg>';

	/**
	 * Gravatar and historic mirror hosts replaced by Cravatar.
	 *
	 * Copied from Service\Avatar::replace_avatar_url $sources.
	 *
	 * @var list<string>
	 */
	private const SOURCES = array(
		'www.gravatar.com',
		'0.gravatar.com',
		'1.gravatar.com',
		'2.gravatar.com',
		's.gravatar.com',
		'secure.gravatar.com',
		'cn.gravatar.com',
		'en.gravatar.com',
		'gravatar.com',
		'sdn.geekzu.org',
		'gravatar.duoshuo.com',
		'gravatar.loli.net',
		'dn-qiniu-avatar.qbox.me',
	);

	/**
	 * Config read model.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Mirror health evaluator for automatic Cravatar backup failover.
	 *
	 * @var MirrorHealth
	 */
	private MirrorHealth $health;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Config            $config Config read model.
	 * @param MirrorHealth|null $health Optional mirror health evaluator.
	 */
	public function __construct( Config $config, ?MirrorHealth $health = null ) {
		$this->config = $config;
		$this->health = $health instanceof MirrorHealth ? $health : new MirrorHealth();
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'connectivity.avatar';
	}

	/**
	 * No module dependencies.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Avatar URLs appear in admin, frontend, and REST.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array( Environment::ADMIN, Environment::FRONTEND, Environment::REST );
	}

	/**
	 * Off, recovery_mode, or rewrite-disallowed scene: do not register.
	 *
	 * @since 4.0.0
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		if ( $config->get( 'recovery_mode' ) ) {
			return false;
		}

		if ( ! $environment->allowsUrlRewrite() ) {
			return false;
		}

		if ( ConflictDetector::is_companion_active( 'wpavatar' ) ) {
			return false;
		}

		$mode = $this->mode_for_current( $config );

		return in_array( $mode, array( 'cravatar_cn', 'cravatar_global' ), true );
	}

	/**
	 * Register 3.x avatar filters. Not called when enabled() is false.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_filter( 'user_profile_picture_description', array( $this, 'set_user_profile_picture_for_cravatar' ), 1 );
		add_filter( 'avatar_defaults', array( $this, 'set_defaults_for_cravatar' ), 1 );
		add_filter( 'um_user_avatar_url_filter', array( $this, 'get_cravatar_url' ), 1 );
		add_filter( 'bp_gravatar_url', array( $this, 'get_cravatar_url' ), 1 );
		add_filter( 'get_avatar_url', array( $this, 'filter_get_avatar_url' ), 1 );
		add_filter( 'get_avatar', array( $this, 'filter_get_avatar' ), 20 );
		add_action( 'wp_head', array( $this, 'add_avatar_preconnect' ), 1 );
	}

	/**
	 * Return the URL-encoded inline SVG data-URI of the official Cravatar C-logo.
	 *
	 * @since 4.1.0
	 */
	public static function fallback_svg_data_uri(): string {
		return 'data:image/svg+xml,' . rawurlencode( self::FALLBACK_SVG );
	}

	/**
	 * Inject a minimal multi-tier failover and inline C-logo onerror handler on Cravatar <img> tags.
	 *
	 * Retries avatar.wenpai.com.cn -> avatar.feibisi.com.cn -> inline SVG C-logo data-URI,
	 * guarded against infinite loops via dataset.caIdx. Never touches non-Cravatar images,
	 * existing onerror attributes, off mode, or active WPAvatar handoff.
	 *
	 * @since 4.1.0
	 *
	 * @param mixed $avatar Rendered avatar HTML markup.
	 * @return mixed
	 */
	public function filter_get_avatar( $avatar ) {
		if ( ! is_string( $avatar ) || '' === $avatar ) {
			return $avatar;
		}
		if ( ConflictDetector::is_companion_active( 'wpavatar' ) ) {
			return $avatar;
		}

		$mode = $this->mode_for_current( $this->config );
		if ( ! in_array( $mode, array( 'cravatar_cn', 'cravatar_global' ), true ) ) {
			return $avatar;
		}

		$result = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( array $matches ): string {
				$tag = $matches[0];
				if ( 1 === preg_match( '/\bonerror\s*=/i', $tag ) ) {
					return $tag;
				}
				if ( 1 !== preg_match( '/\bsrc\s*=\s*(["\'])(https?:\/\/((?:(?:cn|en)\.)?cravatar\.com|avatar\.(?:wenpai|feibisi)\.com\.cn)\/avatar\/[^"\']*)\1/i', $tag, $src_match ) ) {
					return $tag;
				}

				$current_host = strtolower( $src_match[3] );
				$next_hosts   = $this->onerror_backup_hosts_for( $current_host );
				$handler      = $this->build_onerror_handler( $next_hosts );
				$escaped      = function_exists( 'esc_attr' ) ? esc_attr( $handler ) : htmlspecialchars( $handler, ENT_QUOTES, 'UTF-8' );

				if ( '/>' === substr( $tag, -2 ) ) {
					return rtrim( substr( $tag, 0, -2 ) ) . ' onerror="' . $escaped . '" />';
				}

				return rtrim( substr( $tag, 0, -1 ) ) . ' onerror="' . $escaped . '">';
			},
			$avatar
		);

		return is_string( $result ) ? $result : $avatar;
	}

	/**
	 * Rewrite a gravatar URL without incrementing avatar_rewrites_*.
	 *
	 * Used by Ultimate Member / BuddyPress filters so the same URL is not
	 * counted twice when get_avatar_url also runs.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $url Avatar URL.
	 * @return mixed
	 */
	public function get_cravatar_url( $url ) {
		return $this->rewrite_avatar_url( $url, false );
	}

	/**
	 * Rewrite and count a get_avatar_url filter call.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $url Avatar URL.
	 * @return mixed
	 */
	public function filter_get_avatar_url( $url ) {
		return $this->rewrite_avatar_url( $url, true );
	}

	/**
	 * Rewrite a gravatar URL for the configured Cravatar line.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $url   Avatar URL.
	 * @param bool  $count Whether to increment avatar_rewrites_*.
	 * @return mixed
	 */
	private function rewrite_avatar_url( $url, bool $count ) {
		if ( ! is_string( $url ) ) {
			return $url;
		}

		$mode = $this->mode_for_current( $this->config );
		$host = $this->resolve_active_host( $mode );
		if ( '' === $host ) {
			return $url;
		}

		$rewritten = $this->replace_avatar_url( $url, $host );

		if ( $count && $rewritten !== $url ) {
			$this->count_rewrite();
		}

		return $rewritten;
	}

	/**
	 * Count one get_avatar_url rewrite by current scope.
	 *
	 * @since 4.0.0
	 */
	private function count_rewrite(): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}
		$counter = Scope::ADMIN === Scope::current()
			? 'avatar_rewrites_admin'
			: 'avatar_rewrites_frontend';
		do_action( 'wpcy_stats_increment', $counter, 1 );
	}

	/**
	 * Replace known gravatar hosts with $domain.
	 *
	 * @since 4.0.0
	 *
	 * @param string $url    Original URL.
	 * @param string $domain Target host.
	 */
	public function replace_avatar_url( string $url, string $domain ): string {
		return str_replace( self::SOURCES, $domain, $url );
	}

	/**
	 * Default avatar name in Discussion settings.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $avatar_defaults Defaults map.
	 * @return mixed
	 */
	public function set_defaults_for_cravatar( $avatar_defaults ) {
		if ( ! is_array( $avatar_defaults ) ) {
			return $avatar_defaults;
		}

		$avatar_defaults['gravatar_default'] = __( '初认头像', 'wp-china-yes' );

		return $avatar_defaults;
	}

	/**
	 * Profile picture help text.
	 *
	 * @since 4.0.0
	 */
	public function set_user_profile_picture_for_cravatar(): string {
		$href = function_exists( 'esc_url' ) ? esc_url( 'https://cravatar.com' ) : 'https://cravatar.com';
		$text = function_exists( 'esc_html__' )
			? esc_html__( '您可以在初认头像修改您的资料图片', 'wp-china-yes' )
			: '您可以在初认头像修改您的资料图片';

		return '<a href="' . $href . '" target="_blank">' . $text . '</a>';
	}

	/**
	 * Dns-prefetch and preconnect for the active Cravatar host.
	 *
	 * @since 4.0.0
	 */
	public function add_avatar_preconnect(): void {
		$mode = $this->mode_for_current( $this->config );
		$host = $this->resolve_active_host( $mode );

		if ( '' === $host ) {
			return;
		}

		$host_attr = function_exists( 'esc_attr' ) ? esc_attr( $host ) : $host;
		$url_attr  = function_exists( 'esc_url' ) ? esc_url( 'https://' . $host ) : 'https://' . $host;

		echo '<link rel="dns-prefetch" href="//' . $host_attr . '">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above when helpers exist.
		echo '<link rel="preconnect" href="' . $url_attr . '" crossorigin>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above when helpers exist.
	}

	/**
	 * Resolve the active healthy Cravatar host for $mode, failing over to owned backups if unhealthy.
	 *
	 * @param string $mode Avatar mode (`cravatar_cn`, `cravatar_global`, `off`).
	 */
	private function resolve_active_host( string $mode ): string {
		if ( 'cravatar_cn' === $mode ) {
			$primary = 'cn.cravatar.com';
		} elseif ( 'cravatar_global' === $mode ) {
			$primary = 'en.cravatar.com';
		} else {
			return '';
		}

		$candidates = array_merge( array( $primary ), self::BACKUP_HOSTS );
		foreach ( $candidates as $candidate ) {
			if ( $this->health->is_healthy( $candidate ) ) {
				return $candidate;
			}
		}

		return $primary;
	}

	/**
	 * Determine remaining owned backup hosts to try on browser image error for $current_host.
	 *
	 * @param string $current_host Lowercase host currently present in the img src attribute.
	 * @return list<string>
	 */
	private function onerror_backup_hosts_for( string $current_host ): array {
		$pos = array_search( $current_host, self::BACKUP_HOSTS, true );
		if ( false !== $pos ) {
			return array_slice( self::BACKUP_HOSTS, $pos + 1 );
		}
		return self::BACKUP_HOSTS;
	}

	/**
	 * Build a minimal, double-quote-free, loop-guarded inline JS onerror handler.
	 *
	 * @param string[] $backup_hosts Ordered owned backup hosts to try before inline SVG C-logo.
	 */
	private function build_onerror_handler( array $backup_hosts ): string {
		$js_hosts = array();
		foreach ( $backup_hosts as $host ) {
			$js_hosts[] = "'" . $host . "'";
		}
		$hosts_literal = '[' . implode( ',', $js_hosts ) . ']';
		$svg_uri       = self::fallback_svg_data_uri();

		return "(function(i,h,f){var n=+(i.dataset.caIdx||0);i.dataset.caIdx=n+1;i.srcset='';if(h[n]){i.src=i.src.replace(/\\/\\/[^\\/]+/,'//'+h[n])}else{i.onerror=null;i.src=f}})(this," . $hosts_literal . ",'" . $svg_uri . "')";
	}

	/**
	 * Site-wide avatar mode. Split admin/frontend keys are treated as the same value.
	 *
	 * @param Config $config Config read model.
	 */
	private function mode_for_current( Config $config ): string {
		$avatar = $config->get( 'connectivity.avatar', 'off' );
		if ( is_string( $avatar ) && '' !== $avatar ) {
			return $avatar;
		}
		if ( is_array( $avatar ) ) {
			foreach ( array( 'admin', 'frontend' ) as $side ) {
				if ( isset( $avatar[ $side ] ) && is_string( $avatar[ $side ] ) && '' !== $avatar[ $side ] ) {
					return $avatar[ $side ];
				}
			}
		}

		return 'off';
	}
}
