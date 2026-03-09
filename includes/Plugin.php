<?php
/**
 * Customer Post Type - Products
 *
 * @package WP_Barcode_Manager
 */

namespace SBM;

/**
 * Plugin Main Class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Plugin Initialization
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		Cpt::init();         // Register CPT.
		RestApi::init();     // Register REST API.
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'scripts' ) );
	}

	/**
	 * Plugin Page.
	 *
	 * @since 1.0.0
	 */
	public static function page() {
		echo '<div id="wbm-root"></div>';
	}

	/**
	 * Plugin Menu.
	 *
	 * @since 1.0.0
	 */
	public static function register_menu() {
		add_menu_page(
			'Barcode Manager',
			'Barcode Manager',
			'manage_options',
			'barcode-manager',
			array( self::class, 'page' ),   // Callback.
			'dashicons-admin-generic',
			25
		);
	}

	/**
	 * Plugin Scripts.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $hook Current admin page hook.
	 */
	public static function scripts( $hook ) {
		if ( 'toplevel_page_barcode-manager' !== $hook ) {
			return;
		}

		// React app.
		wp_enqueue_script(
			'wbm-react-app',
			plugin_dir_url( __DIR__ ) . 'build/index.js',
			array(
				'wp-element',      // React & ReactDOM.
				'wp-block-editor', // RichText.
				'wp-components',   // UI components.
				'wp-data',         // state management.
				'wp-i18n',         // localization.
			),
			filemtime( plugin_dir_path( __DIR__ ) . 'build/index.js' ),
			true
		);

		// Custom styles.
		wp_enqueue_style(
			'wbm-react-app-style',
			plugin_dir_url( __DIR__ ) . 'build/style-index.css',
			array(),
			filemtime( plugin_dir_path( __DIR__ ) . 'build/style-index.css' )
		);

		// Gutenberg styles (important).
		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style( 'wp-block-editor' );

		// Pass REST API info.
		wp_localize_script(
			'wbm-react-app',
			'WBM_API',
			array(
				'url'   => rest_url( 'wbm/v1/' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
