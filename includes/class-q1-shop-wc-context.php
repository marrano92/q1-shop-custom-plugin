<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_WC_Context {

	/**
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Top product categories ordered by product count.
	 *
	 * @param int $limit Max categories to return.
	 * @return array Array of {name, slug, count}.
	 */
	public static function get_top_categories( $limit = 10 ) {
		if ( ! self::is_woocommerce_active() ) {
			return array();
		}

		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => absint( $limit ),
			'hide_empty' => true,
		) );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$categories = array();
		foreach ( $terms as $term ) {
			$categories[] = array(
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => $term->count,
			);
		}

		return $categories;
	}

	/**
	 * Top products by popularity (HPOS-compatible), fallback to date.
	 *
	 * @param int $limit Max products to return.
	 * @return array Array of {id, name, slug, price, categories}.
	 */
	public static function get_top_products( $limit = 10 ) {
		if ( ! self::is_woocommerce_active() ) {
			return array();
		}

		$args = array(
			'limit'   => absint( $limit ),
			'orderby' => 'popularity',
			'order'   => 'DESC',
			'status'  => 'publish',
			'return'  => 'objects',
		);

		$query    = new WC_Product_Query( $args );
		$products = $query->get_products();

		if ( empty( $products ) ) {
			$args['orderby'] = 'date';
			$query           = new WC_Product_Query( $args );
			$products        = $query->get_products();
		}

		$result = array();
		foreach ( $products as $product ) {
			$cat_terms = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );

			$result[] = array(
				'id'         => $product->get_id(),
				'name'       => $product->get_name(),
				'slug'       => $product->get_slug(),
				'price'      => $product->get_price(),
				'categories' => is_wp_error( $cat_terms ) ? array() : $cat_terms,
			);
		}

		return $result;
	}

	/**
	 * Build a human-readable context string for AI prompts.
	 *
	 * @return string
	 */
	public static function get_site_context() {
		$parts = array();

		$categories = self::get_top_categories( 5 );
		if ( ! empty( $categories ) ) {
			$cat_names = array_map( function ( $c ) {
				return $c['name'];
			}, $categories );
			$parts[]   = 'Categorie principali: ' . implode( ', ', $cat_names );
		}

		$products = self::get_top_products( 5 );
		if ( ! empty( $products ) ) {
			$prod_names = array_map( function ( $p ) {
				return $p['name'];
			}, $products );
			$parts[]    = 'Prodotti di punta: ' . implode( ', ', $prod_names );
		}

		if ( empty( $parts ) ) {
			$parts[] = 'Sito e-commerce generico';
		}

		$parts[] = 'Sito: ' . get_bloginfo( 'name' );

		return implode( '. ', $parts ) . '.';
	}
}
