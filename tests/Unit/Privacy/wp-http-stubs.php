<?php
/**
 * HTTP stubs for DataResidency unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.OneObjectStructurePerFile -- WP stand-ins grouped like other unit stubs.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO
// phpcs:disable Generic.Classes.DuplicateClassName.Found -- richer WP_Error than Connectivity stub; suites run in separate processes.

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Minimal WP_Error for privacy unit tests.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = $data;
		}

		/**
		 * Error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}

		/**
		 * Error data.
		 *
		 * @param string $code Unused.
		 * @return mixed
		 */
		public function get_error_data( $code = '' ) {
			unset( $code );
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Whether $thing is WP_Error.
	 *
	 * @param mixed $thing Candidate.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Identity translation.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! isset( $GLOBALS['wpcy_privacy_filters'] ) || ! is_array( $GLOBALS['wpcy_privacy_filters'] ) ) {
	$GLOBALS['wpcy_privacy_filters'] = array();
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Record filter registration with priority.
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) {
		$GLOBALS['wpcy_privacy_filters'][] = array(
			'hook'     => (string) $hook,
			'callback' => $callback,
			'priority' => (int) $priority,
			'accepted' => (int) $accepted,
		);
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * No-op filter removal.
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @return true
	 */
	function remove_filter( $hook, $callback, $priority = 10 ) {
		unset( $hook, $callback, $priority );
		return true;
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	/**
	 * Record the rewritten URL. Never contacts a network.
	 *
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Request args.
	 * @return array<string, mixed>
	 */
	function wp_remote_request( $url, $args = array() ) {
		if ( ! isset( $GLOBALS['wpcy_privacy_remote_urls'] ) || ! is_array( $GLOBALS['wpcy_privacy_remote_urls'] ) ) {
			$GLOBALS['wpcy_privacy_remote_urls'] = array();
		}
		if ( ! isset( $GLOBALS['wpcy_privacy_remote_args'] ) || ! is_array( $GLOBALS['wpcy_privacy_remote_args'] ) ) {
			$GLOBALS['wpcy_privacy_remote_args'] = array();
		}
		$GLOBALS['wpcy_privacy_remote_urls'][] = $url;
		$GLOBALS['wpcy_privacy_remote_args'][] = is_array( $args ) ? $args : array();
		return array(
			'response' => array(
				'code' => 200,
			),
			'body'     => '',
		);
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Parse a URL.
	 *
	 * @param string $url URL.
	 * @return array<string, mixed>|false
	 */
	function wp_parse_url( $url ) {
		return parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit stub.
	}
}
