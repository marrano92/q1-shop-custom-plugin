<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Q1_Shop_SEO_Assistant {

	/**
	 * @var string
	 */
	private $plugin_file;

	/**
	 * @var Q1_Shop_N8n_Client|null
	 */
	private $n8n_client;

	/**
	 * @param string $plugin_file Main plugin file path.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		// N8n_Client AJAX registration — guarded until issue #03 delivers the class.
		if ( class_exists( 'Q1_Shop_N8n_Client' ) ) {
			$this->n8n_client = new Q1_Shop_N8n_Client();
			add_action( 'wp_ajax_q1_seo_test_n8n_connection', array( $this->n8n_client, 'ajax_test_connection' ) );
		}
	}

	/**
	 * Register top-level menu and submenus.
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'AI SEO Assistant', 'q1-shop-stripe-alert' ),
			__( 'AI SEO Assistant', 'q1-shop-stripe-alert' ),
			'manage_options',
			'q1-seo-assistant',
			array( $this, 'render_keyword_research_page' ),
			'dashicons-search',
			30
		);

		// Sottomenu 1: Keyword Research (sovrascrive il primo item duplicato).
		add_submenu_page(
			'q1-seo-assistant',
			__( 'Keyword Research', 'q1-shop-stripe-alert' ),
			__( 'Keyword Research', 'q1-shop-stripe-alert' ),
			'manage_options',
			'q1-seo-assistant',
			array( $this, 'render_keyword_research_page' )
		);

		// Sottomenu 2: SEO Audit.
		add_submenu_page(
			'q1-seo-assistant',
			__( 'SEO Audit', 'q1-shop-stripe-alert' ),
			__( 'SEO Audit', 'q1-shop-stripe-alert' ),
			'manage_options',
			'q1-seo-audit',
			array( $this, 'render_seo_audit_page' )
		);

		// Sottomenu 3: Impostazioni.
		add_submenu_page(
			'q1-seo-assistant',
			__( 'Impostazioni', 'q1-shop-stripe-alert' ),
			__( 'Impostazioni', 'q1-shop-stripe-alert' ),
			'manage_options',
			'q1-seo-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render Keyword Research page (placeholder — issue #10).
	 */
	public function render_keyword_research_page() {
		$template = Q1_SHOP_SEO_PATH . 'templates/admin/keyword-research-page.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Keyword Research', 'q1-shop-stripe-alert' ) . '</h1>'
				. '<p>' . esc_html__( 'Pagina in costruzione.', 'q1-shop-stripe-alert' ) . '</p></div>';
		}
	}

	/**
	 * Render SEO Audit page (placeholder — issue #17).
	 */
	public function render_seo_audit_page() {
		$template = Q1_SHOP_SEO_PATH . 'templates/admin/seo-audit-page.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'SEO Audit', 'q1-shop-stripe-alert' ) . '</h1>'
				. '<p>' . esc_html__( 'Pagina in costruzione.', 'q1-shop-stripe-alert' ) . '</p></div>';
		}
	}

	/**
	 * Render Settings page (placeholder — issue #02).
	 */
	public function render_settings_page() {
		$template = Q1_SHOP_SEO_PATH . 'templates/admin/settings-page.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Impostazioni SEO', 'q1-shop-stripe-alert' ) . '</h1>'
				. '<p>' . esc_html__( 'Pagina in costruzione.', 'q1-shop-stripe-alert' ) . '</p></div>';
		}
	}
}
