=== WooCommerce Order Generator ===
Contributors: itthinx
Donate link: http://www.itthinx.com/shop/
Tags: order, performance, generator, woocommerce, benchmark, automatic, example, orders, product, products, sample, test, tester, testing, test-tool
Requires at least: 4.6
Tested up to: 5.2
Stable tag: 1.1.0
License: GPLv3

A sample order generator for WooCommerce. Useful for performance testing on large databases.

== Description ==

This plugin is intended to be used as a sample order generator for WooCommerce.

It's purpose is to provide an automated way of creating large sets of order,
useful in providing an environment for performance tests, benchmarks
and use case testing.

== Installation ==

= Dashboard =

Log in as an administrator and go to <strong>Plugins > Add New</strong>.

You can download the plugin from GitHub, clone or fork its repository https://github.com/itthinx/wc-order-generator

In case we later host it on WordPress.org, you should be able to install it directly like this:
Type <em>WooCommerce Order Generator</em> in the search field and click <em>Search Plugins</em>, locate the <em>WooCommerce Order Generator<em> plugin by <em>itthinx</em> and install it by clicking <em>Install Now</em>.
Now <em>activate</em> the plugin to be able to generate sample orders.

We recommend to also install the [WooCommerce Product Generator](https://wordpress.org/plugins/woocommerce-product-generator/) plugin and create a large set of sample products with it which can then be used with the generated orders.

= FTP =

You can install the plugin via FTP, see [Manual Plugin Installation](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Screenshots ==

Sorry, none available at present.

== Changelog ==

= 1.1.0 =
* Fixed an issue when no states are available for the country.
* Added functions to delete generated orders and users. These only work with users and orders generated as of this version 1.1.0.
* Added an option to limit the countries used for the generated orders.
* Now assigning a distribution of dates to generated orders over a period of maximum 3 years back from the moment of generation.
* Changed the default limit from 10000 to only 100.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

Tested with the latest versions of WordPress and WooCommerce.
