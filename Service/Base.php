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
		// 加速服务
		new Super();
		// 监控服务
		new Monitor();
		// 更新服务
		new Update();
		if ( is_admin() ) {
			// 设置服务
			new Setting();
		}
	}
}
