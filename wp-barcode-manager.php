<?php
/**
 * Plugin Name: WP Barcode Manager
 * Plugin URI:  https://milanmalla.com/wp-barcode-manager
 * Description: A WordPress plugin to generate barcodes for products.
 * Version:     1.0.1
 * Author:      Milan Malla
 * Author URI:  https://milanmalla.com
 * License:     GPL-2.0-or-later
 * Text Domain: wp-barcode-manager
 *
 * @package WP_Barcode_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'WBM_VERSION', '1.0.0' );
define( 'WBM_PLUGIN_FILE', __FILE__ );
define( 'WBM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load Composer autoloader.
 */
if ( file_exists( WBM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WBM_PLUGIN_DIR . 'vendor/autoload.php';
}

use SBM\Plugin;

/**
 * Plugin activation.
 */
function wbm_activate() {
	Plugin::activate();
}
register_activation_hook( __FILE__, 'wbm_activate' );

/**
 * Initialize plugin.
 */
function wbm_init() {
	Plugin::init();
}
add_action( 'plugins_loaded', 'wbm_init' );
