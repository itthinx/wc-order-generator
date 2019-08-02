<?php
/**
 * class-wc-order-generator.php
 *
 * Copyright (c) 2017 "kento" Karim Rahimpur www.itthinx.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author itthinx
 * @package wc-order-generator
 * @since 1.0.0
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order Generator.
 */
class WC_Order_Generator {

	const MAX_PER_RUN     = 100;
	const DEFAULT_PER_RUN = 10;
	const DEFAULT_LIMIT   = 100;
	const DEFAULT_PERIOD  = 3 * 365;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// register_activation_hook( WCORDERGEN_PLUGIN_FILE, array( __CLASS__,'activate' ) );
		// register_deactivation_hook( WCORDERGEN_PLUGIN_FILE,  array( __CLASS__,'deactivate' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		if ( is_admin() ) {
			add_filter( 'plugin_action_links_'. plugin_basename( WCORDERGEN_PLUGIN_FILE ), array( __CLASS__, 'admin_settings_link' ) );
		}
		add_action( 'init', array( __CLASS__, 'wp_init' ) );
	}

	/**
	 * Does nothing for now.
	 */
	public static function activate() {
	}

	/**
	 * Does nothing for now.
	 */
	public static function deactivate() {
	}

	/**
	 * Add the Generator menu item.
	 */
	public static function admin_menu() {
		if ( self::woocommerce_is_active() ) {
			$page = add_submenu_page(
				'woocommerce',
				'Order Generator',
				'Order Generator',
				'manage_woocommerce',
				'order-generator',
				array( __CLASS__, 'generator' )
			);
			add_action( 'load-' . $page, array( __CLASS__, 'load' ) );
		}
	}
	
	public static function load() {
		wp_register_script( 'order-generator', WCORDERGEN_PLUGIN_URL . '/js/order-generator.js', array( 'jquery' ), WCORDERGEN_PLUGIN_VERSION, true );
		wp_register_style( 'order-generator', WCORDERGEN_PLUGIN_URL . '/css/order-generator.css', array(), WCORDERGEN_PLUGIN_VERSION );
	}

	/**
	 * Adds plugin links.
	 *
	 * @param array $links
	 * @param array $links with additional links
	 */
	public static function admin_settings_link( $links ) {
		if ( self::woocommerce_is_active() ) {
			$links[] = '<a href="' . esc_url( get_admin_url( null, 'admin.php?page=order-generator' ) ) . '">' . esc_html( __( 'Order Generator', WCORDERGEN_PLUGIN_DOMAIN ) ) . '</a>';
		}
		return $links;
	}

	/**
	 * AJAX request handler.
	 * 
	 * If a valid order generator request is recognized,
	 * it runs a generation cycle and then produces the JSON-encoded response
	 * containing the current number of published orders held in the 'total'
	 * property.
	 */
	public static function wp_init() {
		if (
			isset( $_REQUEST['order_generator'] ) && 
			wp_verify_nonce( $_REQUEST['order_generator'], 'order-generator-js' )
		) {
			// run generator
			$per_run = get_option( 'wc-order-generator-per-run', self::DEFAULT_PER_RUN );
			self::run( $per_run );
			$n_orders = self::get_order_count();
			$result = array( 'total' => $n_orders );
			echo json_encode( $result );
			exit;
		}
	}

	public static function generator() {
		if ( !current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Access denied.', WCORDERGEN_PLUGIN_DOMAIN ) );
		}
		if ( self::woocommerce_is_active() ) {

			wp_enqueue_script( 'order-generator' );
			wp_enqueue_style( 'order-generator' );

			if ( isset( $_POST['action'] ) && ( $_POST['action'] == 'save' ) && wp_verify_nonce( $_POST['order-generator'], 'admin' ) ) {
				$limit   = !empty( $_POST['limit'] ) ? intval( trim( $_POST['limit'] ) ) : self::DEFAULT_LIMIT;
				$per_run = !empty( $_POST['per_run'] ) ? intval( trim( $_POST['per_run'] ) ) : self::DEFAULT_PER_RUN;
				$period  = !empty( $_POST['period'] ) ? intval( trim( $_POST['period'] ) ) : self::DEFAULT_PERIOD;

				$_countries = array();
				$countries = !empty( $_POST['countries'] ) ? trim( $_POST['countries'] ) : '';
				if ( strlen( $countries ) > 0 ) {
					$supported_country_codes = array_keys( WC()->countries->get_countries() );
					$countries = array_map( 'strtoupper', array_map( 'trim', explode( ',', $countries ) ) );
					foreach ( $countries as $country ) {
						if ( in_array( $country, $supported_country_codes ) ) {
							$_countries[] = $country;
						}
					}
				}
				delete_option( 'wc-order-generator-countries' );
				if ( count( $_countries ) > 0 ) {
					sort( $_countries );
					add_option( 'wc-order-generator-countries', $_countries, null, 'no' );
				}

// 				$titles   = !empty( $_POST['titles'] ) ? $_POST['titles'] : '';
// 				$contents = !empty( $_POST['contents'] ) ? $_POST['contents'] : '';

				if ( $limit < 0 ) {
					$limit = self::DEFAULT_LIMIT;
				}
				delete_option( 'wc-order-generator-limit' );
				add_option( 'wc-order-generator-limit', $limit, null, 'no' );

				if ( $per_run < 0 ) {
					$per_run = self::DEFAULT_PER_RUN;
				}
				if ( $per_run > self::MAX_PER_RUN ) {
					$per_run = self::MAX_PER_RUN;
				}
				delete_option( 'wc-order-generator-per-run' );
				add_option( 'wc-order-generator-per-run', $per_run, null, 'no' );

				if ( $period < 0 ) {
					$period = self::DEFAULT_PERIOD;
				}
				delete_option( 'wc-order-generator-period' );
				add_option( 'wc-order-generator-period', $period, null, 'no' );

// 				delete_option( 'wc-order-generator-titles' );
// 				add_option( 'wc-order-generator-title', $titles, null, 'no' );

// 				delete_option( 'wc-order-generator-contents' );
// 				add_option( 'wc-order-generator-contents', $contents, null, 'no' );
			} else if ( isset( $_POST['action'] ) && ( $_POST['action'] == 'generate' ) && wp_verify_nonce( $_POST['order-generate'], 'admin' ) ) {
				$max = isset( $_POST['max'] ) ? intval( $_POST['max'] ) : 0;
				if ( $max > 0 ) {
					for ( $i = 1; $i <= $max ; $i++ ) {
						self::create_order();
					}
				}
			} else if ( isset( $_POST['action'] ) && ( $_POST['action'] == 'reset' ) && wp_verify_nonce( $_POST['order-generator-reset'], 'admin' ) ) {
				delete_option( 'wc-order-generator-limit' );
				add_option( 'wc-order-generator-limit', self::DEFAULT_LIMIT, null, 'no' );

				delete_option( 'wc-order-generator-per-run' );
				add_option( 'wc-order-generator-per-run', self::DEFAULT_PER_RUN, null, 'no' );

// 				delete_option( 'wc-order-generator-titles' );
// 				add_option( 'wc-order-generator-title', self::DEFAULT_TITLES, null, 'no' );

// 				delete_option( 'wc-order-generator-contents' );
// 				add_option( 'wc-order-generator-contents', self::DEFAULT_CONTENTS, null, 'no' );
			} else if ( isset( $_POST['action'] ) && ( $_POST['action'] == 'delete_orders' ) && wp_verify_nonce( $_POST['order-generator-delete'], 'admin' ) ) {
				global $wpdb;
				$post_ids = $wpdb->get_col( "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = '_wc_order_generator' AND meta_value = 'yes'" );
				if ( $post_ids ) {
					foreach ( $post_ids as $post_id ) {
						wp_delete_post( $post_id, true ); // force_delete to bypass the trash
						do_action( 'woocommerce_delete_order', $post_id );
					}
				}
			} else if ( isset( $_POST['action'] ) && ( $_POST['action'] == 'delete_users' ) && wp_verify_nonce( $_POST['order-generator-delete'], 'admin' ) ) {
				global $wpdb;
				$user_ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM $wpdb->usermeta WHERE meta_key = '_wc_order_generator' AND meta_value = 'yes'" );
				if ( $user_ids ) {
					foreach ( $user_ids as $user_id ) {
						wp_delete_user( $user_id );
					}
				}
			}

			$limit     = get_option( 'wc-order-generator-limit', self::DEFAULT_LIMIT );
			$per_run   = get_option( 'wc-order-generator-per-run', self::DEFAULT_PER_RUN );
			$countries = get_option( 'wc-order-generator-countries', array() );
			$period    = get_option( 'wc-order-generator-period', self::DEFAULT_PERIOD );

// 			$titles   = stripslashes( get_option( 'wc-order-generator-titles', self::DEFAULT_TITLES ) );
// 			$contents = stripslashes( get_option( 'wc-order-generator-contents', self::DEFAULT_CONTENTS ) );

// 			$titles = explode( "\n", $titles );
// 			sort( $titles );
// 			$titles = trim( implode( "\n", $titles ) );

			echo '<h1>';
			echo __( 'Order Generator', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</h1>';

			echo '<div class="order-generator-admin" style="margin-right:1em;">';

			echo '<div>';
			echo __( 'This produces demo orders for testing purposes.', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo __( 'It is <strong>NOT</strong> recommended to use this on a production site.', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo __( 'The plugin will <strong>NOT</strong> clean up the data it has created.', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo __( 'The plugin will create a <em>order-generator</em> user in the role of a <em>Shop Manager</em>.', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</div>';

			echo '<div class="settings">';
			echo '<form name="settings" method="post" action="">';
			echo '<div>';

			echo '<p>';
			echo __( 'The continuous generator runs at most once per second, creating up to the indicated number of orders per run.', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo __( 'The continuous generator will try to create new orders until stopped, or the total number of orders reaches the indicated limit.', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</p>';

			echo '<p>';
			echo '<label>';
			echo __( 'Limit', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo sprintf( '<input type="text" name="limit" value="%d" />', $limit );
			echo '</label>';
			echo '</p>';

			echo '<p>';
			echo '<label>';
			echo __( 'Per Run', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo sprintf( '<input type="text" name="per_run" value="%d" />', $per_run );
			echo ' ';
			echo sprintf( __( 'Maximum %d', WCORDERGEN_PLUGIN_DOMAIN ), self::MAX_PER_RUN );
			echo '</label>';
			echo '</p>';

			echo '<p>';
			echo '<label>';
			echo __( 'Period', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo sprintf( '<input type="text" name="period" value="%d" />', $period );
			echo ' ';
			echo __( 'The dates of generated orders are spread out over this number of days back from the current date, with a heavier focus on more recent dates.', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</label>';
			echo '</p>';

			echo '<p>';
			echo '<label>';
			echo __( 'Countries', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo sprintf( '<input type="text" name="countries" value="%s" placeholder="%s"/>', esc_attr( implode( ',', $countries ) ), esc_attr__( 'All', WCORDERGEN_PLUGIN_DOMAIN ) );
			echo '</label>';
			echo ' ';
			echo '<span class="description">';
			esc_html_e( 'Indicate country codes separated by comma. Orders for all countries will be generated if none are set here.', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</span>';
			if ( count( $countries ) > 0 ) {
				$country_names = array();
				$supported_countries = WC()->countries->get_countries();
				foreach ( $countries as $country ) {
					$country_names[] = $supported_countries[$country];
				}
				echo '<div class="description">';
				printf( esc_html__( '[%s]', WCORDERGEN_PLUGIN_DOMAIN ), implode( ', ', $country_names ) );
				echo '</div>';
			}
			echo '</p>';

// 			echo '<p>';
// 			echo '<label>';
// 			echo __( 'Titles', WCORDERGEN_PLUGIN_DOMAIN );
// 			echo '<br/>';
// 			echo '<textarea name="titles" style="height:10em;width:90%;">';
// 			echo htmlentities( $titles );
// 			echo '</textarea>';
// 			echo '</label>';
// 			echo '</p>';

// 			echo '<p>';
// 			echo '<label>';
// 			echo __( 'Contents', WCORDERGEN_PLUGIN_DOMAIN );
// 			echo '<br/>';
// 			echo '<textarea name="contents" style="height:20em;width:90%;">';
// 			echo htmlentities( $contents );
// 			echo '</textarea>';
// 			echo '</label>';
// 			echo '</p>';

			wp_nonce_field( 'admin', 'order-generator', true, true );

			echo '<div class="buttons">';
			echo sprintf( '<input class="button button-primary" type="submit" name="submit" value="%s" />', __( 'Save', WCORDERGEN_PLUGIN_DOMAIN ) );
			echo '<input type="hidden" name="action" value="save" />';
			echo '</div>';

			echo '</div>';
			echo '</form>';
			echo '</div>';

			echo '<h2>';
			echo __( 'Reset', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</h2>';

			echo '<div class="reset">';
			echo '<form name="reset" method="post" action="">';
			echo '<div>';

			echo '<p>';
			echo __( 'Reset to defaults', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</p>';

			wp_nonce_field( 'admin', 'order-generator-reset', true, true );

			echo '<div class="buttons">';
			echo sprintf( '<input class="button button-primary" type="submit" name="submit" value="%s" />', __( 'Reset', WCORDERGEN_PLUGIN_DOMAIN ) );
			echo '<input type="hidden" name="action" value="reset" />';
			echo '</div>';

			echo '</div>';
			echo '</form>';
			echo '</div>';

			//
			// Single Run
			//

			echo '<h2>';
			echo __( 'Single Run', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</h2>';

			echo '<div class="generate">';
			echo '<form name="generate" method="post" action="">';
			echo '<div>';

			echo '<p>';
			echo '<label>';
			echo __( 'Generate up to &hellip;', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo '<input type="text" name="max" value="1" />';
			echo '</label>';
			echo '</p>';

			wp_nonce_field( 'admin', 'order-generate', true, true );

			echo '<div class="buttons">';
			echo sprintf( '<input class="button button-primary" type="submit" name="submit" value="%s" />', __( 'Run', WCORDERGEN_PLUGIN_DOMAIN ) );
			echo '<input type="hidden" name="action" value="generate" />';
			echo '</div>';

			echo '</div>';
			echo '</form>';
			echo '</div>';

			//
			// Continuous AJAX Run
			//

			echo '<h2>';
			echo __( 'Continuous AJAX Run', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</h2>';

			echo '<div class="buttons">';
			echo sprintf( '<input class="button" type="button" id="order-generator-run" name="order-generator-run" value="%s" />', __( 'Run', WCORDERGEN_PLUGIN_DOMAIN ) );
			echo ' ';
			echo sprintf( '<input class="button" type="button" id="order-generator-stop" name="order-generator-stop" value="%s" />', __( 'Stop', WCORDERGEN_PLUGIN_DOMAIN ) );
			echo '</div>';

			echo '<div id="order-generator-status"></div>';
			echo '<div id="order-generator-update"></div>';
			echo '<div id="order-generator-blinker"></div>';

			$js_nonce = wp_create_nonce( 'order-generator-js' );

			echo '<script type="text/javascript">';
			echo 'if ( typeof jQuery !== "undefined" ) {';
			echo 'jQuery(document).ready(function(){';
			echo sprintf( 'ixwcordergen.limit = %d;', $limit );
			echo 'jQuery("#order-generator-run").click(function(e){';
			echo 'e.stopPropagation();';
			echo sprintf(
				'ixwcordergen.start("%s");',
				add_query_arg(
					array(
						'order_generator' => $js_nonce
					),
					admin_url( 'admin-ajax.php' )
				)
			);
			echo '});'; // run click
			echo 'jQuery("#order-generator-stop").click(function(e){';
			echo 'e.stopPropagation();';
			echo 'ixwcordergen.stop();';
			echo '});'; // stop click
			echo '});'; // ready
			echo '}';
			echo '</script>';

			//
			// Delete orders and users
			//

			echo '<h2>';
			echo __( 'Delete generated Orders and Users', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</h2>';

			echo '<div class="delete">';
			echo '<form name="delete" method="post" action="">';
			echo '<div>';

			echo '<p>';
			echo __( 'Clicking the button will attempt to delete all generated orders.', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo '<strong>';
			echo __( 'Important!', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</strong>';
			echo ' ';
			echo __( 'Once you click the button, the process will start immediately without asking for further confirmation.', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo __( 'Note that this is very rudimentary and with many orders to delete, it will likely end up in a timeout. In that case, you will have to do this several times to delete all generated orders.', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</p>';

			wp_nonce_field( 'admin', 'order-generator-delete', true, true );

			echo '<div class="buttons">';
			echo sprintf( '<input class="button button-primary" type="submit" name="submit" value="%s" />', __( 'Delete Orders', WCORDERGEN_PLUGIN_DOMAIN ) );
			echo '<input type="hidden" name="action" value="delete_orders" />';
			echo '</div>';

			echo '</div>';
			echo '</form>';
			echo '</div>';

			echo '<div class="delete">';
			echo '<form name="delete" method="post" action="">';
			echo '<div>';

			echo '<p>';
			echo __( 'Clicking the button will attempt to delete all generated users.', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo '<strong>';
			echo __( 'Important!', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</strong>';
			echo ' ';
			echo __( 'Once you click the button, the process will start immediately without asking for further confirmation.', WCORDERGEN_PLUGIN_DOMAIN );
			echo ' ';
			echo __( 'Note that this is very rudimentary and with many users to delete, it will likely end up in a timeout. In that case, you will have to do this several times to delete all generated users.', WCORDERGEN_PLUGIN_DOMAIN );
			echo '</p>';

			wp_nonce_field( 'admin', 'order-generator-delete', true, true );

			echo '<div class="buttons">';
			echo sprintf( '<input class="button button-primary" type="submit" name="submit" value="%s" />', __( 'Delete Users', WCORDERGEN_PLUGIN_DOMAIN ) );
			echo '<input type="hidden" name="action" value="delete_users" />';
			echo '</div>';

			echo '</div>';
			echo '</form>';
			echo '</div>';

			echo '</div>'; // .order-generator-admin
		}
	}

	/**
	 * Order generation cycle.
	 */
	public static function run( $n = self::MAX_PER_RUN ) {
		$limit = intval( get_option( 'wc-order-generator-limit', self::DEFAULT_LIMIT ) );
		$n_orders = self::get_order_count();
		if ( $n_orders < $limit ) {
			$n = min( $n, $limit - $n_orders );
			$n = min( $n, self::MAX_PER_RUN );
			if ( $n > 0 ) {
				for ( $i = 0; $i < $n; $i++ ) {
					self::create_order();
				}
			}
		}
	}

	/**
	 * Returns the total number of existing orders.
	 * 
	 * @return int
	 */
	public static function get_order_count() {
		global $wpdb;
		return intval( $wpdb->get_var(
			"SELECT count(*) FROM $wpdb->posts WHERE post_type = 'shop_order'"
		) );
	}

	public static function generate_time( $period = 365 ) {
		$f    = $period / 365.0;
		$k    = 100;
		$time = time();
		$time = $time - (
			(                        rand( 1, $k ) *       7 * $f * 24*60*60 / $k ) +
			( rand( 1, 100 ) >= 15 ? rand( 1, $k ) *      14 * $f * 24*60*60 / $k : 0 ) +
			( rand( 1, 100 ) >= 50 ? rand( 1, $k ) *      31 * $f * 24*60*60 / $k : 0 ) +
			( rand( 1, 100 ) >= 75 ? rand( 1, $k ) *     180 * $f * 24*60*60 / $k : 0 ) +
			( rand( 1, 100 ) >= 90 ? rand( 1, $k ) * 1 * 365 * $f * 24*60*60 / $k : 0 )
		);
		return intval( $time );
	}

	public static function create_order() {

		global $wpdb, $woocommerce;

		if ( empty( $woocommerce ) ) {
			return null;
		}

		set_time_limit( 0 );

		$min_products = 1; // @todo configurable
		$max_products = 25; // @todo configurable
		$min_quantity = 1; // @todo configurable
		$max_quantity = 10; // @todo configurable
		$min_shipping = 0; // @todo configurable
		$max_shipping = 100; // @todo configurable

		$order_status_processing_probability = 8 / 10; // @todo configurable
		$order_status_completed_probability = 7 / 10; // @todo configurable
		$order_status_change_probability = 2 / 7; // @todo configurable

		$repeat_customer_probability = 4 / 11; // @todo configurable

		$data = new WC_Order_Generator_Data();

		$user_id = null;
		if ( ( rand( 1, 10 ) / 10.0 ) <= $repeat_customer_probability ) {
			$user_id = $data->get_random_user_id();
		}
		if ( !$user_id ) {
			$user_id = $data->create_user();
		}

		// get only visible products
		$product_ids = wc_get_products(
			array(
				'status'     => 'publish',
				'limit'      => -1,
				'visibility' => 'visible',
				'return'     => 'ids'
			)
		);
		if ( count( $product_ids ) === 0 ) {
			// alternative if nothing was produced above
			$product_ids = array();
			$_product_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'product' AND post_status='publish'" );
			foreach( $_product_ids as $product_id ) {
				$product_ids[] = $product_id;
			}
			unset( $_product_ids );
		}
		if ( count( $product_ids ) == 0 ) {
			return null;
		}
		$n_products = rand( $min_products, $max_products );

		try {

			$customer        = new WC_Customer( $user_id );
			$payment_methods = array( 'bacs','cheque','cod' );
			$payment_method  = $payment_methods[array_rand( $payment_methods, 1 )];
			/**
			 * @var WC_Order $order
			 */
			$order           = wc_create_order( array( 'customer_id' => $user_id ) );

			if ( !( $order instanceof WP_Error ) ) {

				$period = get_option( 'wc-order-generator-period', self::DEFAULT_PERIOD );
				$time = self::generate_time( $period );
				$order->set_date_created( $time );

				$order->set_payment_method( $payment_method );

				$order->set_billing_address_1( $customer->get_billing_address_1() );
				$order->set_billing_country( $customer->get_billing_country() );
				$order->set_billing_postcode( $customer->get_billing_postcode() );
				$order->set_billing_phone( $customer->get_billing_phone() );
				$order->set_billing_email( $customer->get_billing_email() );
				$order->set_billing_city( $customer->get_billing_city() );
				$order->set_billing_first_name( $customer->get_billing_first_name() );
				$order->set_billing_last_name( $customer->get_billing_last_name() );
				$order->set_billing_state( $customer->get_billing_state() );

				$order->set_shipping_address_1( $customer->get_billing_address_1() );
				$order->set_shipping_country( $customer->get_billing_country() );
				$order->set_shipping_postcode( $customer->get_billing_postcode() );
				$order->set_shipping_city( $customer->get_billing_city() );
				$order->set_shipping_first_name( $customer->get_billing_first_name() );
				$order->set_shipping_last_name( $customer->get_billing_last_name() );
				$order->set_shipping_state( $customer->get_billing_state() );

				for( $i = 0; $i < $n_products; $i++ ) {
					$product_id = $product_ids[rand( 0, count( $product_ids ) - 1 )];
					$order->add_product( wc_get_product( $product_id ), rand( $min_quantity, $max_quantity ) );
				}

				$shipping_cost = rand( $min_shipping, $max_shipping );
				$shipping_rate = new WC_Shipping_Rate( 'wc-order-generator-random-rate', 'WooCommerce Order Generator Random Rate', $shipping_cost, array(), '' );
				$item = new WC_Order_Item_Shipping();
				$item->set_props( array(
					'method_title' => 'WooCommerce Order Generator Random Shipping',
					'method_id'    => 'wc-order-generator-random-shipping',
					'total'        => wc_format_decimal( $shipping_cost ),
					'order_id'     => $order->get_id()
				) );
				foreach ( $shipping_rate->get_meta_data() as $key => $value ) {
					$item->add_meta_data( $key, $value, true );
				}
				$item->save();
				$order->add_item( $item );
				$order->calculate_shipping();

				$order->calculate_taxes();
				$order->calculate_totals();
				// $order->set_shipping_total( rand( $min_shipping, $max_shipping ) ); // shipping is done via rate above

				$order->set_status( 'on-hold' );
				if ( ( rand( 1, 10 ) / 10.0 ) <= $order_status_processing_probability ) {
					$order->set_status( 'processing' );
				}
				if ( ( rand( 1, 10 ) / 10.0 ) <= $order_status_completed_probability ) {
					$order->set_status( 'completed' );
				}
				if ( ( rand( 1, 10 ) / 10.0 ) <= $order_status_change_probability ) {
					$statuses = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );
					$status = $statuses[array_rand( $statuses, 1 )];
					$order->set_status( $status );
				}
				$order->add_meta_data( '_wc_order_generator', 'yes', true );
				$order->save();
				$order_id = $order->get_id();
			}

		} catch ( Exception $e ) {
			$order_id = null;
		}

		return $order_id;
	}

	/**
	 * Returns true if WooCommerce is active.
	 * @return boolean true if WooCommerce is active
	 */
	private static function woocommerce_is_active() {
		$active_plugins = get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			$active_sitewide_plugins = array_keys( $active_sitewide_plugins );
			$active_plugins = array_merge( $active_plugins, $active_sitewide_plugins );
		}
		return in_array( 'woocommerce/woocommerce.php', $active_plugins ); 
	}
}
WC_Order_Generator::init();
