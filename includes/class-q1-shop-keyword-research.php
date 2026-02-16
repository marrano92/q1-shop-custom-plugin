<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_Keyword_Research {

	const CACHE_EXPIRATION = DAY_IN_SECONDS;
	const CACHE_PREFIX     = 'q1_seo_kw_';

	/**
	 * @var Q1_Shop_N8n_Client
	 */
	private $n8n_client;

	public function __construct() {
		$this->n8n_client = new Q1_Shop_N8n_Client();

		add_action( 'wp_ajax_q1_seo_keyword_research', array( $this, 'ajax_research' ) );
		add_action( 'wp_ajax_q1_seo_create_keyword_draft', array( $this, 'ajax_create_draft' ) );
	}

	/**
	 * Execute keyword research via n8n workflow.
	 *
	 * @param string $keyword_seed  Seed keyword.
	 * @param bool   $use_auto_context Whether to include WooCommerce context.
	 * @return array|WP_Error
	 */
	public function research( $keyword_seed, $use_auto_context = false ) {
		$keyword_seed = sanitize_text_field( $keyword_seed );

		if ( empty( $keyword_seed ) ) {
			return new WP_Error(
				'empty_keyword',
				__( 'Inserisci una parola chiave.', 'q1-shop-stripe-alert' )
			);
		}

		// Check cache.
		$cache_key = self::CACHE_PREFIX . md5( $keyword_seed . ( $use_auto_context ? '_ctx' : '' ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Concurrency guard: block parallel requests for the same keyword.
		$lock_key = 'q1_seo_kw_lock_' . md5( $keyword_seed );
		if ( get_transient( $lock_key ) ) {
			return new WP_Error(
				'research_in_progress',
				__( 'Ricerca già in corso per questa keyword. Attendi il completamento.', 'q1-shop-stripe-alert' )
			);
		}
		set_transient( $lock_key, true, 60 );

		// Check daily keyword limit.
		$kw_limit     = (int) Q1_Shop_SEO_Settings::get_option( 'daily_keyword_limit', 50 );
		$kw_count_key = 'q1_seo_kw_count_' . gmdate( 'Y-m-d' );
		$kw_count     = (int) get_transient( $kw_count_key );

		if ( $kw_count >= $kw_limit ) {
			delete_transient( $lock_key );
			return new WP_Error(
				'daily_kw_limit_reached',
				sprintf(
					/* translators: %d: daily keyword limit */
					__( 'Limite giornaliero di %d ricerche keyword raggiunto. Riprova domani.', 'q1-shop-stripe-alert' ),
					$kw_limit
				)
			);
		}

		// Build payload.
		$payload = array(
			'keyword_seed' => $keyword_seed,
			'context'      => $use_auto_context ? Q1_Shop_WC_Context::get_site_context() : '',
			'language'     => 'it',
			'location'     => 'Italy',
		);

		$response = $this->n8n_client->set_timeout( 40 )->send_keyword_request( $payload );

		if ( is_wp_error( $response ) ) {
			delete_transient( $lock_key );
			return $response;
		}

		// Validate response structure.
		if ( ! isset( $response['success'] ) || ! $response['success'] || ! isset( $response['keywords'] ) ) {
			delete_transient( $lock_key );
			return new WP_Error(
				'invalid_response',
				__( 'Risposta non valida dal servizio keyword research.', 'q1-shop-stripe-alert' )
			);
		}

		delete_transient( $lock_key );

		// Increment daily keyword counter.
		set_transient( $kw_count_key, $kw_count + 1, DAY_IN_SECONDS );

		// Cache successful result.
		set_transient( $cache_key, $response, self::CACHE_EXPIRATION );

		return $response;
	}

	/**
	 * AJAX handler for keyword research.
	 */
	public function ajax_research() {
		check_ajax_referer( 'q1_seo_keyword_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		$keyword_seed     = isset( $_POST['keyword_seed'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword_seed'] ) ) : '';
		$use_auto_context = isset( $_POST['use_auto_context'] ) && '1' === $_POST['use_auto_context'];

		$result = $this->research( $keyword_seed, $use_auto_context );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: create a draft post from a selected keyword.
	 */
	public function ajax_create_draft() {
		check_ajax_referer( 'q1_seo_keyword_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		$keyword      = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$all_keywords = isset( $_POST['all_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['all_keywords'] ) ) : '';

		if ( empty( $keyword ) ) {
			wp_send_json_error( array(
				'message' => __( 'Keyword mancante.', 'q1-shop-stripe-alert' ),
			) );
		}

		$title = ucfirst( $keyword );

		$post_id = wp_insert_post( array(
			'post_title'   => $title,
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_content' => '',
			'meta_input'   => array(
				'_seo_focus_keyword'    => $keyword,
				'_seo_related_keywords' => $all_keywords,
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
}
