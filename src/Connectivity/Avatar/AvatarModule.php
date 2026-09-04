<?php
/**
 * Cravatar / WeAvatar URL rewrite. Modes: cravatar_cn, cravatar_global, weavatar, off.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\Avatar;

use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id connectivity.avatar. weavatar rewrites to weavatar.com (3.x Service\Avatar).
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

		$mode = $config->get( 'connectivity.avatar', 'off' );

		return in_array( $mode, array( 'cravatar_cn', 'cravatar_global', 'weavatar' ), true );
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
		add_filter( 'get_avatar_url', array( $this, 'get_cravatar_url' ), 1 );
		add_action( 'wp_head', array( $this, 'add_avatar_preconnect' ), 1 );
	}

	/**
	 * Rewrite a gravatar URL for the configured Cravatar line.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $url Avatar URL.
	 * @return mixed
	 */
	public function get_cravatar_url( $url ) {
		if ( ! is_string( $url ) ) {
			return $url;
		}

		$mode = $this->config->get( 'connectivity.avatar', 'off' );

		switch ( $mode ) {
			case 'cravatar_cn':
				return $this->replace_avatar_url( $url, 'cn.cravatar.com' );
			case 'cravatar_global':
				return $this->replace_avatar_url( $url, 'en.cravatar.com' );
			case 'weavatar':
				return $this->replace_avatar_url( $url, 'weavatar.com' );
			default:
				return $url;
		}
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

		$mode = $this->config->get( 'connectivity.avatar', 'off' );
		if ( 'weavatar' === $mode ) {
			$avatar_defaults['gravatar_default'] = 'WeAvatar';
		} else {
			$avatar_defaults['gravatar_default'] = __( '初认头像', 'wp-china-yes' );
		}

		return $avatar_defaults;
	}

	/**
	 * Profile picture help text.
	 *
	 * @since 4.0.0
	 */
	public function set_user_profile_picture_for_cravatar(): string {
		$mode = $this->config->get( 'connectivity.avatar', 'off' );
		if ( 'weavatar' === $mode ) {
			$href = function_exists( 'esc_url' ) ? esc_url( 'https://weavatar.com' ) : 'https://weavatar.com';
			$text = function_exists( 'esc_html__' )
				? esc_html__( '您可以在 WeAvatar 修改您的资料图片', 'wp-china-yes' )
				: '您可以在 WeAvatar 修改您的资料图片';
		} else {
			$href = function_exists( 'esc_url' ) ? esc_url( 'https://cravatar.com' ) : 'https://cravatar.com';
			$text = function_exists( 'esc_html__' )
				? esc_html__( '您可以在初认头像修改您的资料图片', 'wp-china-yes' )
				: '您可以在初认头像修改您的资料图片';
		}

		return '<a href="' . $href . '" target="_blank">' . $text . '</a>';
	}

	/**
	 * Dns-prefetch and preconnect for the active Cravatar host.
	 *
	 * @since 4.0.0
	 */
	public function add_avatar_preconnect(): void {
		$mode = $this->config->get( 'connectivity.avatar', 'off' );
		$host = '';

		if ( 'cravatar_cn' === $mode ) {
			$host = 'cn.cravatar.com';
		} elseif ( 'cravatar_global' === $mode ) {
			$host = 'en.cravatar.com';
		} elseif ( 'weavatar' === $mode ) {
			$host = 'weavatar.com';
		}

		if ( '' === $host ) {
			return;
		}

		$host_attr = function_exists( 'esc_attr' ) ? esc_attr( $host ) : $host;
		$url_attr  = function_exists( 'esc_url' ) ? esc_url( 'https://' . $host ) : 'https://' . $host;

		echo '<link rel="dns-prefetch" href="//' . $host_attr . '">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above when helpers exist.
		echo '<link rel="preconnect" href="' . $url_attr . '" crossorigin>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above when helpers exist.
	}
}
