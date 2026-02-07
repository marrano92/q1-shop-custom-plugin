<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap q1-seo-wrap">
	<h1><?php esc_html_e( 'Keyword Research', 'q1-shop-stripe-alert' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Trova le migliori parole chiave per i tuoi contenuti, basate su dati reali di ricerca Google e analisi AI.', 'q1-shop-stripe-alert' ); ?></p>

	<div class="q1-seo-search-form">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="q1-keyword-seed"><?php esc_html_e( 'Parola chiave seed', 'q1-shop-stripe-alert' ); ?></label>
				</th>
				<td>
					<input type="text" id="q1-keyword-seed" class="regular-text"
					       placeholder="<?php esc_attr_e( 'Es. aspirapolvere robot', 'q1-shop-stripe-alert' ); ?>" />
					<p class="description"><?php esc_html_e( 'Inserisci una parola chiave o argomento da analizzare.', 'q1-shop-stripe-alert' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Contesto automatico', 'q1-shop-stripe-alert' ); ?></th>
				<td>
					<label>
						<input type="checkbox" id="q1-auto-context" value="1" />
						<?php esc_html_e( 'Suggerimento automatico da WooCommerce', 'q1-shop-stripe-alert' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Se attivo, aggiunge contesto dal catalogo prodotti per risultati più pertinenti.', 'q1-shop-stripe-alert' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="button" id="q1-keyword-search-btn" class="button button-primary">
				<?php esc_html_e( 'Cerca Keywords', 'q1-shop-stripe-alert' ); ?>
			</button>
		</p>
	</div>

	<!-- Results area (populated via JS) -->
	<div id="q1-keyword-results" class="q1-seo-results" style="display: none;">
		<div id="q1-keyword-loading" class="q1-seo-loading" style="display: none;">
			<span class="spinner is-active"></span>
			<span><?php esc_html_e( 'Ricerca in corso... Potrebbe richiedere fino a 30 secondi.', 'q1-shop-stripe-alert' ); ?></span>
		</div>
		<div id="q1-keyword-error" class="notice notice-error" style="display: none;">
			<p></p>
		</div>
		<div id="q1-keyword-data"></div>
	</div>
</div>
