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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_seo_metabox' ) );
		add_action( 'admin_notices', array( $this, 'show_config_warnings' ) );

		// N8n_Client AJAX registration — guarded until issue #03 delivers the class.
		if ( class_exists( 'Q1_Shop_N8n_Client' ) ) {
			$this->n8n_client = new Q1_Shop_N8n_Client();
			add_action( 'wp_ajax_q1_seo_test_n8n_connection', array( $this->n8n_client, 'ajax_test_connection' ) );
		}
	}

	/**
	 * Show admin notices for missing configuration on SEO pages only.
	 */
	public function show_config_warnings() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'q1-seo' ) === false ) {
			return;
		}

		$settings_url = esc_url( admin_url( 'admin.php?page=q1-seo-settings' ) );

		$n8n_url = Q1_Shop_SEO_Settings::get_option( 'n8n_base_url', '' );
		if ( empty( $n8n_url ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				sprintf(
					/* translators: 1: opening link tag, 2: closing link tag */
					esc_html__( 'AI SEO Assistant: URL n8n non configurato. %1$sVai alle Impostazioni%2$s.', 'q1-shop-stripe-alert' ),
					'<a href="' . $settings_url . '">',
					'</a>'
				)
			);
		}

		$n8n_token = Q1_Shop_SEO_Settings::get_option( 'n8n_webhook_token', '' );
		if ( empty( $n8n_token ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				sprintf(
					/* translators: 1: opening link tag, 2: closing link tag */
					esc_html__( 'AI SEO Assistant: Token webhook n8n non configurato. %1$sVai alle Impostazioni%2$s.', 'q1-shop-stripe-alert' ),
					'<a href="' . $settings_url . '">',
					'</a>'
				)
			);
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
	 * Enqueue CSS/JS only on SEO Assistant admin pages.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		// SEO Assistant admin pages.
		$seo_pages = array(
			'toplevel_page_q1-seo-assistant',
			'ai-seo-assistant_page_q1-seo-audit',
			'ai-seo-assistant_page_q1-seo-settings',
		);

		$is_seo_page = in_array( $hook, $seo_pages, true );

		// Post/product editor pages (for metabox).
		$screen         = get_current_screen();
		$is_editor_page = $screen
			&& in_array( $screen->base, array( 'post', 'post-new' ), true )
			&& in_array( $screen->post_type, array( 'post', 'product' ), true );

		if ( ! $is_seo_page && ! $is_editor_page ) {
			return;
		}

		wp_enqueue_style(
			'q1-seo-assistant-admin',
			Q1_SHOP_SEO_URL . 'assets/css/seo-assistant-admin.css',
			array(),
			Q1_SHOP_SEO_VERSION
		);

		// Keyword Research JS (only on KW page).
		if ( 'toplevel_page_q1-seo-assistant' === $hook ) {
			wp_enqueue_script(
				'q1-seo-keyword-research',
				Q1_SHOP_SEO_URL . 'assets/js/keyword-research.js',
				array( 'jquery' ),
				Q1_SHOP_SEO_VERSION,
				true
			);

			wp_localize_script( 'q1-seo-keyword-research', 'q1SeoKeyword', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'q1_seo_keyword_nonce' ),
				'strings' => array(
					'search'          => __( 'Cerca Keywords', 'q1-shop-stripe-alert' ),
					'searching'       => __( 'Ricerca in corso...', 'q1-shop-stripe-alert' ),
					'emptyKeyword'    => __( 'Inserisci una parola chiave.', 'q1-shop-stripe-alert' ),
					'noResults'       => __( 'Nessun risultato trovato.', 'q1-shop-stripe-alert' ),
					'exportCsv'       => __( 'Esporta CSV', 'q1-shop-stripe-alert' ),
					'createDraft'     => __( 'Crea bozza articolo', 'q1-shop-stripe-alert' ),
					/* translators: %s: keyword name */
					'confirmDraft'    => __( 'Creare una bozza articolo per "%s"?', 'q1-shop-stripe-alert' ),
					'connectionError' => __( 'Errore di connessione.', 'q1-shop-stripe-alert' ),
					/* translators: %d: number of selected keywords */
					'selectedCount'   => __( '%d keyword selezionate', 'q1-shop-stripe-alert' ),
				),
			) );
		}

		// SEO Audit JS — shared strings for both metabox and page contexts.
		$audit_strings = array(
			'analyze'         => __( 'Analizza SEO', 'q1-shop-stripe-alert' ),
			'analyzing'       => __( 'Analisi in corso...', 'q1-shop-stripe-alert' ),
			'reanalyze'       => __( 'Rianalizza', 'q1-shop-stripe-alert' ),
			'error'           => __( 'Errore durante l\'analisi.', 'q1-shop-stripe-alert' ),
			'viewFullReport'  => __( 'Vedi report completo', 'q1-shop-stripe-alert' ),
			'noIssues'        => __( 'Nessun problema critico rilevato.', 'q1-shop-stripe-alert' ),
			/* translators: %d: number of additional warnings */
			'moreWarnings'    => __( '...e altri %d avvisi', 'q1-shop-stripe-alert' ),
			'suggestion'      => __( 'Suggerimento:', 'q1-shop-stripe-alert' ),
			'selectPost'      => __( '-- Seleziona --', 'q1-shop-stripe-alert' ),
			'severityCritical' => __( 'Critici', 'q1-shop-stripe-alert' ),
			'severityWarning' => __( 'Avvisi', 'q1-shop-stripe-alert' ),
			'severityInfo'    => __( 'Info', 'q1-shop-stripe-alert' ),
			'severityOk'      => __( 'OK', 'q1-shop-stripe-alert' ),
			'viewDetails'     => __( 'Vedi dettagli', 'q1-shop-stripe-alert' ),
			'hideDetails'     => __( 'Nascondi dettagli', 'q1-shop-stripe-alert' ),
		);

		// SEO Audit JS (on editor pages for metabox).
		if ( $is_editor_page ) {
			wp_enqueue_script(
				'q1-seo-audit',
				Q1_SHOP_SEO_URL . 'assets/js/seo-audit.js',
				array( 'jquery' ),
				Q1_SHOP_SEO_VERSION,
				true
			);

			wp_localize_script( 'q1-seo-audit', 'q1SeoAudit', array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'q1_seo_audit_nonce' ),
				'postId'       => get_the_ID(),
				'auditPageUrl' => admin_url( 'admin.php?page=q1-seo-audit' ),
				'context'      => 'metabox',
				'strings'      => $audit_strings,
			) );
		}

		// SEO Audit JS (on dedicated audit page).
		if ( 'ai-seo-assistant_page_q1-seo-audit' === $hook ) {
			wp_enqueue_script(
				'q1-seo-audit',
				Q1_SHOP_SEO_URL . 'assets/js/seo-audit.js',
				array( 'jquery' ),
				Q1_SHOP_SEO_VERSION,
				true
			);

			wp_localize_script( 'q1-seo-audit', 'q1SeoAudit', array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'q1_seo_audit_nonce' ),
				'postId'       => isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0,
				'auditPageUrl' => admin_url( 'admin.php?page=q1-seo-audit' ),
				'context'      => 'page',
				'strings'      => $audit_strings,
			) );
		}
	}

	/**
	 * Register SEO audit metabox on post and product editors.
	 */
	public function register_seo_metabox() {
		foreach ( array( 'post', 'product' ) as $post_type ) {
			add_meta_box(
				'q1-seo-audit-metabox',
				__( 'Analisi SEO AI', 'q1-shop-stripe-alert' ),
				array( $this, 'render_seo_metabox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render SEO audit metabox content.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_seo_metabox( $post ) {
		include Q1_SHOP_SEO_PATH . 'templates/metabox/seo-audit-metabox.php';
	}

	/**
	 * Map a numeric score to a level label.
	 *
	 * @param int $score Score 0-100.
	 * @return string 'good', 'ok', or 'poor'.
	 */
	public static function get_score_level( $score ) {
		if ( $score >= 80 ) {
			return 'good';
		}
		if ( $score >= 50 ) {
			return 'ok';
		}
		return 'poor';
	}

	/**
	 * Render Keyword Research page.
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
