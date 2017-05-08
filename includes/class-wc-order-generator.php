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

	const MAX_PER_RUN = 100;
	const DEFAULT_PER_RUN = 10;

	const IMAGE_WIDTH = 512;
	const IMAGE_HEIGHT = 512;

	const DEFAULT_LIMIT = 10000;

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
				$limit    = !empty( $_POST['limit'] ) ? intval( trim( $_POST['limit'] ) ) : self::DEFAULT_LIMIT;
				$per_run  = !empty( $_POST['per_run'] ) ? intval( trim( $_POST['per_run'] ) ) : self::DEFAULT_PER_RUN;
				$titles   = !empty( $_POST['titles'] ) ? $_POST['titles'] : '';
				$contents = !empty( $_POST['contents'] ) ? $_POST['contents'] : '';

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

				delete_option( 'wc-order-generator-titles' );
				add_option( 'wc-order-generator-title', $titles, null, 'no' );

				delete_option( 'wc-order-generator-contents' );
				add_option( 'wc-order-generator-contents', $contents, null, 'no' );
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

				delete_option( 'wc-order-generator-titles' );
				add_option( 'wc-order-generator-title', self::DEFAULT_TITLES, null, 'no' );

				delete_option( 'wc-order-generator-contents' );
				add_option( 'wc-order-generator-contents', self::DEFAULT_CONTENTS, null, 'no' );
			}

			$limit    = get_option( 'wc-order-generator-limit', self::DEFAULT_LIMIT );
			$per_run  = get_option( 'wc-order-generator-per-run', self::DEFAULT_PER_RUN );
			$titles   = stripslashes( get_option( 'wc-order-generator-titles', self::DEFAULT_TITLES ) );
			$contents = stripslashes( get_option( 'wc-order-generator-contents', self::DEFAULT_CONTENTS ) );

			$titles = explode( "\n", $titles );
			sort( $titles );
			$titles = trim( implode( "\n", $titles ) );

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
			echo __( 'Titles', WCORDERGEN_PLUGIN_DOMAIN );
			echo '<br/>';
			echo '<textarea name="titles" style="height:10em;width:90%;">';
			echo htmlentities( $titles );
			echo '</textarea>';
			echo '</label>';
			echo '</p>';

			echo '<p>';
			echo '<label>';
			echo __( 'Contents', WCORDERGEN_PLUGIN_DOMAIN );
			echo '<br/>';
			echo '<textarea name="contents" style="height:20em;width:90%;">';
			echo htmlentities( $contents );
			echo '</textarea>';
			echo '</label>';
			echo '</p>';

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
		//$counts = wp_count_posts( 'product' ); // <-- nah ... :|
		global $wpdb;
		return intval( $wpdb->get_var(
			"SELECT count(*) FROM $wpdb->posts WHERE post_type = 'shop_order'"
		) );
	}

	public static function create_order() {
		$user_id = self::get_user_id(); 
		$title = self::get_title();
		$i = 0;
		while( ( $i < 99 ) ) {
			if ( get_page_by_title( $title, OBJECT, 'shop_order' ) ) {
				$title .= " " . self::get_title();
			} else {
				break;
			}
			$i++;
		}

		$content = self::get_content();
		$excerpt = self::get_excerpt( 3, $content );

		$post_id = wp_insert_post( array(
			'post_type' => 'product',
			'post_title' => $title,
			'post_excerpt' => $excerpt,
			'post_content' => $content,
			'post_status' => 'publish',
			'post_author' => $user_id
		) );
		if ( !( $post_id instanceof WP_Error ) ) {

			// visibility
			update_post_meta( $post_id, '_visibility', 'visible' );

			// price
			$price = wc_format_decimal( floatval( rand( 1, 10000 ) ) / 100.0 );
			update_post_meta( $post_id, '_price', $price );
			update_post_meta( $post_id, '_regular_price', $price );

			// add categories
			$terms = array();
			$cats = explode( "\n", self::DEFAULT_CATEGORIES );
			$c_n = count( $cats );
			$c_max = rand( 1, 3 );
			for ( $i = 0; $i < $c_max ; $i++ ) {
				$terms[] = $cats[rand( 0, $c_n - 1 )];
			}
			wp_set_object_terms( $post_id, $terms, 'product_cat', true );

			// add tags
			$tags = explode( " ", $title );
			$tags[] = 'progen';
			$potential = explode( " ", $content );
			$n = count( $potential );
			$t_max = rand( 1, 7 );
			for ( $i = 0; $i < $t_max ; $i++ ) {
				$tags[] = preg_replace( "/[^a-zA-Z0-9 ]/", '', $potential[rand( 0, $n-1 )] );
			}
			wp_set_object_terms( $post_id, $tags, 'product_tag', true );

			// product image
			$image = self::get_image();
			$image_name = self::get_image_name();
			$r = wp_upload_bits( $image_name, null, $image );
			if ( !empty( $r ) && is_array( $r ) && !empty( $r['file'] ) ) {
				$filetype = wp_check_filetype( $r['file'] );
				$attachment_id = wp_insert_attachment(
					array(
						'post_title' => $title,
						'post_mime_type' => $filetype['type'],
						'post_status' => 'publish',
						'post_author' => $user_id
					),
					$r['file'],
					$post_id
				);
				if ( !empty( $attachment_id ) ) {
					include_once ABSPATH . 'wp-admin/includes/image.php';
					if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
						$meta = wp_generate_attachment_metadata( $attachment_id, $r['file'] );
						wp_update_attachment_metadata( $attachment_id, $meta );
					}
					update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
				}
			}
		}
	}

	/**
	 * Returns the user ID of the order-generator user which is used as the
	 * author of products generated. The user is created here if it doesn't
	 * exist yet, with role Shop Manager.
	 * 
	 * @return int order-generator user ID
	 */
	public static function get_user_id() {
		$user_id = get_current_user_id();
		$user = get_user_by( 'login', 'order-generator' );
		if ( $user instanceof WP_User ) {
			$user_id = $user->ID;
		} else {

			$user_pass = wp_generate_password( 12 );
			$maybe_user_id = wp_insert_user( array(
				'user_login' => 'order-generator',
				'role'       => 'shop_manager',
				'user_pass'  => $user_pass
			) );
			if ( !( $maybe_user_id instanceof WP_Error ) ) {
				$user_id = $maybe_user_id;

				// notify admin
				$user = get_userdata( $user_id );
				$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

				$message  = sprintf( __( 'Order generator user created on %s:', WCORDERGEN_PLUGIN_DOMAIN ), $blogname ) . "\r\n\r\n";
				$message .= sprintf( __( 'Username: %s', WCORDERGEN_PLUGIN_DOMAIN ), $user->user_login ) . "\r\n\r\n";
				$message .= sprintf( __( 'Password: %s', WCORDERGEN_PLUGIN_DOMAIN ), $user_pass ) . "\r\n\r\n";
				$message .= __( 'The user has the role of a Shop Manager.', WCORDERGEN_PLUGIN_DOMAIN ) . "\r\n";

				@wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Order Generator User', WCORDERGEN_PLUGIN_DOMAIN ), $blogname ), $message);
			}
		}
		return $user_id;
	} 

	/**
	 * Produce a title.
	 * 
	 * @param int $n_words
	 * @return string
	 */
	public static function get_title( $n_words = 3 ) {
		$titles = trim( stripslashes( get_option( 'wc-order-generator-titles', self::DEFAULT_TITLES ) ) );
		$titles = explode( "\n", $titles );
		$title = array();
		$n = count( $titles );
		$n_words = rand( 1, $n_words );
		for ( $i = 1; $i <= $n_words ; $i++ ) {
			$title[] = $titles[rand( 0, $n - 1 )];
		}
		$title = implode( ' ', $title );
		return $title;
	}

	/**
	 * Produce the excerpt.
	 *
	 * @param int $n_lines
	 * @return string
	 */
	public static function get_excerpt( $n_lines = 3, $contents = null ) {
		if ( $contents === null ) {
			$contents = trim( stripslashes( get_option( 'wc-order-generator-contents', self::DEFAULT_CONTENTS ) ) );
		} else {
			$contents = str_ireplace( '</p>', "\n", $contents );
			$contents = str_ireplace( '<p>', '', $contents );
		}
		$contents = explode( "\n", $contents );
		$content = array();
		$n = count( $contents );
		$n_lines = rand( 1, $n_lines );
		for ( $i = 1; $i <= $n_lines ; $i++ ) {
			$maybe_content = $contents[rand( 0, $n - 1 )];
			if ( !in_array( $maybe_content, $content ) ) {
				$content[] = $maybe_content;
			}
		}
		$content = "<p>" . implode( "</p><p>", $content ) . "</p>";
		return $content;
	}

	/**
	 * Produce content.
	 * 
	 * @param int $n_lines
	 * @return string
	 */
	public static function get_content( $n_lines = 10 ) {
		$contents = trim( stripslashes( get_option( 'wc-order-generator-contents', self::DEFAULT_CONTENTS ) ) );
		$contents = explode( "\n", $contents );
		$content = array();
		$n = count( $contents );
		$n_lines = rand( 1, $n_lines );
		for ( $i = 1; $i <= $n_lines ; $i++ ) {
			$content[] = $contents[rand( 0, $n - 1 )];
		}
		$content = "<p>" . implode( "</p><p>", $content ) . "</p>";
		return $content;
	}

	/**
	 * Produce an image.
	 * 
	 * @return string image data
	 */
	public static function get_image() {
		$output = '';
		if ( function_exists( 'imagepng' ) ) {
			$width = self::IMAGE_WIDTH;
			$height = self::IMAGE_HEIGHT;

			$image = imagecreatetruecolor( $width, $height );
			for( $i = 0; $i <= 11; $i++ ) {
				$x = rand( 0, $width );
				$y = rand( 0, $height );
				$w = rand( 1, $width );
				$h = rand( 1, $height );
				$red = rand( 0, 255 );
				$green = rand( 0, 255 );
				$blue  = rand( 0, 255 );
				$color = imagecolorallocate( $image, $red, $green, $blue );
				imagefilledrectangle(
					$image,
					$x - $w / 2,
					$y - $h / 2,
					$x + $w / 2,
					$y + $h / 2,
					$color
				);
			}

			ob_start();
			imagepng( $image );
			$output = ob_get_clean();
			imagedestroy( $image );
		} else {
			$image = file_get_contents( WCORDERGEN_PLUGIN_URL . '/images/placeholder.png' );
			ob_start();
			echo $image;
			$output = ob_get_clean();
		}
		return $output;

	}

	/**
	 * Produce a name for an image.
	 * @return string
	 */
	public static function get_image_name() {
		$t = time();
		$r = rand();
		return "order-$t-$r.png";
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
