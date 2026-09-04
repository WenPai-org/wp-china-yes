<?php
/**
 * WooCommerce stand-ins for ReportWooCommerceFieldsTest. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

// phpcs:disable Generic.Files.OneObjectStructurePerFile -- test fakes grouped by purpose.

if ( ! defined( 'DB_NAME' ) ) {
	define( 'DB_NAME', 'wp_test' );
}
if ( ! defined( 'WC_VERSION' ) ) {
	define( 'WC_VERSION', '9.9.0' );
}

if ( ! class_exists( 'WooCommerce', false ) ) {
	/**
	 * Marker class so Report::collect() emits the woocommerce block.
	 */
	class WooCommerce {
	}
}

if ( ! class_exists( 'Wpcy_Telemetry_Fake_Wpdb', false ) ) {
	/**
	 * Database stand-in for aggregate queries.
	 */
	class Wpcy_Telemetry_Fake_Wpdb {
		/**
		 * Table prefix.
		 *
		 * @var string
		 */
		public $prefix = 'wp_';

		/**
		 * Posts table.
		 *
		 * @var string
		 */
		public $posts = 'wp_posts';

		/**
		 * Term relationships table.
		 *
		 * @var string
		 */
		public $term_relationships = 'wp_term_relationships';

		/**
		 * Term taxonomy table.
		 *
		 * @var string
		 */
		public $term_taxonomy = 'wp_term_taxonomy';

		/**
		 * Terms table.
		 *
		 * @var string
		 */
		public $terms = 'wp_terms';

		/**
		 * Prepare a statement. Interpolates %s for the fake.
		 *
		 * @param string $sql     SQL.
		 * @param mixed  ...$args Args.
		 * @return string
		 */
		public function prepare( $sql, ...$args ) {
			foreach ( $args as $arg ) {
				$sql = preg_replace( '/%s/', "'" . $arg . "'", $sql, 1 );
			}
			return $sql;
		}

		/**
		 * Scalar query.
		 *
		 * @param string $sql SQL.
		 * @return mixed
		 */
		public function get_var( $sql ) {
			if ( false !== strpos( $sql, 'SELECT VERSION()' ) ) {
				return '8.0.36';
			}
			if ( false !== strpos( $sql, 'information_schema' ) ) {
				return '2';
			}
			if ( false !== strpos( $sql, 'SHOW TABLES LIKE' ) ) {
				return 'wp_woocommerce_shipping_zones';
			}
			if ( false !== strpos( $sql, 'woocommerce_shipping_zones' ) ) {
				return '3';
			}
			return null;
		}

		/**
		 * Row query.
		 *
		 * @param string $sql SQL.
		 * @return list<object>
		 */
		public function get_results( $sql ) {
			if ( false !== strpos( $sql, 'product_type' ) ) {
				return array(
					(object) array(
						'slug' => 'simple',
						'cnt'  => 12,
					),
					(object) array(
						'slug' => 'variable',
						'cnt'  => 3,
					),
				);
			}
			if ( false !== strpos( $sql, 'shop_order' ) ) {
				return array(
					(object) array(
						'status' => 'wc-completed',
						'cnt'    => 40,
					),
					(object) array(
						'status' => 'wc-processing',
						'cnt'    => 2,
					),
				);
			}
			return array();
		}
	}
}

if ( ! class_exists( 'Wpcy_Telemetry_Fake_Gateway', false ) ) {
	/**
	 * Payment or shipping method.
	 */
	class Wpcy_Telemetry_Fake_Gateway {
		/**
		 * Id.
		 *
		 * @var string
		 */
		public $id;

		/**
		 * Enabled flag.
		 *
		 * @var string
		 */
		public $enabled;

		/**
		 * Create a method.
		 *
		 * @param string $id      Id.
		 * @param string $enabled yes|no.
		 */
		public function __construct( $id, $enabled ) {
			$this->id      = $id;
			$this->enabled = $enabled;
		}
	}
}

if ( ! class_exists( 'Wpcy_Telemetry_Fake_Gateways', false ) ) {
	/**
	 * Gateway list.
	 */
	class Wpcy_Telemetry_Fake_Gateways {
		/**
		 * Installed gateways.
		 *
		 * @return list<Wpcy_Telemetry_Fake_Gateway>
		 */
		public function payment_gateways() {
			return array(
				new Wpcy_Telemetry_Fake_Gateway( 'alipay', 'yes' ),
				new Wpcy_Telemetry_Fake_Gateway( 'cod', 'no' ),
			);
		}
	}
}

if ( ! class_exists( 'Wpcy_Telemetry_Fake_Shipping', false ) ) {
	/**
	 * Shipping methods.
	 */
	class Wpcy_Telemetry_Fake_Shipping {
		/**
		 * Installed methods.
		 *
		 * @return list<Wpcy_Telemetry_Fake_Gateway>
		 */
		public function get_shipping_methods() {
			return array(
				new Wpcy_Telemetry_Fake_Gateway( 'flat_rate', 'yes' ),
			);
		}
	}
}

if ( ! class_exists( 'Wpcy_Telemetry_Fake_WC', false ) ) {
	/**
	 * WC() return value.
	 */
	class Wpcy_Telemetry_Fake_WC {
		/**
		 * Gateways container.
		 *
		 * @var Wpcy_Telemetry_Fake_Gateways
		 */
		public $payment_gateways;

		/**
		 * Shipping container.
		 *
		 * @var Wpcy_Telemetry_Fake_Shipping
		 */
		public $shipping;

		/**
		 * Wire containers.
		 */
		public function __construct() {
			$this->payment_gateways = new Wpcy_Telemetry_Fake_Gateways();
			$this->shipping         = new Wpcy_Telemetry_Fake_Shipping();
		}

		/**
		 * Plugin path that does not exist.
		 *
		 * @return string
		 */
		public function plugin_path() {
			return '/nonexistent/woocommerce';
		}
	}
}
