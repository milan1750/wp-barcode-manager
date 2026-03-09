<?php
/**
 * Customer Post Type - Products
 *
 * @package WP_Barcode_Manager
 */

namespace SBM;

/**
 * Undocumented class
 *
 * @since 1.0.0
 */
class Cpt {


	/**
	 * Undocumented function
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register_product_cpt' ) );
	}

	/**
	 * Undocumented function
	 *
	 * @since 1.0.0
	 */
	public static function register_product_cpt() {
		$labels = array(
			'name'          => 'Products',
			'singular_name' => 'Product',
		);

		$args = array(
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'supports'     => array( 'title' ),
			'show_in_rest' => true,
			'rest_base'    => 'wbm_product',
		);

		register_post_type( 'wbm_product', $args );
	}
}
