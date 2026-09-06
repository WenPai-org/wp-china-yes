<?php
/**
 * WordPress request-scene stubs for Connectivity\Scope tests.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Connectivity\ScopeHarness;

require_once __DIR__ . '/ScopeHarness.php';

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Admin flag.
	 */
	function is_admin() {
		return ScopeHarness::$is_admin;
	}
}

if ( ! function_exists( 'wp_doing_cron' ) ) {
	/**
	 * Cron flag.
	 */
	function wp_doing_cron() {
		return ScopeHarness::$cron;
	}
}

if ( ! function_exists( 'wp_doing_rest' ) ) {
	/**
	 * REST flag.
	 */
	function wp_doing_rest() {
		return ScopeHarness::$rest;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Nonce check.
	 *
	 * @param string $nonce  Nonce.
	 * @param string $action Action.
	 */
	function wp_verify_nonce( $nonce, $action ) {
		unset( $nonce, $action );
		return ScopeHarness::$nonce_ok;
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	/**
	 * Referer.
	 */
	function wp_get_referer() {
		return ScopeHarness::$referer;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Identity unslash.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Identity text sanitize for unit tests.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
