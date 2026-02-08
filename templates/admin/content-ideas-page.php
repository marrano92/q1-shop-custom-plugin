<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$saved_context = Q1_Shop_Content_Ideas::get_saved_context();
$has_context   = ! empty( $saved_context['text'] );
$history       = get_option( Q1_Shop_Content_Ideas::HISTORY_OPTION, array() );
?>
<div class="wrap q1-seo-wrap">
	<h1><?php esc_html_e( 'Idee Articoli', 'q1-shop-stripe-alert' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Genera idee per nuovi articoli basate sul contesto del tuo sito, analisi competitor e dati keyword reali.', 'q1-shop-stripe-alert' ); ?></p>

	<!-- Sezione 1: Analisi Contesto Sito -->
	<div class="q1-seo-search-form">
		<h2><?php esc_html_e( 'Fase 1: Analisi Contesto Sito', 'q1-shop-stripe-alert' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Analizza i dati del tuo sito (prodotti, categorie, articoli) per costruire il contesto da inviare all\'AI.', 'q1-shop-stripe-alert' ); ?></p>

		<p class="submit">
			<button type="button" id="q1-ideas-analyze-btn" class="button button-secondary">
				<?php esc_html_e( 'Analizza Sito', 'q1-shop-stripe-alert' ); ?>
			</button>
			<span class="spinner" id="q1-ideas-analyze-spinner" style="float:none;"></span>
		</p>

		<div id="q1-ideas-context-box" class="q1-ideas-context-box" <?php echo $has_context ? '' : 'style="display:none;"'; ?>>
			<label for="q1-ideas-context-text">
				<strong><?php esc_html_e( 'Contesto del sito (editabile):', 'q1-shop-stripe-alert' ); ?></strong>
			</label>
			<textarea id="q1-ideas-context-text" rows="10" class="large-text"><?php echo $has_context ? esc_textarea( $saved_context['text'] ) : ''; ?></textarea>

			<p class="submit">
				<button type="button" id="q1-ideas-save-context-btn" class="button button-primary">
					<?php esc_html_e( 'Salva Contesto', 'q1-shop-stripe-alert' ); ?>
				</button>
				<span id="q1-ideas-save-status" class="q1-ideas-save-status">
					<?php if ( $has_context && ! empty( $saved_context['updated_at'] ) ) : ?>
						<?php
						printf(
							/* translators: %s: date/time of last save */
							esc_html__( 'Ultimo salvataggio: %s', 'q1-shop-stripe-alert' ),
							esc_html( $saved_context['updated_at'] )
						);
						?>
					<?php endif; ?>
				</span>
			</p>
		</div>
		<div id="q1-ideas-analyze-error" class="notice notice-error inline" style="display:none;"><p></p></div>
	</div>

	<!-- Sezione 2: Generazione Idee -->
	<div class="q1-seo-search-form">
		<h2><?php esc_html_e( 'Fase 2: Generazione Idee', 'q1-shop-stripe-alert' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Invia il contesto all\'AI per generare idee di articoli basate su analisi SERP e keyword reali.', 'q1-shop-stripe-alert' ); ?></p>

		<p class="submit">
			<button type="button" id="q1-ideas-generate-btn" class="button button-primary" <?php echo $has_context ? '' : 'disabled'; ?>>
				<?php esc_html_e( 'Inizia Ricerca', 'q1-shop-stripe-alert' ); ?>
			</button>
		</p>

		<div id="q1-ideas-loading" class="q1-seo-loading" style="display:none;">
			<span class="spinner is-active"></span>
			<span><?php esc_html_e( 'Generazione idee in corso... Potrebbe richiedere fino a 60 secondi.', 'q1-shop-stripe-alert' ); ?></span>
		</div>

		<div id="q1-ideas-error" class="notice notice-error" style="display:none;"><p></p></div>

		<div id="q1-ideas-data"></div>
	</div>

	<!-- Sezione 3: Storico Ricerche -->
	<div class="q1-seo-search-form q1-ideas-history-section">
		<h2><?php esc_html_e( 'Storico Ricerche', 'q1-shop-stripe-alert' ); ?></h2>

		<?php if ( empty( $history ) ) : ?>
			<p class="description"><?php esc_html_e( 'Nessuna ricerca precedente.', 'q1-shop-stripe-alert' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:40px;"></th>
						<th><?php esc_html_e( 'Data', 'q1-shop-stripe-alert' ); ?></th>
						<th><?php esc_html_e( 'Idee generate', 'q1-shop-stripe-alert' ); ?></th>
						<th><?php esc_html_e( 'Query analizzate', 'q1-shop-stripe-alert' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $index => $session ) : ?>
						<tr class="q1-ideas-history-row" data-index="<?php echo esc_attr( $index ); ?>">
							<td>
								<button type="button" class="button button-small q1-ideas-history-toggle" data-index="<?php echo esc_attr( $index ); ?>">
									<span class="dashicons dashicons-arrow-down-alt2"></span>
								</button>
							</td>
							<td><?php echo esc_html( $session['timestamp'] ); ?></td>
							<td><?php echo esc_html( count( $session['ideas'] ) ); ?></td>
							<td>
								<?php
								$queries_count = isset( $session['meta']['queries_used'] ) ? count( $session['meta']['queries_used'] ) : 0;
								echo esc_html( $queries_count );
								?>
							</td>
						</tr>
						<tr class="q1-ideas-history-detail" id="q1-ideas-detail-<?php echo esc_attr( $index ); ?>" style="display:none;">
							<td colspan="4">
								<div class="q1-seo-detail-inner">
									<?php if ( ! empty( $session['strategy_notes'] ) ) : ?>
										<div class="q1-ideas-strategy-notes">
											<strong><?php esc_html_e( 'Note strategiche:', 'q1-shop-stripe-alert' ); ?></strong>
											<p><?php echo esc_html( $session['strategy_notes'] ); ?></p>
										</div>
									<?php endif; ?>

									<?php if ( ! empty( $session['ideas'] ) ) : ?>
										<div class="q1-ideas-grid">
											<?php foreach ( $session['ideas'] as $idea ) : ?>
												<div class="q1-idea-card">
													<div class="q1-idea-title"><?php echo esc_html( $idea['title'] ); ?></div>
													<?php if ( ! empty( $idea['subtitle'] ) ) : ?>
														<div class="q1-idea-subtitle"><?php echo esc_html( $idea['subtitle'] ); ?></div>
													<?php endif; ?>
													<?php if ( ! empty( $idea['description'] ) ) : ?>
														<div class="q1-idea-description"><?php echo esc_html( $idea['description'] ); ?></div>
													<?php endif; ?>
													<div class="q1-idea-meta">
														<?php if ( ! empty( $idea['target_keywords'] ) ) : ?>
															<?php foreach ( (array) $idea['target_keywords'] as $kw ) : ?>
																<span class="q1-idea-keyword-badge"><?php echo esc_html( $kw ); ?></span>
															<?php endforeach; ?>
														<?php endif; ?>
														<?php if ( ! empty( $idea['estimated_volume'] ) ) : ?>
															<span class="q1-idea-volume"><?php echo esc_html( $idea['estimated_volume'] ); ?> vol.</span>
														<?php endif; ?>
														<?php if ( ! empty( $idea['difficulty'] ) ) : ?>
															<span class="q1-idea-badge q1-idea-badge--<?php echo esc_attr( $idea['difficulty'] ); ?>"><?php echo esc_html( $idea['difficulty'] ); ?></span>
														<?php endif; ?>
														<?php if ( ! empty( $idea['content_type'] ) ) : ?>
															<span class="q1-idea-badge q1-idea-badge--type"><?php echo esc_html( $idea['content_type'] ); ?></span>
														<?php endif; ?>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
