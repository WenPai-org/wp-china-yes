<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use const WenPai\ChinaYes\PLUGIN_FILE;

/**
 * Class Update
 * 插件更新服务
 * @package WenPai\ChinaYes\Service
 */
class Update {

	public function __construct() {
		PucFactory::buildUpdateChecker(
			'https://api.wenpai.org/china-yes/version-check',
			PLUGIN_FILE,
			'wp-china-yes'
		);
	}
}