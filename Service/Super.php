<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use function WenPai\ChinaYes\get_settings;
use WenPai\ChinaYes\Service\Widget;
use WenPai\ChinaYes\Service\Language;
use WenPai\ChinaYes\Service\Migration;
use WenPai\ChinaYes\Service\Fonts;
use WenPai\ChinaYes\Service\Comments;

class Super {

	private $settings;

	public function __construct() {
		$this->settings = get_settings();

		if ( is_admin() || wp_doing_cron() ) {
			if ( $this->settings['store'] != 'off' ) {
				add_filter( 'pre_http_request', [ $this, 'filter_wordpress_org' ], 100, 3 );
			}
		}

		new Widget();
		new Language();
		new Migration();
		new Fonts();
		new Comments();

		if ( ! empty( $this->settings['cravatar'] ) ) {
			add_filter( 'user_profile_picture_description', [ $this, 'set_user_profile_picture_for_cravatar' ], 1 );
			add_filter( 'avatar_defaults', [ $this, 'set_defaults_for_cravatar' ], 1 );
			add_filter( 'um_user_avatar_url_filter', [ $this, 'get_cravatar_url' ], 1 );
			add_filter( 'bp_gravatar_url', [ $this, 'get_cravatar_url' ], 1 );
			add_filter( 'get_avatar_url', [ $this, 'get_cravatar_url' ], 1 );
		}

		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			if ( ! empty( $this->settings['adblock'] ) && $this->settings['adblock'] == 'on' ) {
				add_action( 'admin_head', [ $this, 'load_adblock' ] );
			}
		}

		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			if ( ! empty( $this->settings['notice_block'] ) && $this->settings['notice_block'] == 'on' ) {
				add_action( 'admin_head', [ $this, 'load_notice_management' ] );
			}
		}

		if ( ! empty( $this->settings['plane'] ) && $this->settings['plane'] == 'on' ) {
			$this->load_plane();
		}
	}

	public function load_adblock() {
		if (empty($this->settings['adblock']) || $this->settings['adblock'] !== 'on') {
			return;
		}

		foreach ( (array) $this->settings['adblock_rule'] as $rule ) {
			if ( empty( $rule['enable'] ) || empty( $rule['selector'] ) ) {
				continue;
			}

			echo sprintf( '<style>%s { display: none !important; }</style>', esc_html( $rule['selector'] ) );
		}
	}

	public function load_notice_management() {
		echo '<style>
		.notice, .update-nag, .updated, .error, .is-dismissible {
			display: none !important;
		}
		</style>';
	}

	public function load_plane() {
		foreach ( (array) $this->settings['plane_rule'] as $rule ) {
			if ( empty( $rule['enable'] ) || empty( $rule['domain'] ) ) {
				continue;
			}

			add_filter( 'pre_http_request', function ( $preempt, $parsed_args, $url ) use ( $rule ) {
				$host = wp_parse_url( $url, PHP_URL_HOST );
				if ( strpos( $host, $rule['domain'] ) !== false ) {
					return new WP_Error( 'http_request_failed', 'Blocked by plane mode' );
				}
				return $preempt;
			}, 10, 3 );
		}
	}

	public function filter_wordpress_org( $preempt, $parsed_args, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		
		if ( ! in_array( $host, [ 'api.wordpress.org', 'downloads.wordpress.org' ] ) ) {
			return $preempt;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$query = wp_parse_url( $url, PHP_URL_QUERY );

		if ( $this->settings['store'] == 'cn' ) {
			$mirror_url = 'https://api.wenpai.net' . $path;
		} else {
			$mirror_url = 'https://api.wenpai.net' . $path;
		}

		if ( $query ) {
			$mirror_url .= '?' . $query;
		}

		$parsed_args['timeout'] = 30;
		return wp_remote_request( $mirror_url, $parsed_args );
	}

	public function set_user_profile_picture_for_cravatar( $description ) {
		return str_replace( 'Gravatar', 'Cravatar', $description );
	}

	public function set_defaults_for_cravatar( $avatar_defaults ) {
		$avatar_defaults['cravatar'] = 'Cravatar Logo (Generated)';
		return $avatar_defaults;
	}

	public function get_cravatar_url( $url ) {
		$sources = [
			'www.gravatar.com'        => 'cn.cravatar.com',
			'0.gravatar.com'          => 'cn.cravatar.com',
			'1.gravatar.com'          => 'cn.cravatar.com',
			'2.gravatar.com'          => 'cn.cravatar.com',
			'secure.gravatar.com'     => 'cn.cravatar.com',
			'cn.gravatar.com'         => 'cn.cravatar.com',
			'gravatar.com'            => 'cn.cravatar.com',
		];

		if ( $this->settings['cravatar'] == 'global' ) {
			$sources = [
				'www.gravatar.com'        => 'www.gravatar.com',
				'0.gravatar.com'          => 'www.gravatar.com',
				'1.gravatar.com'          => 'www.gravatar.com',
				'2.gravatar.com'          => 'www.gravatar.com',
				'secure.gravatar.com'     => 'www.gravatar.com',
				'cn.gravatar.com'         => 'www.gravatar.com',
				'gravatar.com'            => 'www.gravatar.com',
			];
		}

		return str_replace( array_keys( $sources ), array_values( $sources ), $url );
	}

	public function page_str_replace( $hook, $function, $args ) {
		add_action( $hook, function () use ( $function, $args ) {
			ob_start( function ( $buffer ) use ( $function, $args ) {
				return call_user_func_array( $function, array_merge( [ $args[0], $args[1], $buffer ] ) );
			} );
		}, 1 );

		add_action( 'wp_footer', function () {
			if ( ob_get_level() ) {
				ob_end_flush();
			}
		}, 999 );
	}
}
