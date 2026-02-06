<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<form method="post" action="options.php">
		<?php
		settings_fields( Q1_Shop_SEO_Settings::OPTION_GROUP );
		do_settings_sections( 'q1-seo-settings' );
		submit_button( __( 'Salva Impostazioni', 'q1-shop-stripe-alert' ) );
		?>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Test Connessione', 'q1-shop-stripe-alert' ); ?></h2>
	<p>
		<button type="button" id="q1-seo-test-n8n" class="button button-secondary">
			<?php esc_html_e( 'Testa Connessione n8n', 'q1-shop-stripe-alert' ); ?>
		</button>
		<span class="spinner" id="q1-seo-test-spinner" style="float:none;"></span>
	</p>
	<div id="q1-seo-test-result"></div>

	<script>
	(function() {
		var btn     = document.getElementById('q1-seo-test-n8n');
		var spinner = document.getElementById('q1-seo-test-spinner');
		var result  = document.getElementById('q1-seo-test-result');
		var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'q1_seo_settings_nonce' ) ); ?>;

		btn.addEventListener('click', function() {
			btn.disabled = true;
			spinner.classList.add('is-active');
			result.innerHTML = '';

			var data = new FormData();
			data.append('action', 'q1_seo_test_n8n_connection');
			data.append('nonce', nonce);

			fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function(r) { return r.json(); })
				.then(function(json) {
					if (json.success) {
						result.innerHTML = '<div class="notice notice-success inline"><p>' + json.data.message + '</p></div>';
					} else {
						var msg = json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( 'Errore sconosciuto.', 'q1-shop-stripe-alert' ) ); ?>';
						result.innerHTML = '<div class="notice notice-error inline"><p>' + msg + '</p></div>';
					}
				})
				.catch(function() {
					result.innerHTML = '<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Errore di rete.', 'q1-shop-stripe-alert' ) ); ?></p></div>';
				})
				.finally(function() {
					btn.disabled = false;
					spinner.classList.remove('is-active');
				});
		});
	})();
	</script>
</div>
