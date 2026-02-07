<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_SEO_Settings {

	const OPTION_GROUP = 'q1_shop_seo_options';
	const OPTION_NAME  = 'q1_shop_seo_settings';
	const SECTION_ID   = 'q1_shop_seo_main_section';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register all settings, section and fields.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array( 'sanitize_callback' => array( $this, 'sanitize_options' ) )
		);

		add_settings_section(
			self::SECTION_ID,
			__( 'Configurazione Servizi', 'q1-shop-stripe-alert' ),
			array( $this, 'render_section_description' ),
			'q1-seo-settings'
		);

		add_settings_field(
			'n8n_base_url',
			__( 'URL base n8n', 'q1-shop-stripe-alert' ),
			array( $this, 'render_field_url' ),
			'q1-seo-settings',
			self::SECTION_ID,
			array(
				'field'       => 'n8n_base_url',
				'description' => __( "URL dell'istanza n8n (es. https://n8n.example.com)", 'q1-shop-stripe-alert' ),
				'placeholder' => 'https://n8n.example.com',
			)
		);

		add_settings_field(
			'n8n_webhook_token',
			__( 'Token Webhook n8n', 'q1-shop-stripe-alert' ),
			array( $this, 'render_field_password' ),
			'q1-seo-settings',
			self::SECTION_ID,
			array(
				'field'       => 'n8n_webhook_token',
				'description' => __( 'Token segreto per autenticazione webhook', 'q1-shop-stripe-alert' ),
			)
		);

		add_settings_field(
			'daily_audit_limit',
			__( 'Limite audit giornaliero', 'q1-shop-stripe-alert' ),
			array( $this, 'render_field_number' ),
			'q1-seo-settings',
			self::SECTION_ID,
			array(
				'field'       => 'daily_audit_limit',
				'default'     => 20,
				'min'         => 1,
				'max'         => 100,
				'description' => __( 'Numero massimo di analisi SEO al giorno', 'q1-shop-stripe-alert' ),
			)
		);

		add_settings_field(
			'daily_keyword_limit',
			__( 'Limite keyword research giornaliero', 'q1-shop-stripe-alert' ),
			array( $this, 'render_field_number' ),
			'q1-seo-settings',
			self::SECTION_ID,
			array(
				'field'       => 'daily_keyword_limit',
				'default'     => 50,
				'min'         => 1,
				'max'         => 200,
				'description' => __( 'Numero massimo di ricerche keyword al giorno', 'q1-shop-stripe-alert' ),
			)
		);
	}

	public function render_section_description() {
		echo '<p>' . esc_html__( 'Configura le credenziali per i servizi esterni utilizzati dal modulo SEO.', 'q1-shop-stripe-alert' ) . '</p>';
	}

	public function render_field_url( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : '';
		printf(
			'<input type="url" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" placeholder="%4$s" />',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value ),
			esc_attr( $args['placeholder'] ?? '' )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	public function render_field_password( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : '';
		printf(
			'<input type="password" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	public function render_field_number( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : ( $args['default'] ?? '' );
		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$d" max="%5$d" class="small-text" />',
			esc_attr( $args['field'] ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value ),
			intval( $args['min'] ?? 0 ),
			intval( $args['max'] ?? 999 )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Sanitize all option values.
	 *
	 * @param array $input Raw form input.
	 * @return array Sanitized values.
	 */
	public function sanitize_options( $input ) {
		$sanitized = array();

		$sanitized['n8n_base_url'] = isset( $input['n8n_base_url'] )
			? esc_url_raw( rtrim( $input['n8n_base_url'], '/' ) )
			: '';

		$sanitized['n8n_webhook_token'] = isset( $input['n8n_webhook_token'] )
			? sanitize_text_field( $input['n8n_webhook_token'] )
			: '';

		$sanitized['daily_audit_limit'] = isset( $input['daily_audit_limit'] )
			? absint( $input['daily_audit_limit'] )
			: 20;
		$sanitized['daily_audit_limit'] = max( 1, min( 100, $sanitized['daily_audit_limit'] ) );

		$sanitized['daily_keyword_limit'] = isset( $input['daily_keyword_limit'] )
			? absint( $input['daily_keyword_limit'] )
			: 50;
		$sanitized['daily_keyword_limit'] = max( 1, min( 200, $sanitized['daily_keyword_limit'] ) );

		return $sanitized;
	}

	/**
	 * Retrieve a single option value.
	 *
	 * @param string $key     Option key inside the settings array.
	 * @param mixed  $default Default value if key is missing.
	 * @return mixed
	 */
	public static function get_option( $key, $default = '' ) {
		$options = get_option( self::OPTION_NAME, array() );
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * Get daily usage stats for the budget widget.
	 *
	 * @return array
	 */
	public static function get_usage_stats() {
		$today         = gmdate( 'Y-m-d' );
		$audit_limit   = (int) self::get_option( 'daily_audit_limit', 20 );
		$keyword_limit = (int) self::get_option( 'daily_keyword_limit', 50 );

		return array(
			'keyword_today' => (int) get_transient( 'q1_seo_kw_count_' . $today ),
			'keyword_limit' => $keyword_limit,
			'audit_today'   => (int) get_transient( 'q1_seo_audit_count_' . $today ),
			'audit_limit'   => $audit_limit,
			'date'          => $today,
		);
	}
}
