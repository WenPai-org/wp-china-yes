<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use function WenPai\ChinaYes\get_settings;

/**
 * Class Widget
 * 小组件服务
 * @package WenPai\ChinaYes\Service
 */
class Widget {

	private $settings;

	public function __construct() {
		$this->settings = get_settings();

		/**
		 * 添加「文派茶馆」小组件
		 */
		if ( is_admin() ) {
			add_action( 'wp_dashboard_setup', [ $this, 'setup_wenpai_tea_widget' ] );
		}
	}

	/**
	 * 设置文派茶馆小组件
	 */
	public function setup_wenpai_tea_widget() {
		global $wp_meta_boxes;

		unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_primary'] );
		wp_add_dashboard_widget( 'wenpai_tea', '文派茶馆', [ $this, 'render_wenpai_tea_widget' ] );
	}

	/**
	 * 渲染文派茶馆小组件
	 */
	public function render_wenpai_tea_widget() {
		$default_rss_url = 'https://wptea.com/feed/'; 
		$custom_rss_url = $this->settings['custom_rss_url'] ?? ''; 
		$refresh_interval = $this->settings['custom_rss_refresh'] ?? 14400; 

		$rss_display_options = $this->settings['rss_display_options'] ?? ['show_date', 'show_summary', 'show_footer'];
		if (!is_array($rss_display_options)) {
			$rss_display_options = explode(',', $rss_display_options);
		}

		// 获取默认的 RSS 源内容
		$default_rss = fetch_feed($default_rss_url);
		$default_items = [];
		if (!is_wp_error($default_rss)) {
			$default_items = $default_rss->get_items(0, 5); 
		}

		$custom_items = [];
		$custom_rss = null;
		$custom_rss_latest_date = 0; 

		if (!empty($custom_rss_url)) {
			$transient_key = 'wenpai_tea_custom_rss_' . md5($custom_rss_url); 
			$cached_custom_items = get_transient($transient_key);

			if (false === $cached_custom_items) {
				$custom_rss = fetch_feed($custom_rss_url);
				if (!is_wp_error($custom_rss)) {
					$custom_items = $custom_rss->get_items(0, 2); 
					if (!empty($custom_items)) {
						$custom_rss_latest_date = $custom_items[0]->get_date('U'); 
					}

					set_transient($transient_key, $custom_items, $refresh_interval); 
				}
			} else {
				$custom_items = $cached_custom_items;
				if (!empty($custom_items)) {
					$custom_rss_latest_date = $custom_items[0]->get_date('U'); 
				}
			}
		}

		$three_days_ago = time() - (3 * 24 * 60 * 60);
		if ($custom_rss_latest_date > $three_days_ago) {
			$items = array_merge(array_slice($default_items, 0, 3), $custom_items); 
		} else {
			$items = array_slice($default_items, 0, 5);
		}

		if (is_wp_error($custom_rss)) {
			$items = array_slice($default_items, 0, 5);
		}

		echo <<<HTML
		<div class="wordpress-news hide-if-no-js">
		<div class="rss-widget">
HTML;
		foreach ($items as $item) {
			echo '<div class="rss-item">';
			echo '<a href="' . esc_url($item->get_permalink()) . '" target="_blank">' . esc_html($item->get_title()) . '</a>';
			if (in_array('show_date', $rss_display_options)) {
				echo '<span class="rss-date">' . esc_html($item->get_date('Y.m.d')) . '</span>';
			}
			if (in_array('show_summary', $rss_display_options)) {
				echo '<div class="rss-summary">' . esc_html(wp_trim_words($item->get_description(), 45, '...')) . '</div>';
			}
			echo '</div>';
		}
		
		echo <<<HTML
		</div>
		</div>
HTML;
		if (in_array('show_footer', $rss_display_options)) {
			echo <<<HTML
			<p class="community-events-footer">
			<a href="https://wenpai.org/" target="_blank">文派开源</a>
			 |
			<a href="https://wenpai.org/support" target="_blank">支持论坛</a>
			 |
			<a href="https://translate.wenpai.org/" target="_blank">翻译平台</a>
			 |
			<a href="https://wptea.com/newsletter/" target="_blank">订阅推送</a>
			</p>
HTML;
		}
		echo <<<HTML
		<style>
				#wenpai_tea .rss-widget {
	padding: 0 12px;
}
#wenpai_tea .rss-widget:last-child {
	border-bottom: none;
	padding-bottom: 8px;
}
#wenpai_tea .rss-item {
	margin-bottom: 10px;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}
#wenpai_tea .rss-item:last-child {
	border-bottom: none;
	margin-bottom: 0;
	padding-bottom: 0;
}
#wenpai_tea .rss-item a {
	text-decoration: none;
	display: block;
	margin-bottom: 5px;
}
#wenpai_tea .rss-date {
	color: #666;
	font-size: 12px;
	display: block;
	margin-bottom: 8px;
}
#wenpai_tea .rss-summary {
	color: #444;
	font-size: 13px;
	line-height: 1.5;
}
#wenpai_tea .community-events-footer {
	margin-top: 15px;
	padding-top: 15px;
	padding-bottom: 5px;
	border-top: 1px solid #eee;
	text-align: center;
}
#wenpai_tea .community-events-footer a {
	text-decoration: none;
	margin: 0 5px;
}
#wenpai_tea .community-events-footer a:hover {
	text-decoration: underline;
}
		</style>
HTML;
	}
}