<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class Base
 * 插件主服务
 * @package WenPai\ChinaYes\Service
 */
class Base {

	public function __construct() {
		/**
		 * 插件列表页中所有插件增加「参与翻译」链接
		 */
		add_filter( sprintf( '%splugin_action_links', is_multisite() ? 'network_admin_' : '' ), function ( $links, $plugin = '' ) {
			$links[] = '<a target="_blank" href="https://translate.wenpai.org/projects/plugins/' . substr( $plugin, 0, strpos( $plugin, '/' ) ) . '/">参与翻译</a>';
			$links[] = '<a target="_blank" href="https://wp-china-yes.com/plugins/' . substr( $plugin, 0, strpos( $plugin, '/' ) ) . '/">去广告</a>';

			return $links;
		}, 10, 2 );

		// 加速服务
		new Super();
		// 监控服务
		new Monitor();
		// 更新服务
		new Update();
	}
}
