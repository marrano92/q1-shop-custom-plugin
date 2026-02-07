<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$last_audit = Q1_Shop_SEO_Audit::get_last_audit( $post->ID );
?>
<div id="q1-seo-metabox-content">
	<p>
		<button type="button" id="q1-seo-analyze-btn" class="button button-primary button-large" style="width:100%;">
			<?php echo $last_audit
				? esc_html__( 'Rianalizza', 'q1-shop-stripe-alert' )
				: esc_html__( 'Analizza SEO', 'q1-shop-stripe-alert' );
			?>
		</button>
	</p>

	<div id="q1-seo-metabox-loading" style="display:none; text-align:center; padding:10px;">
		<span class="spinner is-active" style="float:none;"></span>
		<br/><small><?php esc_html_e( 'Analisi in corso... (fino a 30 sec)', 'q1-shop-stripe-alert' ); ?></small>
	</div>

	<div id="q1-seo-metabox-error" class="notice notice-error inline" style="display:none;"><p></p></div>

	<div id="q1-seo-metabox-report">
		<?php if ( $last_audit ) : ?>
			<div class="q1-seo-score-badge q1-seo-score-<?php echo esc_attr( Q1_Shop_SEO_Assistant::get_score_level( $last_audit['score'] ) ); ?>">
				<?php echo esc_html( $last_audit['score'] ); ?>/100
			</div>
			<p class="description">
				<?php
				printf(
					/* translators: %s: audit timestamp */
					esc_html__( 'Ultimo audit: %s', 'q1-shop-stripe-alert' ),
					esc_html( $last_audit['timestamp'] )
				);
				?>
			</p>
		<?php endif; ?>
	</div>
</div>
