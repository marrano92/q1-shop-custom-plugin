<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_Content_Ideas {

	const CACHE_PREFIX      = 'q1_seo_ideas_';
	const CACHE_EXPIRATION  = WEEK_IN_SECONDS;
	const LOCK_TTL          = 120;
	const CONTEXT_OPTION    = 'q1_seo_site_context';
	const HISTORY_OPTION    = 'q1_seo_ideas_history';
	const MAX_HISTORY       = 20;
	const MAX_CONTEXT_CHARS = 2000;

	/**
	 * @var Q1_Shop_N8n_Client
	 */
	private $n8n_client;

	public function __construct() {
		$this->n8n_client = new Q1_Shop_N8n_Client();

		add_action( 'wp_ajax_q1_seo_analyze_site', array( $this, 'ajax_analyze_site' ) );
		add_action( 'wp_ajax_q1_seo_save_site_context', array( $this, 'ajax_save_site_context' ) );
		add_action( 'wp_ajax_q1_seo_generate_ideas', array( $this, 'ajax_generate_ideas' ) );
		add_action( 'wp_ajax_q1_seo_get_saved_ideas', array( $this, 'ajax_get_saved_ideas' ) );
		add_action( 'wp_ajax_q1_seo_create_idea_draft', array( $this, 'ajax_create_idea_draft' ) );
	}

	/**
	 * Analyze site context using local WordPress data (no n8n call).
	 *
	 * @return array Structured site context.
	 */
	public function analyze_site() {
		$site_metadata = self::get_site_metadata();
		$categories    = Q1_Shop_WC_Context::get_top_categories( 10 );
		$products      = Q1_Shop_WC_Context::get_top_products( 10 );
		$posts         = self::get_recent_posts_context( 10 );

		$parts = array();
		$parts[] = sprintf( 'Sito: %s — %s (%s)', $site_metadata['name'], $site_metadata['description'], $site_metadata['url'] );

		if ( ! empty( $categories ) ) {
			$cat_list = array_map( function ( $c ) {
				return sprintf( '%s (%d prodotti)', $c['name'], $c['count'] );
			}, $categories );
			$parts[] = 'Categorie prodotti: ' . implode( ', ', $cat_list );
		}

		if ( ! empty( $products ) ) {
			$prod_list = array_map( function ( $p ) {
				$cats = ! empty( $p['categories'] ) ? ' [' . implode( ', ', $p['categories'] ) . ']' : '';
				return $p['name'] . $cats;
			}, $products );
			$parts[] = 'Prodotti di punta: ' . implode( '; ', $prod_list );
		}

		if ( ! empty( $posts ) ) {
			$post_list = array_map( function ( $p ) {
				$tags = ! empty( $p['tags'] ) ? ' (tag: ' . implode( ', ', $p['tags'] ) . ')' : '';
				return $p['title'] . $tags;
			}, $posts );
			$parts[] = 'Articoli recenti: ' . implode( '; ', $post_list );
		}

		$text = implode( "\n\n", $parts );

		return array(
			'text'     => $text,
			'raw_data' => array(
				'categories' => $categories,
				'products'   => $products,
				'posts'      => $posts,
				'site'       => $site_metadata,
			),
		);
	}

	/**
	 * AJAX: analyze site context.
	 */
	public function ajax_analyze_site() {
		check_ajax_referer( 'q1_seo_ideas_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		$context = $this->analyze_site();

		wp_send_json_success( $context );
	}

	/**
	 * AJAX: save edited site context.
	 */
	public function ajax_save_site_context() {
		check_ajax_referer( 'q1_seo_ideas_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		$text = isset( $_POST['context_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['context_text'] ) ) : '';

		if ( empty( $text ) ) {
			wp_send_json_error( array(
				'message' => __( 'Il contesto non può essere vuoto.', 'q1-shop-stripe-alert' ),
			) );
		}

		$data = array(
			'text'       => $text,
			'updated_at' => current_time( 'mysql' ),
		);

		update_option( self::CONTEXT_OPTION, $data, false );

		wp_send_json_success( array(
			'message'    => __( 'Contesto salvato con successo.', 'q1-shop-stripe-alert' ),
			'updated_at' => $data['updated_at'],
		) );
	}

	/**
	 * Get previously saved context.
	 *
	 * @return array
	 */
	public static function get_saved_context() {
		return get_option( self::CONTEXT_OPTION, array() );
	}

	/**
	 * Generate content ideas via n8n workflow.
	 *
	 * @param string $context Site context text.
	 * @return array|WP_Error
	 */
	public function generate_ideas( $context ) {
		// Concurrency guard.
		$lock_key = 'q1_seo_ideas_lock';
		if ( get_transient( $lock_key ) ) {
			return new WP_Error(
				'ideas_in_progress',
				__( 'Generazione idee già in corso. Attendi il completamento.', 'q1-shop-stripe-alert' )
			);
		}
		set_transient( $lock_key, true, self::LOCK_TTL );

		// Daily limit check.
		$limit     = (int) Q1_Shop_SEO_Settings::get_option( 'daily_ideas_limit', 5 );
		$today     = gmdate( 'Y-m-d' );
		$count_key = 'q1_seo_ideas_count_' . $today;
		$count     = (int) get_transient( $count_key );

		if ( $count >= $limit ) {
			delete_transient( $lock_key );
			return new WP_Error(
				'daily_ideas_limit_reached',
				sprintf(
					/* translators: %d: daily ideas limit */
					__( 'Limite giornaliero di %d generazioni idee raggiunto. Riprova domani.', 'q1-shop-stripe-alert' ),
					$limit
				)
			);
		}

		// Cache check.
		$cache_key = self::CACHE_PREFIX . md5( $context );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			delete_transient( $lock_key );
			return $cached;
		}

		// Truncate context for AI token limits.
		$truncated_context = mb_substr( $context, 0, self::MAX_CONTEXT_CHARS );

		$payload = array(
			'context'  => $truncated_context,
			'language' => 'it',
			'location' => 'Italy',
		);

		$response = $this->n8n_client->set_timeout( 60 )->send_content_ideas_request( $payload );

		if ( is_wp_error( $response ) ) {
			delete_transient( $lock_key );
			return $response;
		}

		// Validate response structure.
		if ( ! isset( $response['success'] ) || ! $response['success'] || ! isset( $response['ideas'] ) ) {
			delete_transient( $lock_key );
			return new WP_Error(
				'invalid_response',
				__( 'Risposta non valida dal servizio generazione idee.', 'q1-shop-stripe-alert' )
			);
		}

		// Save to history.
		$this->save_ideas_to_history( $response );

		// Cache result.
		set_transient( $cache_key, $response, self::CACHE_EXPIRATION );

		// Increment daily counter.
		set_transient( $count_key, $count + 1, DAY_IN_SECONDS );

		delete_transient( $lock_key );

		return $response;
	}

	/**
	 * AJAX: generate content ideas.
	 */
	public function ajax_generate_ideas() {
		check_ajax_referer( 'q1_seo_ideas_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		// Use saved context or POST context.
		$context = '';
		if ( ! empty( $_POST['context_text'] ) ) {
			$context = sanitize_textarea_field( wp_unslash( $_POST['context_text'] ) );
		} else {
			$saved = self::get_saved_context();
			if ( ! empty( $saved['text'] ) ) {
				$context = $saved['text'];
			}
		}

		if ( empty( $context ) ) {
			wp_send_json_error( array(
				'message' => __( 'Analizza e salva il contesto del sito prima di generare le idee.', 'q1-shop-stripe-alert' ),
			) );
		}

		$result = $this->generate_ideas( $context );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Save ideas session to FIFO history (max 20).
	 *
	 * @param array $response n8n response data.
	 */
	private function save_ideas_to_history( $response ) {
		$history = get_option( self::HISTORY_OPTION, array() );

		$session = array(
			'session_id'     => uniqid( 'ideas_' ),
			'timestamp'      => current_time( 'mysql' ),
			'strategy_notes' => isset( $response['strategy_notes'] ) ? $response['strategy_notes'] : '',
			'ideas'          => isset( $response['ideas'] ) ? $response['ideas'] : array(),
			'meta'           => isset( $response['meta'] ) ? $response['meta'] : array(),
		);

		array_unshift( $history, $session );
		$history = array_slice( $history, 0, self::MAX_HISTORY );

		update_option( self::HISTORY_OPTION, $history, false );
	}

	/**
	 * AJAX: get saved ideas history.
	 */
	public function ajax_get_saved_ideas() {
		check_ajax_referer( 'q1_seo_ideas_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		$history = get_option( self::HISTORY_OPTION, array() );

		wp_send_json_success( array( 'history' => $history ) );
	}

	/**
	 * AJAX: create a draft post from a content idea.
	 */
	public function ajax_create_idea_draft() {
		check_ajax_referer( 'q1_seo_ideas_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		$title           = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$subtitle        = isset( $_POST['subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['subtitle'] ) ) : '';
		$description     = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$primary_keyword = isset( $_POST['primary_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['primary_keyword'] ) ) : '';
		$target_keywords = isset( $_POST['target_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['target_keywords'] ) ) : '';

		if ( empty( $title ) ) {
			wp_send_json_error( array(
				'message' => __( 'Titolo mancante.', 'q1-shop-stripe-alert' ),
			) );
		}

		$post_id = wp_insert_post( array(
			'post_title'   => $title,
			'post_excerpt' => $subtitle,
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_content' => '',
			'meta_input'   => array(
				'_seo_focus_keyword'   => $primary_keyword,
				'_seo_target_keywords' => $target_keywords,
				'_seo_content_brief'   => $description,
			),
		) );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array(
				'message' => $post_id->get_error_message(),
			) );
		}

		wp_send_json_success( array(
			'post_id'  => $post_id,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'message'  => sprintf(
				/* translators: %s: post title */
				__( 'Bozza creata: "%s"', 'q1-shop-stripe-alert' ),
				$title
			),
		) );
	}

	/**
	 * Get recent published posts with metadata.
	 *
	 * @param int $limit Max posts to return.
	 * @return array
	 */
	private static function get_recent_posts_context( $limit ) {
		$posts = get_posts( array(
			'post_type'   => 'post',
			'post_status' => 'publish',
			'numberposts' => absint( $limit ),
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );

		$result = array();
		foreach ( $posts as $post ) {
			$excerpt = $post->post_excerpt;
			if ( empty( $excerpt ) ) {
				$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
			}

			$cat_terms = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
			$tag_terms = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );

			$result[] = array(
				'title'      => $post->post_title,
				'excerpt'    => $excerpt,
				'categories' => is_wp_error( $cat_terms ) ? array() : $cat_terms,
				'tags'       => is_wp_error( $tag_terms ) ? array() : $tag_terms,
			);
		}

		return $result;
	}

	/**
	 * Get basic site metadata.
	 *
	 * @return array
	 */
	private static function get_site_metadata() {
		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => home_url(),
		);
	}
}
