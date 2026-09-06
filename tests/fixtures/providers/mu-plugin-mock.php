<?php
/**
 * wp-env mu-plugin: intercept WC AM HTTP for integration-providers.sh.
 *
 * @package WenPai\ChinaYes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		if ( ! is_string( $url ) || false === strpos( $url, 'mall.weixiaoduo.com' ) ) {
			return $preempt;
		}
		if ( false === strpos( $url, 'wc-am-api' ) && false === strpos( $url, 'wc-am-action' ) ) {
			$body = isset( $args['body'] ) ? $args['body'] : array();
			$action = '';
			if ( is_array( $body ) && isset( $body['wc-am-action'] ) ) {
				$action = (string) $body['wc-am-action'];
			}
			if ( '' === $action ) {
				return $preempt;
			}
		} else {
			$action = '';
			$body   = isset( $args['body'] ) ? $args['body'] : array();
			if ( is_array( $body ) && isset( $body['wc-am-action'] ) ) {
				$action = (string) $body['wc-am-action'];
			}
			if ( '' === $action ) {
				$parts = wp_parse_url( $url );
				$query = array();
				if ( is_array( $parts ) && isset( $parts['query'] ) ) {
					parse_str( (string) $parts['query'], $query );
				}
				if ( isset( $query['wc_am_action'] ) ) {
					$action = (string) $query['wc_am_action'];
				} elseif ( isset( $query['wc-am-action'] ) ) {
					$action = (string) $query['wc-am-action'];
				}
			}
		}

		$payload = array(
			'success' => true,
			'data'    => array(),
		);
		if ( 'product_list' === $action ) {
			$payload['data']['product_list'] = array(
				array(
					'product_id' => 12,
					'slug'       => 'akismet',
					'title'      => 'Akismet',
				),
			);
		}
		if ( 'update' === $action ) {
			$payload['data']['package']     = 'https://mall.weixiaoduo.com/pkg.zip';
			$payload['data']['new_version'] = '9.9.9';
		}

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $payload ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	0,
	3
);
