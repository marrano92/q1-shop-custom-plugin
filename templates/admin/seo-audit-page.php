<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$selected_post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
?>
<div class="wrap q1-seo-wrap">
	<h1><?php esc_html_e( 'SEO Audit', 'q1-shop-stripe-alert' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Analizza la qualità SEO di un articolo o prodotto.', 'q1-shop-stripe-alert' ); ?></p>

	<div class="q1-seo-search-form">
		<table class="form-table">
			<tr>
				<th scope="row"><label for="q1-audit-post-search"><?php esc_html_e( 'Cerca contenuto', 'q1-shop-stripe-alert' ); ?></label></th>
				<td>
					<input type="text" id="q1-audit-post-search" class="regular-text" placeholder="<?php esc_attr_e( 'Digita per cercare...', 'q1-shop-stripe-alert' ); ?>" />
					<select id="q1-audit-post-select" class="regular-text" style="min-width:350px;">
						<option value=""><?php esc_html_e( '-- Seleziona --', 'q1-shop-stripe-alert' ); ?></option>
						<?php
						if ( $selected_post_id ) :
							$p = get_post( $selected_post_id );
							if ( $p ) :
								?>
								<option value="<?php echo esc_attr( $p->ID ); ?>" selected>
									<?php echo esc_html( $p->post_title . ' (' . $p->post_type . ')' ); ?>
								</option>
								<?php
							endif;
						endif;
						?>
					</select>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="button" id="q1-audit-analyze-btn" class="button button-primary" <?php echo ! $selected_post_id ? 'disabled' : ''; ?>>
				<?php esc_html_e( 'Analizza SEO', 'q1-shop-stripe-alert' ); ?>
			</button>
		</p>
	</div>

	<div id="q1-seo-page-report-wrapper" style="display:none;">
		<div id="q1-seo-page-loading" class="q1-seo-loading" style="display:none;">
			<span class="spinner is-active"></span>
			<span><?php esc_html_e( 'Analisi in corso...', 'q1-shop-stripe-alert' ); ?></span>
		</div>
		<div id="q1-seo-page-error" class="notice notice-error" style="display:none;"><p></p></div>
		<div id="q1-seo-page-report"></div>
	</div>

	<div class="q1-seo-recent-audits">
		<h2><?php esc_html_e( 'Ultimi audit effettuati', 'q1-shop-stripe-alert' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Titolo', 'q1-shop-stripe-alert' ); ?></th>
					<th><?php esc_html_e( 'Tipo', 'q1-shop-stripe-alert' ); ?></th>
					<th><?php esc_html_e( 'Score', 'q1-shop-stripe-alert' ); ?></th>
					<th><?php esc_html_e( 'Data', 'q1-shop-stripe-alert' ); ?></th>
					<th><?php esc_html_e( 'Azioni', 'q1-shop-stripe-alert' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$audited_posts = get_posts( array(
					'post_type'   => array( 'post', 'product' ),
					'meta_key'    => '_q1_seo_last_audit',
					'orderby'     => 'meta_value',
					'order'       => 'DESC',
					'numberposts' => 20,
				) );

				if ( empty( $audited_posts ) ) :
					?>
					<tr><td colspan="5"><?php esc_html_e( 'Nessun audit effettuato.', 'q1-shop-stripe-alert' ); ?></td></tr>
				<?php
				else :
					foreach ( $audited_posts as $ap ) :
						$audit_data = get_post_meta( $ap->ID, '_q1_seo_last_audit', true );
						if ( ! is_array( $audit_data ) ) {
							continue;
						}
						$score = isset( $audit_data['score'] ) ? $audit_data['score'] : 0;
						$level = Q1_Shop_SEO_Assistant::get_score_level( $score );
						?>
						<tr data-audit="<?php echo esc_attr( wp_json_encode( $audit_data ) ); ?>">
							<td><a href="<?php echo esc_url( get_edit_post_link( $ap->ID ) ); ?>"><?php echo esc_html( $ap->post_title ); ?></a></td>
							<td><?php echo esc_html( $ap->post_type ); ?></td>
							<td><span class="q1-seo-score-inline q1-seo-score-<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $score ); ?>/100</span></td>
							<td><?php echo esc_html( isset( $audit_data['timestamp'] ) ? $audit_data['timestamp'] : '' ); ?></td>
							<td>
								<button type="button" class="button button-small q1-seo-detail-toggle"><?php esc_html_e( 'Vedi dettagli', 'q1-shop-stripe-alert' ); ?></button>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=q1-seo-audit&post_id=' . $ap->ID ) ); ?>" class="button button-small"><?php esc_html_e( 'Rianalizza', 'q1-shop-stripe-alert' ); ?></a>
							</td>
						</tr>
					<?php
					endforeach;
				endif;
				?>
			</tbody>
		</table>
	</div>
</div>
