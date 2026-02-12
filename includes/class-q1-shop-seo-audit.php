<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_SEO_Audit {

	const TRANSIENT_PREFIX = 'q1_seo_audit_count_';

	/**
	 * @var Q1_Shop_N8n_Client
	 */
	private $n8n_client;

	public function __construct() {
		$this->n8n_client = new Q1_Shop_N8n_Client();

		add_action( 'wp_ajax_q1_seo_audit_analyze', array( $this, 'ajax_analyze' ) );
		add_action( 'wp_ajax_q1_seo_audit_search_posts', array( $this, 'ajax_search_posts' ) );
	}

	/**
	 * Run SEO audit on a post or product.
	 *
	 * @param int $post_id Post ID.
	 * @return array|WP_Error Audit results or error.
	 */
	public function audit( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return new WP_Error( 'invalid_post_id', __( 'ID articolo non valido.', 'q1-shop-stripe-alert' ) );
		}

		// Concurrency guard.
		$lock_key = 'q1_seo_audit_lock_' . $post_id;
		if ( get_transient( $lock_key ) ) {
			return new WP_Error(
				'audit_in_progress',
				__( 'Analisi già in corso per questo contenuto. Attendi il completamento.', 'q1-shop-stripe-alert' )
			);
		}
		set_transient( $lock_key, true, 180 );

		// Check daily limit.
		$limit_check = $this->check_daily_limit();
		if ( is_wp_error( $limit_check ) ) {
			delete_transient( $lock_key );
			return $limit_check;
		}

		// Collect content data.
		$content_data = Q1_Shop_Content_Collector::collect( $post_id );
		if ( is_wp_error( $content_data ) ) {
			delete_transient( $lock_key );
			return $content_data;
		}

		// Send to n8n — n8n audit workflows may take up to 2 minutes.
		$this->n8n_client->set_timeout( 120 );
		$response = $this->n8n_client->send_audit_request( $content_data );
		if ( is_wp_error( $response ) ) {
			delete_transient( $lock_key );
			return $response;
		}

		// Validate response.
		if ( ! isset( $response['success'] ) || ! $response['success'] ) {
			delete_transient( $lock_key );
			return new WP_Error(
				'invalid_response',
				__( 'Risposta non valida dal servizio audit SEO.', 'q1-shop-stripe-alert' )
			);
		}

		// Increment daily counter.
		$this->increment_daily_count();

		// Release lock.
		delete_transient( $lock_key );

		// Save last audit to post meta.
		update_post_meta( $post_id, '_q1_seo_last_audit', array(
			'score'           => isset( $response['score'] ) ? absint( $response['score'] ) : 0,
			'recommendations' => isset( $response['recommendations'] ) ? $response['recommendations'] : array(),
			'timestamp'       => current_time( 'mysql' ),
		) );

		return $response;
	}

	/**
	 * AJAX handler for SEO audit.
	 */
	public function ajax_analyze() {
		check_ajax_referer( 'q1_seo_audit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$result  = $this->audit( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for post search (used by dedicated audit page #17).
	 */
	public function ajax_search_posts() {
		check_ajax_referer( 'q1_seo_audit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		if ( strlen( $query ) < 2 ) {
			wp_send_json_success( array( 'posts' => array() ) );
			return;
		}

		$posts = get_posts( array(
			'post_type'   => array( 'post', 'product' ),
			'post_status' => array( 'publish', 'draft', 'pending' ),
			's'           => $query,
			'numberposts' => 20,
		) );

		$results = array();
		foreach ( $posts as $post ) {
			$results[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
				'type'  => $post->post_type,
			);
		}

		wp_send_json_success( array( 'posts' => $results ) );
	}

	/**
	 * Check if the daily audit limit has been reached.
	 *
	 * @return true|WP_Error
	 */
	private function check_daily_limit() {
		$limit = (int) Q1_Shop_SEO_Settings::get_option( 'daily_audit_limit', 20 );
		$today = gmdate( 'Y-m-d' );
		$count = (int) get_transient( self::TRANSIENT_PREFIX . $today );

		if ( $count >= $limit ) {
			return new WP_Error(
				'daily_limit_reached',
				sprintf(
					/* translators: %d: daily audit limit */
					__( 'Limite giornaliero di %d analisi raggiunto. Riprova domani.', 'q1-shop-stripe-alert' ),
					$limit
				)
			);
		}

		return true;
	}

	/**
	 * Increment the daily audit counter.
	 */
	private function increment_daily_count() {
		$today = gmdate( 'Y-m-d' );
		$key   = self::TRANSIENT_PREFIX . $today;
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
	}

	/**
	 * Get today's audit count.
	 *
	 * @return int
	 */
	public function get_today_count() {
		$today = gmdate( 'Y-m-d' );
		return (int) get_transient( self::TRANSIENT_PREFIX . $today );
	}

	/**
	 * Get last audit result for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Audit data or null.
	 */
	public static function get_last_audit( $post_id ) {
		$audit = get_post_meta( $post_id, '_q1_seo_last_audit', true );
		return ! empty( $audit ) ? $audit : null;
	}
}
