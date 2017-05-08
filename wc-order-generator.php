<?php
/**
 * wc-order-generator.php
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
 *
 * Plugin Name: WooCommerce Order Generator
 * Plugin URI: http://www.itthinx.com/
 * Description: A sample product generator for WooCommerce. Useful for performance testing on large databases.
 * Version: 1.0.0
 * Author: itthinx
 * Author URI: http://www.itthinx.com
 * Donate-Link: http://www.itthinx.com
 * License: GPLv3
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCORDERGEN_PLUGIN_VERSION', '1.0.0' );
define( 'WCORDERGEN_PLUGIN_DOMAIN', 'wc-order-generator' );
define( 'WCORDERGEN_PLUGIN_FILE', __FILE__ );
define( 'WCORDERGEN_PLUGIN_URL', plugins_url( 'wc-order-generator' ) );
define( 'WCORDERGEN_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WCORDERGEN_INCLUDES_DIR', WCORDERGEN_PLUGIN_DIR . '/includes' );
function wc_order_generator_plugins_loaded() {
	if ( defined( 'WC_VERSION' ) ) {
		require_once WCORDERGEN_INCLUDES_DIR . '/class-wc-order-generator-syllables.php';
		require_once WCORDERGEN_INCLUDES_DIR . '/class-wc-order-generator-data.php';
		require_once WCORDERGEN_INCLUDES_DIR . '/class-wc-order-generator.php';
	}
}
add_action( 'plugins_loaded', 'wc_order_generator_plugins_loaded' );
