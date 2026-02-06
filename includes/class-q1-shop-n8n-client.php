<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_N8n_Client {

	const DEFAULT_TIMEOUT = 30;

	const ENDPOINT_KEYWORD_RESEARCH = '/webhook/seo-keyword-research';
	const ENDPOINT_SEO_AUDIT        = '/webhook/seo-audit';
	const ENDPOINT_TEST             = '/webhook/seo-test';

	/**
	 * @var string
	 */
	private $base_url;

	/**
	 * @var string
	 */
	private $token;

	/**
	 * @var int
	 */
	private $timeout;

	/**
	 * Pure HTTP service — no hooks registered here.
	 * AJAX registration is handled by Q1_Shop_SEO_Assistant (#01).
	 */
	public function __construct() {
		$this->base_url = Q1_Shop_SEO_Settings::get_option( 'n8n_base_url', '' );
		$this->token    = Q1_Shop_SEO_Settings::get_option( 'n8n_webhook_token', '' );
		$this->timeout  = self::DEFAULT_TIMEOUT;
	}

	/**
	 * Send keyword research request to n8n workflow.
	 *
	 * @param array $payload {keyword_seed, context, language, location}.
	 * @return array|WP_Error
	 */
	public function send_keyword_request( $payload ) {
		return $this->post( self::ENDPOINT_KEYWORD_RESEARCH, $payload );
	}

	/**
	 * Send SEO audit request to n8n workflow.
	 *
	 * @param array $payload {title, content, meta_description, slug, images, internal_links, keyword_focus, …}.
	 * @return array|WP_Error
	 */
	public function send_audit_request( $payload ) {
		return $this->post( self::ENDPOINT_SEO_AUDIT, $payload );
	}

	/**
	 * Test n8n reachability.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->base_url ) ) {
			return new WP_Error(
				'missing_url',
				__( 'URL n8n non configurato.', 'q1-shop-stripe-alert' )
			);
		}

		$response = $this->post( self::ENDPOINT_TEST, array( 'action' => 'ping' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * AJAX handler for test connection (called by Q1_Shop_SEO_Assistant hook).
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'q1_seo_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permesso negato.', 'q1-shop-stripe-alert' ),
			) );
		}

		// Reload options in case they were just saved.
		$this->base_url = Q1_Shop_SEO_Settings::get_option( 'n8n_base_url', '' );
		$this->token    = Q1_Shop_SEO_Settings::get_option( 'n8n_webhook_token', '' );

		$result = $this->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			) );
		}

		wp_send_json_success( array(
			'message' => __( 'Connessione a n8n riuscita!', 'q1-shop-stripe-alert' ),
		) );
	}

	/**
	 * Set a custom timeout for the next request.
	 *
	 * @param int $seconds Timeout in seconds.
	 * @return self
	 */
	public function set_timeout( $seconds ) {
		$this->timeout = absint( $seconds );
		return $this;
	}

	/**
	 * Execute a POST request to n8n.
	 *
	 * @param string $endpoint Relative webhook path.
	 * @param array  $payload  Data to send as JSON.
	 * @return array|WP_Error  Decoded response or error.
	 */
	private function post( $endpoint, $payload ) {
		if ( empty( $this->base_url ) ) {
			return new WP_Error(
				'n8n_not_configured',
				__( 'URL base n8n non configurato. Vai in Impostazioni.', 'q1-shop-stripe-alert' )
			);
		}

		$url = rtrim( $this->base_url, '/' ) . $endpoint;

		$args = array(
			'method'  => 'POST',
			'timeout' => $this->timeout,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->token,
			),
			'body'    => wp_json_encode( $payload ),
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'n8n_connection_error',
				sprintf(
					/* translators: %s: error message from wp_remote_post */
					__( 'Errore di connessione a n8n: %s', 'q1-shop-stripe-alert' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'n8n_http_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: HTTP status message */
					__( 'n8n ha risposto con errore HTTP %1$d: %2$s', 'q1-shop-stripe-alert' ),
					$status_code,
					wp_remote_retrieve_response_message( $response )
				)
			);
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'n8n_json_error',
				__( 'Risposta n8n non è un JSON valido.', 'q1-shop-stripe-alert' )
			);
		}

		return $data;
	}
}
