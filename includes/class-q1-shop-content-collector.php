<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_Content_Collector {

	/**
	 * Collect all SEO-relevant data from a post or WooCommerce product.
	 *
	 * @param int $post_id Post or product ID.
	 * @return array|WP_Error Structured data or error.
	 */
	public static function collect( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Articolo non trovato.', 'q1-shop-stripe-alert' ) );
		}

		$content_html = apply_filters( 'the_content', $post->post_content );
		$content_text = wp_strip_all_tags( $content_html );

		return array(
			'post_id'          => $post->ID,
			'post_type'        => $post->post_type,
			'title'            => $post->post_title,
			'content'          => $content_html,
			'content_text'     => $content_text,
			'excerpt'          => $post->post_excerpt,
			'slug'             => $post->post_name,
			'meta_description' => self::get_meta_description( $post->ID ),
			'keyword_focus'    => self::get_focus_keyword( $post->ID ),
			'categories'       => self::get_categories( $post ),
			'tags'             => self::get_tags( $post ),
			'images'           => self::extract_images( $content_html ),
			'internal_links'   => self::extract_links( $content_html, 'internal' ),
			'external_links'   => self::extract_links( $content_html, 'external' ),
			'word_count'       => str_word_count( $content_text ),
			'headings'         => self::count_headings( $content_html ),
			'paragraphs'       => self::count_paragraphs( $content_html ),
		);
	}

	/**
	 * Get meta description (Yoast, Rank Math, AIOSEO compatible).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function get_meta_description( $post_id ) {
		$yoast = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		if ( ! empty( $yoast ) ) {
			return $yoast;
		}

		$rankmath = get_post_meta( $post_id, 'rank_math_description', true );
		if ( ! empty( $rankmath ) ) {
			return $rankmath;
		}

		$aioseo = get_post_meta( $post_id, '_aioseo_description', true );
		if ( ! empty( $aioseo ) ) {
			return $aioseo;
		}

		return '';
	}

	/**
	 * Get focus keyword (custom meta, Yoast, Rank Math compatible).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function get_focus_keyword( $post_id ) {
		$custom = get_post_meta( $post_id, '_seo_focus_keyword', true );
		if ( ! empty( $custom ) ) {
			return $custom;
		}

		$yoast = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		if ( ! empty( $yoast ) ) {
			return $yoast;
		}

		$rankmath = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		if ( ! empty( $rankmath ) ) {
			return $rankmath;
		}

		return '';
	}

	/**
	 * Get post categories (product_cat for WC products).
	 *
	 * @param WP_Post $post Post object.
	 * @return array Category names.
	 */
	private static function get_categories( $post ) {
		$taxonomy = ( 'product' === $post->post_type ) ? 'product_cat' : 'category';
		$terms    = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Get post tags (product_tag for WC products).
	 *
	 * @param WP_Post $post Post object.
	 * @return array Tag names.
	 */
	private static function get_tags( $post ) {
		$taxonomy = ( 'product' === $post->post_type ) ? 'product_tag' : 'post_tag';
		$terms    = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Extract images from HTML content via DOMDocument.
	 *
	 * @param string $html Content HTML.
	 * @return array Array of {src, alt, width, height}.
	 */
	private static function extract_images( $html ) {
		$images = array();
		if ( empty( $html ) ) {
			return $images;
		}

		$dom = new DOMDocument();
		@$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$img_tags = $dom->getElementsByTagName( 'img' );
		foreach ( $img_tags as $img ) {
			$images[] = array(
				'src'    => $img->getAttribute( 'src' ),
				'alt'    => $img->getAttribute( 'alt' ),
				'width'  => $img->getAttribute( 'width' ) ? (int) $img->getAttribute( 'width' ) : 0,
				'height' => $img->getAttribute( 'height' ) ? (int) $img->getAttribute( 'height' ) : 0,
			);
		}

		return $images;
	}

	/**
	 * Extract links from HTML content.
	 *
	 * @param string $html Content HTML.
	 * @param string $type 'internal' or 'external'.
	 * @return array Array of {url, text, type}.
	 */
	private static function extract_links( $html, $type = 'internal' ) {
		$links = array();
		if ( empty( $html ) ) {
			return $links;
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$dom       = new DOMDocument();
		@$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$a_tags = $dom->getElementsByTagName( 'a' );
		foreach ( $a_tags as $a ) {
			$href = $a->getAttribute( 'href' );
			if ( empty( $href ) || 0 === strpos( $href, '#' ) ) {
				continue;
			}

			$link_host   = wp_parse_url( $href, PHP_URL_HOST );
			$is_internal = empty( $link_host ) || $link_host === $site_host;

			if ( ( 'internal' === $type && $is_internal ) || ( 'external' === $type && ! $is_internal ) ) {
				$links[] = array(
					'url'  => $href,
					'text' => trim( $a->textContent ),
					'type' => $is_internal ? 'internal' : 'external',
				);
			}
		}

		return $links;
	}

	/**
	 * Count heading tags in HTML content.
	 *
	 * @param string $html Content HTML.
	 * @return array {h2: int, h3: int, h4: int}.
	 */
	private static function count_headings( $html ) {
		$headings = array( 'h2' => 0, 'h3' => 0, 'h4' => 0 );
		if ( empty( $html ) ) {
			return $headings;
		}

		foreach ( array( 'h2', 'h3', 'h4' ) as $tag ) {
			preg_match_all( '/<' . $tag . '[\s>]/i', $html, $matches );
			$headings[ $tag ] = count( $matches[0] );
		}

		return $headings;
	}

	/**
	 * Count paragraphs in HTML content.
	 *
	 * @param string $html Content HTML.
	 * @return int
	 */
	private static function count_paragraphs( $html ) {
		if ( empty( $html ) ) {
			return 0;
		}
		preg_match_all( '/<p[\s>]/i', $html, $matches );
		return count( $matches[0] );
	}
}
