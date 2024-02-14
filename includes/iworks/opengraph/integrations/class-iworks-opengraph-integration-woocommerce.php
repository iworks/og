<?php
/**
 * WooCommerce
 * https://wordpress.org/plugins/woocommerce/
 *
 * @since 3.3.0
 */
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'iWorks_OpenGraph_Integration_WooCommerce' ) ) {
	return;
}

class iWorks_OpenGraph_Integration_WooCommerce extends iWorks_OpenGraph_Integrations {

	public function __construct() {
		add_filter( 'og_array', array( $this, 'change' ) );
	}

	public function change( $og ) {
		if ( is_singular() ) {
			$og = $this->add_product_data( $og );
		}
		return $og;
	}

	private function add_product_data( $og ) {
		if ( ! is_singular() ) {
			return $og;
		}
		global $post;
		if ( 'product' !== $post->post_type ) {
			return $og;
		}
		/**
		 * woocommerce product
		 */
		global $woocommerce;
		if ( is_object( $woocommerce ) && version_compare( $woocommerce->version, '3.0', '>=' ) ) {
			$_product = wc_get_product( $post->ID );
			if (
				is_object( $_product )
				&& method_exists( $_product, 'get_regular_price' )
				&& function_exists( 'get_woocommerce_currency' )
			) {
				if ( isset( $og['article'] ) ) {
					unset( $og['article'] );
				}
				$og['og']['type']  = 'product';
				$og['og']['brand'] = '';
				$og['product']     = array(
					'retailer_item_id' => $_product->get_sku(),
					'availability'     => $_product->get_stock_status(),
					'weight'           => $_product->get_weight(),
					'price'            => array(
						'amount'   => $_product->get_regular_price(),
						'currency' => get_woocommerce_currency(),
					),
					'category'         => array(),
				);
				if ( $_product->is_on_sale() ) {
					$og['product']['sale_price'] = array(
						'amount'   => $_product->get_sale_price(),
						'currency' => get_woocommerce_currency(),
					);
					$from                        = $_product->get_date_on_sale_from();
					$to                          = $_product->get_date_on_sale_to();
					if ( ! empty( $from ) || ! empty( $to ) ) {
						$og['product']['sale_price_dates'] = array();
						if ( ! empty( $from ) ) {
							$og['product']['sale_price_dates']['start'] = $from;
						}
						if ( ! empty( $to ) ) {
							$og['product']['sale_price_dates']['end'] = $to;
						}
					}
				}
				/**
				 * Product Categories
				 *
				 * @since 2.9.2
				 */
				$terms = get_the_terms( $post->ID, 'product_cat' );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$og['product']['category'][] = $term->name;
					}
				}
				/**
				 * Product Tags
				 *
				 * @since 2.9.2
				 */
				$terms = get_the_terms( $post->ID, 'product_tag' );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$og['product']['tag'][] = $term->name;
					}
				}
				/**
				 * Product Brand by:
				 * - YITH WooCommerce Brands Add-On
				 *
				 * @since 2.9.2
				 */
				$brand_taxonomies = array(
					'product_brand',
					'berocket_brand',
					'gswcbr_brand',
					'pwb-brand',
					'yith_product_brand',
				);
				foreach ( $brand_taxonomies as $taxonomy ) {
					if ( ! empty( $og['brand'] ) ) {
						continue;
					}
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					$terms = get_the_terms( $post->ID, $taxonomy );
					if ( is_array( $terms ) ) {
						foreach ( $terms as $term ) {
							$og['og']['brand']      = $term->name;
							$og['product']['brand'] = $term->name;
						}
					}
				}
			}
		}
		return $og;
	}
}

