<?php
/*
 * API接口
 * Author: 孙锡源
 * Version: 2.0.0
 * Author URI:https://www.ibadboy.net/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-includes/option.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-includes/capabilities.php');

header('Content-Type:application/json; charset=utf-8');

if (current_user_can('manage_options')) {
    if (array_key_exists('get_config', $_GET)) {
        success('', get_option('wp_china_yes_options'));
    }

    if (( ! array_key_exists('api_server', $_POST) && ! array_key_exists('download_server', $_POST)) ||
        ( ! array_key_exists('community', $_POST) && ! array_key_exists('custom_api_server', $_POST) && ! array_key_exists('custom_download_server', $_POST))) {
        error('参数错误', - 1);
    }

    $options                           = array();
    $options['community']              = sanitize_text_field(trim($_POST['community']));
    $options['custom_api_server']      = sanitize_text_field(trim($_POST['custom_api_server']));
    $options['custom_download_server'] = sanitize_text_field(trim($_POST['custom_download_server']));
    $options["api_server"]             = sanitize_text_field(trim($_POST['api_server']));
    $options["download_server"]        = sanitize_text_field(trim($_POST['download_server']));
    update_option("wp_china_yes_options", $options);
    success('', $options);
}

error('登录失效或其他错误，请尝试刷新页面', - 3);

function success($message = '', $data = []) {
    echo json_encode([
        'code'    => 0,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function error($message = '', $code = - 1) {
    header('Status:500');
    echo json_encode([
        'code'    => $code,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
