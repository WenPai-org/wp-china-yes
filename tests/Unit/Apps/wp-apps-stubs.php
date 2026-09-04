<?php
/**
 * WordPress stubs for Apps unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.OneObjectStructurePerFile -- WP stand-ins grouped like other unit stubs.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO
// phpcs:disable Generic.Classes.DuplicateClassName.Found -- suite runs in its own process.

use WenPai\ChinaYes\Tests\Unit\Apps\AppsStore;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WenPai\\ChinaYes\\Tests\\Unit\\Apps\\AppsStore', false ) ) {
	require_once __DIR__ . '/AppsStore.php';
}

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
		 * JSON body.
		 *
		 * @var array<string, mixed>
		 */
		public $json = array();

		/**
		 * Merged params.
		 *
		 * @var array<string, mixed>
		 */
		public $params = array();

		/**
		 * Raw body.
		 *
		 * @var string
		 */
		public $body = '';

		/**
		 * Header value.
		 *
		 * @param string $key Header name.
		 * @return string|null
		 */
		public function get_header( $key ) {
			unset( $key );
			return null;
		}

		/**
		 * JSON params.
		 *
		 * @return array<string, mixed>
		 */
		public function get_json_params() {
			return $this->json;
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
		 * Raw body.
		 *
		 * @return string
		 */
		public function get_body() {
			return $this->body;
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

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Read a test transient.
	 *
	 * @param string $key Name.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return AppsStore::$transients[ $key ] ?? false;
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
		AppsStore::$transients[ $key ] = $value;
		return true;
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
		AppsStore::$routes[] = array(
			'namespace' => $ns,
			'route'     => $route,
			'args'      => $args,
		);
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * No-op action registration.
	 *
	 * @param string $tag      Hook.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $accepted Args.
	 * @return true
	 */
	function add_action( $tag, $callback, $priority = 10, $accepted = 1 ) {
		unset( $tag, $callback, $priority, $accepted );
		return true;
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

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Always true in this suite (capability is tested in Rest).
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		unset( $cap );
		return true;
	}
}
