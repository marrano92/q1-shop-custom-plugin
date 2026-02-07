<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php $stats = Q1_Shop_SEO_Settings::get_usage_stats(); ?>
	<div class="q1-seo-budget-widget">
		<h2><?php esc_html_e( 'Utilizzo odierno', 'q1-shop-stripe-alert' ); ?></h2>
		<div class="q1-seo-budget-grid">
			<div class="q1-budget-card">
				<span class="q1-budget-label"><?php esc_html_e( 'Keyword Research', 'q1-shop-stripe-alert' ); ?></span>
				<span class="q1-budget-value <?php echo esc_attr( $stats['keyword_today'] >= $stats['keyword_limit'] * 0.8 ? 'q1-budget-warning' : '' ); ?>">
					<?php echo esc_html( $stats['keyword_today'] . ' / ' . $stats['keyword_limit'] ); ?>
				</span>
				<span class="q1-budget-sublabel"><?php esc_html_e( 'ricerche oggi', 'q1-shop-stripe-alert' ); ?></span>
				<?php if ( $stats['keyword_today'] >= $stats['keyword_limit'] * 0.8 && $stats['keyword_today'] < $stats['keyword_limit'] ) : ?>
					<span class="q1-budget-alert"><?php esc_html_e( 'Quasi al limite!', 'q1-shop-stripe-alert' ); ?></span>
				<?php elseif ( $stats['keyword_today'] >= $stats['keyword_limit'] ) : ?>
					<span class="q1-budget-alert q1-budget-exceeded"><?php esc_html_e( 'Limite raggiunto', 'q1-shop-stripe-alert' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="q1-budget-card">
				<span class="q1-budget-label"><?php esc_html_e( 'SEO Audit', 'q1-shop-stripe-alert' ); ?></span>
				<span class="q1-budget-value <?php echo esc_attr( $stats['audit_today'] >= $stats['audit_limit'] * 0.8 ? 'q1-budget-warning' : '' ); ?>">
					<?php echo esc_html( $stats['audit_today'] . ' / ' . $stats['audit_limit'] ); ?>
				</span>
				<span class="q1-budget-sublabel"><?php esc_html_e( 'analisi oggi', 'q1-shop-stripe-alert' ); ?></span>
				<?php if ( $stats['audit_today'] >= $stats['audit_limit'] * 0.8 && $stats['audit_today'] < $stats['audit_limit'] ) : ?>
					<span class="q1-budget-alert"><?php esc_html_e( 'Quasi al limite!', 'q1-shop-stripe-alert' ); ?></span>
				<?php elseif ( $stats['audit_today'] >= $stats['audit_limit'] ) : ?>
					<span class="q1-budget-alert q1-budget-exceeded"><?php esc_html_e( 'Limite raggiunto', 'q1-shop-stripe-alert' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'I contatori si resettano automaticamente a mezzanotte.', 'q1-shop-stripe-alert' ); ?></p>
	</div>

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

	<hr>
	<h2><?php esc_html_e( 'Log recenti', 'q1-shop-stripe-alert' ); ?></h2>
	<?php $log_entries = Q1_Shop_SEO_Logger::get_recent_entries( 20 ); ?>
	<?php if ( empty( $log_entries ) ) : ?>
		<p class="description"><?php esc_html_e( 'Nessun log registrato.', 'q1-shop-stripe-alert' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:160px;"><?php esc_html_e( 'Data', 'q1-shop-stripe-alert' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Livello', 'q1-shop-stripe-alert' ); ?></th>
					<th><?php esc_html_e( 'Messaggio', 'q1-shop-stripe-alert' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log_entries as $entry ) : ?>
					<tr class="q1-log-<?php echo esc_attr( strtolower( $entry['level'] ) ); ?>">
						<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
						<td><strong><?php echo esc_html( $entry['level'] ); ?></strong></td>
						<td><?php echo esc_html( $entry['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

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
