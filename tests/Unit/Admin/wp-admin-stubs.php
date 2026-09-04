<?php
/**
 * WordPress stubs for Admin unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.OneObjectStructurePerFile -- WP stand-ins grouped like other unit stubs.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO
// phpcs:disable Generic.Classes.DuplicateClassName.Found -- suite runs in its own process.

use WenPai\ChinaYes\Tests\Unit\Admin\AdminStore;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WenPai\\ChinaYes\\Tests\\Unit\\Admin\\AdminStore', false ) ) {
	require_once __DIR__ . '/AdminStore.php';
}

require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * REST error stand-in.
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

if ( ! class_exists( 'WP_REST_Request', false ) ) {
	/**
	 * Minimal REST request.
	 */
	class WP_REST_Request {

		/**
		 * Params.
		 *
		 * @var array<string, mixed>
		 */
		public $params = array();

		/**
		 * Headers, lowercase names.
		 *
		 * @var array<string, string>
		 */
		public $headers = array();

		/**
		 * Header value.
		 *
		 * @param string $key Header name.
		 * @return string|null
		 */
		public function get_header( $key ) {
			$name = strtolower( (string) $key );
			return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : null;
		}

		/**
		 * One param.
		 *
		 * @param string $key Param name.
		 * @return mixed
		 */
		public function get_param( $key ) {
			return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
		}

		/**
		 * Route.
		 *
		 * @return string
		 */
		public function get_route() {
			return '';
		}
	}
}

if ( ! class_exists( 'WP_REST_Response', false ) ) {
	/**
	 * Minimal REST response.
	 */
	class WP_REST_Response {

		/**
		 * Body.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * HTTP status.
		 *
		 * @var int
		 */
		public $status;

		/**
		 * Headers.
		 *
		 * @var array<string, string>
		 */
		public $headers = array();

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Body.
		 * @param int   $status Status.
		 */
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = (int) $status;
		}

		/**
		 * Set a header.
		 *
		 * @param string $key   Name.
		 * @param string $value Value.
		 * @return void
		 */
		public function header( $key, $value ) {
			$this->headers[ $key ] = (string) $value;
		}

		/**
		 * Body.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Status.
		 *
		 * @return int
		 */
		public function get_status() {
			return $this->status;
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

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Read a test transient.
	 *
	 * @param string $key Name.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return AdminStore::$transients[ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Write a test transient.
	 *
	 * @param string $key   Name.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Unused.
	 * @return true
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		unset( $ttl );
		AdminStore::$transients[ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record an action.
	 *
	 * @param string $tag      Hook.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $accepted Args.
	 * @return true
	 */
	function add_action( $tag, $callback, $priority = 10, $accepted = 1 ) {
		unset( $priority, $accepted );
		AdminStore::$hooks[ $tag ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Record a filter.
	 *
	 * @param string $tag      Hook.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $accepted Args.
	 * @return true
	 */
	function add_filter( $tag, $callback, $priority = 10, $accepted = 1 ) {
		return add_action( $tag, $callback, $priority, $accepted );
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	/**
	 * Record a REST route.
	 *
	 * @param string               $ns    Namespace.
	 * @param string               $route Route.
	 * @param array<string, mixed> $args  Args.
	 * @return true
	 */
	function register_rest_route( $ns, $route, $args ) {
		AdminStore::$routes[] = array(
			'namespace' => $ns,
			'route'     => $route,
			'args'      => $args,
		);
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Capability check from AdminStore.
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		return ! empty( AdminStore::$caps[ (string) $cap ] );
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * REST nonce check.
	 *
	 * @param string $nonce  Token.
	 * @param string $action Action.
	 * @return int|false
	 */
	function wp_verify_nonce( $nonce, $action ) {
		if ( 'wp_rest' !== $action ) {
			return false;
		}
		return ! empty( AdminStore::$nonces[ (string) $nonce ] ) ? 1 : false;
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

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escape HTML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/**
	 * Deterministic request id.
	 *
	 * @return string
	 */
	function wp_generate_uuid4() {
		return 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * No scheduled events in unit tests.
	 *
	 * @param string $hook Hook.
	 * @return false
	 */
	function wp_next_scheduled( $hook ) {
		unset( $hook );
		return false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * Record a schedule.
	 *
	 * @param int    $timestamp Timestamp.
	 * @param string $recurrence Recurrence.
	 * @param string $hook       Hook.
	 * @return true
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook ) {
		unset( $timestamp, $recurrence, $hook );
		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode.
	 *
	 * @param mixed $data Data.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- this is the wp_json_encode stub.
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	/**
	 * HTML class token.
	 *
	 * @param string $css_class Class.
	 * @return string
	 */
	function sanitize_html_class( $css_class ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $css_class );
	}
}
