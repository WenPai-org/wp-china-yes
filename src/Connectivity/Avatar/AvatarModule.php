<?php
/**
 * Cravatar URL rewrite. Modes: cravatar_cn, cravatar_global, off.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\Avatar;

use WenPai\ChinaYes\Connectivity\Scope;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id connectivity.avatar.
 */
final class AvatarModule implements ConditionalModule {

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
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Config $config Config read model.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
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
		add_action( 'wp_head', array( $this, 'add_avatar_preconnect' ), 1 );
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

		switch ( $mode ) {
			case 'cravatar_cn':
				$rewritten = $this->replace_avatar_url( $url, 'cn.cravatar.com' );
				break;
			case 'cravatar_global':
				$rewritten = $this->replace_avatar_url( $url, 'en.cravatar.com' );
				break;
			default:
				return $url;
		}

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
		$host = '';

		if ( 'cravatar_cn' === $mode ) {
			$host = 'cn.cravatar.com';
		} elseif ( 'cravatar_global' === $mode ) {
			$host = 'en.cravatar.com';
		}

		if ( '' === $host ) {
			return;
		}

		$host_attr = function_exists( 'esc_attr' ) ? esc_attr( $host ) : $host;
		$url_attr  = function_exists( 'esc_url' ) ? esc_url( 'https://' . $host ) : 'https://' . $host;

		echo '<link rel="dns-prefetch" href="//' . $host_attr . '">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above when helpers exist.
		echo '<link rel="preconnect" href="' . $url_attr . '" crossorigin>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above when helpers exist.
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
