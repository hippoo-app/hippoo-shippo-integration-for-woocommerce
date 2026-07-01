<?php
/**
 * Plugin Name: Hippoo Shippo Integration for WooCommerce
 * Plugin URI: https://hippoo.app
 * Description: Hippoo Shippo Integration connects Shippo with the WooCommerce Admin app, allowing you to generate carrier shipping labels directly from your dashboard. Get real-time shipping rates at checkout and support for shipments. Designed by the Hippoo team to streamline your shipping process.
 * Short Description: Generate Shippo carrier labels inside WooCommerce Admin with real-time shipping rates at checkout.
 * Version: 1.3.0
 * Author: Hippoo Team
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.7
 * Requires Plugins: woocommerce
 * Tags: WooCommerce, Shippo, shipping, labels, e-commerce, carriers, goshippo, WooCommerce label generate, shipping rates, WooCommerce shipping, carrier integration, shipping label generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'HIPPSHIPP_VERSION', '1.3.0' );
define( 'HIPPSHIPP__FILE__', __FILE__ );
define( 'HIPPSHIPP_PATH', plugin_dir_path( __FILE__ ) );
define( 'HIPPSHIPP_URL', plugin_dir_url( __FILE__ ) );

require_once HIPPSHIPP_PATH . 'inc/helper.php';
require_once HIPPSHIPP_PATH . 'inc/shippo-api.php';
require_once HIPPSHIPP_PATH . 'inc/settings.php';
require_once HIPPSHIPP_PATH . 'inc/modals.php';
require_once HIPPSHIPP_PATH . 'inc/ajax.php';
require_once HIPPSHIPP_PATH . 'inc/hooks.php';
require_once HIPPSHIPP_PATH . 'inc/web-api.php';

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );