<?php
/**
 * WP_Error stand-in for Connectivity unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Transport-error stand-in.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code = '';

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message = '';

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		public $data = '';

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
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Error message.
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
