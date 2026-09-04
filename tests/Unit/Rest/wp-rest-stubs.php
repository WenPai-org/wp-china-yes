<?php
/**
 * WordPress REST and admin stubs for Rest unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.OneObjectStructurePerFile -- WP stand-ins grouped like other unit stubs.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO
// phpcs:disable Generic.Classes.DuplicateClassName.Found -- richer WP_Error than Connectivity stub; suites run in separate processes.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- stub HTML for unit tests.

use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WenPai\\ChinaYes\\Tests\\Unit\\Rest\\RestStore', false ) ) {
	require_once __DIR__ . '/RestStore.php';
}

require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * REST error stand-in with code / message / data.
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
		 * HTTP method.
		 *
		 * @var string
		 */
		public $method = 'GET';

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
		 * Headers, lowercase names.
		 *
		 * @var array<string, string>
		 */
		public $headers = array();

		/**
		 * Route path.
		 *
		 * @var string
		 */
		public $route = '';

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
		 * JSON params.
		 *
		 * @return array<string, mixed>
		 */
		public function get_json_params() {
			return $this->json;
		}

		/**
		 * All params.
		 *
		 * @return array<string, mixed>
		 */
		public function get_params() {
			return $this->params;
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
			return $this->route;
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

		/**
		 * Headers.
		 *
		 * @return array<string, string>
		 */
		public function get_headers() {
			return $this->headers;
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

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Capability check from RestStore.
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		return ! empty( RestStore::$caps[ (string) $cap ] );
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
		return ! empty( RestStore::$nonces[ (string) $nonce ] ) ? 1 : false;
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
		RestStore::$routes[] = array(
			'namespace' => $ns,
			'route'     => $route,
			'args'      => $args,
		);
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
		RestStore::$hooks[ $tag ][] = $callback;
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

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * Record a hidden submenu page.
	 *
	 * @param string|null $parent_slug Parent slug. Null hides the item.
	 * @param string      $page_title  Title.
	 * @param string      $menu_title  Menu title.
	 * @param string      $capability  Capability.
	 * @param string      $menu_slug   Slug.
	 * @param callable    $callback    Render callback.
	 * @return string
	 */
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
		RestStore::$pages[] = array(
			'parent'     => $parent_slug,
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'menu_slug'  => $menu_slug,
			'callback'   => $callback,
		);
		return $menu_slug;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Print a nonce hidden field.
	 *
	 * @param string $action   Action.
	 * @param string $name     Field name.
	 * @param bool   $referer  Unused.
	 * @param bool   $do_echo  Echo.
	 * @return string
	 */
	function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $do_echo = true ) {
		unset( $referer );
		$html = '<input type="hidden" name="' . htmlspecialchars( (string) $name, ENT_QUOTES, 'UTF-8' ) . '" value="' . htmlspecialchars( (string) $action, ENT_QUOTES, 'UTF-8' ) . '" />';
		if ( $do_echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stub HTML for unit tests.
		}
		return $html;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Verify an admin form nonce or wp_die().
	 *
	 * @param string $action Action.
	 * @param string $query  Field name.
	 * @return true
	 */
	function check_admin_referer( $action, $query = '_wpnonce' ) {
		$token = '';
		if ( isset( $_POST[ $query ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this is the verifier.
			$token = (string) $_POST[ $query ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- stub.
		}
		if ( isset( RestStore::$admin_nonces[ (string) $action ] ) && RestStore::$admin_nonces[ (string) $action ] === $token ) {
			return true;
		}
		wp_die( 'Invalid nonce.', 403 );
		return false;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Record a die. Optionally throw.
	 *
	 * @param string $message Message.
	 * @param int    $status  Status.
	 * @return void
	 * @throws RuntimeException When RestStore::$die_throws is true.
	 */
	function wp_die( $message = '', $status = 500 ) {
		RestStore::$die        = (string) $message;
		RestStore::$die_status = (int) $status;
		if ( RestStore::$die_throws ) {
			throw new RuntimeException( (string) $message, (int) $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
		}
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

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Lowercase slug.
	 *
	 * @param mixed $key Raw key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
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

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Identity escaped translation.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
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

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escape attribute.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escape URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Admin URL stand-in.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'http://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	/**
	 * Record a redirect and throw so unit tests do not hit exit.
	 *
	 * @param string $location Redirect URL.
	 * @param int    $status   Unused.
	 * @param string $x_by     Unused.
	 * @return void
	 * @throws RuntimeException Always, with the location as the message.
	 */
	function wp_safe_redirect( $location, $status = 302, $x_by = 'WordPress' ) {
		unset( $status, $x_by );
		RestStore::$redirect = (string) $location;
		throw new RuntimeException( (string) $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test control flow, not HTML.
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
		return RestStore::$transients[ $key ] ?? false;
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
		RestStore::$transients[ $key ] = $value;
		return true;
	}
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
