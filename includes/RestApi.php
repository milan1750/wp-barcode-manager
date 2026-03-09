<?php
/**
 * REST API handler for WP Barcode Manager
 * Only keeps value styles (bold, italic, underline, color)
 *
 * @package WP_Barcode_Manager
 */

namespace SBM;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Font;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for products and XLSX import/export.
 *
 * @since 1.0.0
 */
class RestApi {

	private const PRODUCT_FIELDS = array(
		'Label',
		'Category1',
		'Category2',
		'Product',
		'Ingredients',
		'AllergyAdvice 1 (inc May contain)',
		'AllergyAdvice 2',
		'Price',
		'Eat In Price',
		'BarcodeEAN13',
		'Storage Information',
		'PLU',
	);

	/**
	 * Fields that can contain HTML and should be sanitized with wp_kses_post.
	 */
	private const HTML_FIELDS = array(
		'Product',
		'Ingredients',
		'AllergyAdvice 1 (inc May contain)',
		'AllergyAdvice 2',
		'Storage Information',
	);

	/**
	 * Initialize REST API routes.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Check if current user has permissions to manage products.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user can manage options, false otherwise.
	 */
	public static function permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register REST API routes for product CRUD and XLSX import/export.
	 *
	 * @since 1.0.0
	 */
	public static function register_routes(): void {
		$namespace = 'wbm/v1';
		$routes    = array(
			array( '/products', 'GET', 'get_products' ),
			array( '/products', 'POST', 'create_product' ),
			array( '/products/(?P<id>\d+)', 'PUT', 'update_product' ),
			array( '/products/(?P<id>\d+)', 'DELETE', 'delete_product' ),
			array( '/products/import-xlsx', 'POST', 'import_products_xlsx' ),
			array( '/products/export-xlsx', 'GET', 'export_products' ),
		);

		foreach ( $routes as $route ) {
			register_rest_route(
				$namespace,
				$route[0],
				array(
					'methods'             => $route[1],
					'callback'            => array( __CLASS__, $route[2] ),
					'permission_callback' => array( __CLASS__, 'permissions' ),
					'args'                => '/products/(?P<id>\d+)' === $route[0] ? array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					) : array(),
				)
			);
		}
	}

	/**
	 * Get a list of products.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_REQUEST $request Full details about the request.
	 * @return array List of products.
	 */
	public static function get_products( $request ): array {
		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
		}
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, (int) $request->get_param( 'per_page' ) );

		$query = new \WP_Query(
			array(
				'post_type'      => 'sbm_product',
				'posts_per_page' => $per_page,
				'paged'          => $page,
			)
		);

		$data = array_map( fn( $post ) => self::map_post_to_array( $post ), $query->posts );

		return array(
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'data'        => $data,
		);
	}

	/**
	 * Create a new product.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_REQUEST $request Full details about the request.
	 * @return array|\WP_Error Details of the created product or \WP_Error on failure.
	 */
	public static function create_product( $request ) {

		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
		}
		$params = $request->get_json_params();
		$data   = self::sanitize_product_fields( $params );

		if ( empty( $data['Product'] ) || empty( $data['PLU'] ) ) {
			return new \WP_Error( 'missing_data', 'Product and PLU are required', array( 'status' => 400 ) );
		}

		if ( empty( $data['BarcodeEAN13'] ) ) {
			$data['BarcodeEAN13'] = self::generate_unique_barcode();
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => sanitize_text_field( $data['Product'] ),
				'post_type'   => 'sbm_product',
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::update_product_meta( $post_id, $data );

		return array_merge( array( 'id' => $post_id ), $data );
	}

	/**
	 * Update an existing product.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_REQUEST $request Full details about the request.
	 * @return array|\WP_Error Details of the updated product or \WP_Error on failure.
	 */
	public static function update_product( $request ) {
		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
		}

		$id     = (int) $request['id'];
		$params = $request->get_json_params();

		if ( ! $id || ! get_post( $id ) ) {
			return new \WP_Error( 'invalid_id', 'Invalid product ID', array( 'status' => 404 ) );
		}

		// Update post title safely.
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => sanitize_text_field( $params['Product'] ?? '' ),
			)
		);

		$meta_data = array();

		foreach ( self::PRODUCT_FIELDS as $field ) {
			// If field is BarcodeEAN13 and missing, generate one.
			if ( 'BarcodeEAN13' === $field && empty( $params[ $field ] ) ) {
				$meta_data[ $field ] = self::generate_unique_barcode();
				continue;
			}

			// Skip other missing fields.
			if ( ! isset( $params[ $field ] ) ) {
				continue;
			}

			// Sanitize field value.
			$meta_data[ $field ] = in_array( $field, self::HTML_FIELDS, true )
			? wp_kses_post( $params[ $field ] )
			: sanitize_text_field( $params[ $field ] );
		}

		// Use helper to update all meta at once.
		self::update_product_meta( $id, $meta_data );

		return array(
			'id'   => $id,
			'data' => $params,
		);
	}

	/**
	 * Delete a product.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_REQUEST $request Full details about the request.
	 * @return array|\WP_Error Confirmation of deletion or \WP_Error on failure.
	 */
	public static function delete_product( $request ): array {
		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
		}

		$id = (int) $request['id'];
		if ( ! $id || ! get_post( $id ) ) {
			return new \WP_Error( 'invalid_id', 'Invalid product ID', array( 'status' => 404 ) );
		}
		wp_delete_post( $id, true );
		return array(
			'deleted' => true,
			'id'      => $id,
		);
	}

	/**
	 * Import products from an uploaded XLSX file.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_REQUEST $request Full details about the request.
	 * @return array Summary of import results or \WP_Error on failure.
	 */
	public static function import_products_xlsx( $request ): array {

		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
		}

		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', 'No file uploaded', array( 'status' => 400 ) );
		}

		try {
			$spreadsheet = IOFactory::load( $_FILES['file']['tmp_name'] );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'invalid_file', 'Cannot read Excel file: ' . $e->getMessage(), array( 'status' => 400 ) );
		}

		$sheet    = $spreadsheet->getActiveSheet();
		$headers  = self::get_sheet_headers( $sheet );
		$imported = array();
		$updated  = array();
		$skipped  = 0;

		foreach ( $sheet->getRowIterator( 2 ) as $row ) {
			$row_data = self::read_row_html( $row, $headers );
			$title    = $row_data['Product'] ?? '';
			$plu      = $row_data['PLU'] ?? '';

			if ( empty( $title ) || empty( $plu ) ) {
				++$skipped;
				continue; }

			$existing = get_posts(
				array(
					'post_type'   => 'sbm_product',
					'meta_key'    => 'PLU',
					'meta_value'  => $plu,
					'numberposts' => 1,
				)
			);

			$post_id = $existing ? wp_update_post(
				array(
					'ID'         => $existing[0]->ID,
					'post_title' => $title,
				)
			) : wp_insert_post(
				array(
					'post_title'  => $title,
					'post_type'   => 'sbm_product',
					'post_status' => 'publish',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			self::update_product_meta( $post_id, $row_data );

			$existing ? $updated[] = $post_id : $imported[] = $post_id;
		}

		return array(
			'imported' => count( $imported ),
			'updated'  => count( $updated ),
			'skipped'  => $skipped,
		);
	}

	/**
	 * Export all products to an XLSX file and return it as base64.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_REQUEST $request Full details about the request.
	 * @return array Filename and base64 content of the generated XLSX file or \WP_Error on failure.
	 */
	public static function export_products( $request ) {
		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
		}

		$products = get_posts(
			array(
				'post_type'      => 'sbm_product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		if ( empty( $products ) ) {
			return new \WP_Error( 'no_products', 'No products found', array( 'status' => 404 ) );
		}

		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();

		// Headers.
		foreach ( self::PRODUCT_FIELDS as $i => $h ) {
			$cell = Coordinate::stringFromColumnIndex( $i + 1 ) . '1';
			$sheet->setCellValue( $cell, $h );
		}

		// Data rows.
		foreach ( $products as $row_index => $product ) {
			$row_index += 2;
			foreach ( self::PRODUCT_FIELDS as $col_index => $field ) {
				$cell  = Coordinate::stringFromColumnIndex( $col_index + 1 ) . $row_index;
				$value = 'Product' === $field ? $product->post_title : get_post_meta( $product->ID, $field, true );
				self::apply_value_styles_to_cell( $sheet, $cell, $value );
			}
		}

		$filename = 'products-export-' . gmdate( 'Y-m-d' ) . '.xlsx';

		// Stream file to browser.
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: max-age=0' );

		$writer = new Xlsx( $spreadsheet );
		$writer->save( 'php://output' );
		exit;
	}
	/**
	 * Helper to apply basic value styles (bold, italic, underline, color) from HTML to a spreadsheet cell.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet  The worksheet object.
	 * @param string                                        $coord  The cell coordinate (e.g., 'A1').
	 * @param string                                        $html   The HTML string containing the value and styles.
	 */
	private static function apply_value_styles_to_cell( $sheet, $coord, $html ): void {
		$rich_text = new RichText();

		if ( empty( $html ) ) {
			$html = '<span></span>';
		}

		$dom = new \DOMDocument();
		$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );

		$process_node = function ( $node, $style = array(), $rich_text ) use ( &$process_node ) {
			if ( XML_ELEMENT_NODE === $node->nodeType ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$tag = strtolower( $node->nodeName );//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( 'strong' === $tag ) {
					$style['bold'] = true;
				}
				if ( 'em' === $tag ) {
					$style['italic'] = true;
				}
				if ( 'u' === $tag ) {
					$style['underline'] = true;
				}

				if ( $node->hasAttribute( 'style' ) ) {
					$inline = $node->getAttribute( 'style' );
					if ( preg_match( '/color\s*:\s*#([0-9a-fA-F]{6})/', $inline, $m ) ) {
						$style['color'] = $m[1];
					}
				}

				foreach ( $node->childNodes as $child ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$process_node( $child, $style, $rich_text );
				}
			} elseif ( XML_TEXT_NODE === $node->nodeType ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$text_run = $rich_text->createTextRun( $node->nodeValue ); //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$text_run->getFont()->setBold( ! empty( $style['bold'] ) );
				$text_run->getFont()->setItalic( ! empty( $style['italic'] ) );
				$text_run->getFont()->setUnderline( ! empty( $style['underline'] ) ? Font::UNDERLINE_SINGLE : Font::UNDERLINE_NONE );
				$text_run->getFont()->getColor()->setARGB( 'FF' . ( $style['color'] ?? '000000' ) );
			}
		};

		foreach ( $body->childNodes as $child ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$process_node( $child, array(), $rich_text );
		}

		$sheet->setCellValue( $coord, $rich_text );
	}

	/**
	 * MAP post to Array.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_Post $post The product post object.
	 *
	 * @return array
	 */
	private static function map_post_to_array( $post ): array {
		$meta = get_post_meta( $post->ID );
		$data = array();
		foreach ( self::PRODUCT_FIELDS as $field ) {
			$data[ $field ] = $meta[ $field ][0] ?? '';
		}
		$data['id']      = $post->ID;
		$data['Product'] = $post->post_title;
		return $data;
	}

	/**
	 * Sanitize Product Fields.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $params Products Fields.
	 *
	 * @return array
	 */
	private static function sanitize_product_fields( array $params ): array {
		$data = array();
		foreach ( self::PRODUCT_FIELDS as $field ) {
			$data[ $field ] = in_array( $field, self::HTML_FIELDS, true )
				? wp_kses_post( $params[ $field ] ?? '' )
				: sanitize_text_field( $params[ $field ] ?? '' );
		}
		return $data;
	}

	/**
	 * Update Product Meta.
	 *
	 * @since 1.0.0
	 *
	 * @param  int   $post_id The ID of the product post to update.
	 * @param  array $data An associative array of meta fields and their values to update for the product.
	 */
	private static function update_product_meta( int $post_id, array $data ): void {

		// Update all meta fields.
		foreach ( $data as $field => $value ) {
			update_post_meta( $post_id, $field, $value );
		}
	}

	/**
	 * Generate unique EAN-13 barcode.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function generate_unique_barcode(): string {
		do {
			// Generate 12 random digits.
			$base = str_pad( wp_rand( 0, 999999999999 ), 12, '0', STR_PAD_LEFT );

			// Calculate the EAN-13 check digit.
			$sum = 0;
			for ( $i = 0; $i < 12; $i++ ) {
				$digit = (int) $base[ $i ];
				$sum  += ( 0 === $i % 2 ) ? $digit : $digit * 3;
			}
			$check_digit = ( 10 - ( $sum % 10 ) ) % 10;

			$barcode = $base . $check_digit;

			// Ensure uniqueness.
			$existing = get_posts(
				array(
					'post_type'  => 'sbm_product',
					'meta_key'   => 'BarcodeEAN13',
					'meta_value' => $barcode,
					'fields'     => 'ids',
				)
			);

			$existing_count = count( $existing );

		} while ( $existing_count > 0 );

		return $barcode;
	}

	/**
	 * Get Sheet Headers.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed $sheet Sheet object.
	 *
	 * @return array
	 */
	private static function get_sheet_headers( $sheet ): array {
		$header_row    = $sheet->getRowIterator( 1 )->current();
		$cell_iterator = $header_row->getCellIterator();
		$headers       = array();
		foreach ( $cell_iterator as $cell ) {
			$headers[ $cell->getColumn() ] = trim( $cell->getValue() );
		}
		return $headers;
	}

	/**
	 * Read Row.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed $row Row data.
	 * @param  array $headers Sheet headers to map columns to field names.
	 *
	 * @return array
	 */
	private static function read_row_html( $row, array $headers ): array {
		$row_data      = array();
		$cell_iterator = $row->getCellIterator();
		foreach ( $cell_iterator as $cell ) {
			$col        = $cell->getColumn();
			$field_name = $headers[ $col ] ?? null;
			if ( ! $field_name || ! in_array( $field_name, self::PRODUCT_FIELDS, true ) ) {
				continue;
			}
			$row_data[ $field_name ] = self::get_cell_html( $cell );
		}
		return $row_data;
	}

	/**
	 * Get Cell Html.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed $cell Cell object to extract value and styles from.
	 *
	 * @return string
	 */
	private static function get_cell_html( $cell ): string {
		$value = $cell->getValue();
		if ( $value instanceof RichText ) {
			$html = '';
			foreach ( $value->getRichTextElements() as $el ) {
				$text  = htmlspecialchars( $el->getText() );
				$font  = $el->getFont();
				$color = $font && $font->getColor() ? $font->getColor()->getRGB() : '000000';
				if ( $font && $font->getBold() ) {
					$text = "<strong>$text</strong>";
				}
				if ( $font && $font->getItalic() ) {
					$text = "<em>$text</em>";
				}
				if ( $font && $font->getUnderline() !== Font::UNDERLINE_NONE ) {
					$text = "<u>$text</u>";
				}
				$html .= "<span style='color:#$color'>$text</span>";
			}
			return $html;
		}
		return htmlspecialchars( (string) $value );
	}
}
