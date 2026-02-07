<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_N8n_Client {

	const DEFAULT_TIMEOUT  = 30;
	const MAX_ATTEMPTS     = 3;
	const ATTEMPT_TIMEOUT  = 10;

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
	 * Execute a POST request to n8n with retry on transport/server errors.
	 *
	 * Retry strategy: immediate retry (no sleep) with reduced per-attempt timeout
	 * so total wall time stays under ~30s (ATTEMPT_TIMEOUT × MAX_ATTEMPTS).
	 *
	 * @param string $endpoint Relative webhook path.
	 * @param array  $payload  Data to send as JSON.
	 * @param int    $attempt  Current attempt number (1-based).
	 * @return array|WP_Error  Decoded response or error.
	 */
	private function post( $endpoint, $payload, $attempt = 1 ) {
		if ( empty( $this->base_url ) ) {
			$error = new WP_Error(
				'n8n_not_configured',
				$this->get_user_message( 'n8n_not_configured' )
			);
			Q1_Shop_SEO_Logger::error( 'n8n non configurato', array( 'endpoint' => $endpoint ) );
			return $error;
		}

		$url             = rtrim( $this->base_url, '/' ) . $endpoint;
		$attempt_timeout = min( $this->timeout, self::ATTEMPT_TIMEOUT );
		$start_time      = microtime( true );

		$args = array(
			'method'  => 'POST',
			'timeout' => $attempt_timeout,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->token,
			),
			'body'    => wp_json_encode( $payload ),
		);

		$response = wp_remote_post( $url, $args );

		// Transport error — retry immediately.
		if ( is_wp_error( $response ) ) {
			if ( $attempt < self::MAX_ATTEMPTS ) {
				Q1_Shop_SEO_Logger::warning( 'Retry n8n (tentativo ' . $attempt . ')', array(
					'endpoint' => $endpoint,
					'error'    => $response->get_error_message(),
				) );
				return $this->post( $endpoint, $payload, $attempt + 1 );
			}
			$duration_ms = round( ( microtime( true ) - $start_time ) * 1000 );
			$error       = new WP_Error(
				'n8n_connection_error',
				sprintf(
					/* translators: 1: number of attempts, 2: error message */
					__( 'Impossibile connettersi a n8n dopo %1$d tentativi: %2$s', 'q1-shop-stripe-alert' ),
					self::MAX_ATTEMPTS,
					$response->get_error_message()
				)
			);
			Q1_Shop_SEO_Logger::api_call( $endpoint, $payload, $error, $duration_ms );
			return $error;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		// Server error (5xx) — retry immediately.
		if ( $status_code >= 500 && $attempt < self::MAX_ATTEMPTS ) {
			Q1_Shop_SEO_Logger::warning( 'Retry n8n su HTTP ' . $status_code . ' (tentativo ' . $attempt . ')', array(
				'endpoint' => $endpoint,
			) );
			return $this->post( $endpoint, $payload, $attempt + 1 );
		}

		$duration_ms = round( ( microtime( true ) - $start_time ) * 1000 );

		// Client error (4xx) — no retry, configuration/request problem.
		if ( $status_code < 200 || $status_code >= 300 ) {
			$error = new WP_Error(
				'n8n_http_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: HTTP status message */
					__( 'n8n ha risposto con errore HTTP %1$d: %2$s', 'q1-shop-stripe-alert' ),
					$status_code,
					wp_remote_retrieve_response_message( $response )
				)
			);
			Q1_Shop_SEO_Logger::api_call( $endpoint, $payload, $error, $duration_ms );
			return $error;
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error = new WP_Error(
				'n8n_json_error',
				sprintf(
					/* translators: %s: preview of the raw response body */
					__( 'Risposta n8n non è un JSON valido. Anteprima body: %s', 'q1-shop-stripe-alert' ),
					substr( $body, 0, 300 )
				)
			);
			Q1_Shop_SEO_Logger::api_call( $endpoint, $payload, $error, $duration_ms );
			return $error;
		}

		// n8n can wrap the response in an array — unwrap first element.
		if ( isset( $data[0] ) && ! isset( $data['success'] ) && is_array( $data[0] ) ) {
			$data = $data[0];
		}

		Q1_Shop_SEO_Logger::api_call( $endpoint, $payload, $data, $duration_ms );
		return $data;
	}

	/**
	 * Map error codes to user-friendly Italian messages.
	 *
	 * @param string $error_code WP_Error code.
	 * @return string Localized message.
	 */
	private function get_user_message( $error_code ) {
		$messages = array(
			'n8n_not_configured'   => __( 'URL base n8n non configurato. Vai in AI SEO Assistant > Impostazioni.', 'q1-shop-stripe-alert' ),
			'n8n_connection_error' => __( 'Impossibile connettersi a n8n. Verifica che il servizio sia attivo e raggiungibile.', 'q1-shop-stripe-alert' ),
			'n8n_http_error'       => __( 'Il servizio n8n ha risposto con un errore. Controlla i log di n8n.', 'q1-shop-stripe-alert' ),
			'n8n_json_error'       => __( 'Risposta n8n non valida. Verifica la configurazione del workflow.', 'q1-shop-stripe-alert' ),
		);
		return isset( $messages[ $error_code ] )
			? $messages[ $error_code ]
			: __( 'Errore sconosciuto. Riprova.', 'q1-shop-stripe-alert' );
	}
}
