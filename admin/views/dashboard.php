<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap es-admin">
	<h1 class="es-page-title">
		<span class="dashicons dashicons-feedback"></span>
		<?php echo esc_html__( 'ExitSurvey Dashboard', 'exitsurvey' ); ?>
	</h1>

	<div class="es-status-bar">
		<?php $es_enabled = ExitSurvey_Settings::get( 'enabled', 'yes' ) === 'yes'; ?>
		<span class="es-status <?php echo esc_attr( $es_enabled ? 'es-status--on' : 'es-status--off' ); ?>">
			<?php echo esc_html( $es_enabled ? '● ' . __( 'Plugin Active', 'exitsurvey' ) : '● ' . __( 'Plugin Disabled', 'exitsurvey' ) ); ?>
		</span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=exitsurvey-settings' ) ); ?>" class="es-link-small">
			<?php echo esc_html__( 'Settings →', 'exitsurvey' ); ?>
		</a>
	</div>

	<!-- Stats -->
	<div class="es-cards">
		<div class="es-card es-card--purple">
			<div class="es-card__number"><?php echo esc_html( number_format( $stats['total'] ) ); ?></div>
			<div class="es-card__label"><?php echo esc_html__( 'Total Responses', 'exitsurvey' ); ?></div>
		</div>
		<div class="es-card es-card--green">
			<div class="es-card__number"><?php echo esc_html( number_format( $stats['today'] ) ); ?></div>
			<div class="es-card__label"><?php echo esc_html__( "Today's Responses", 'exitsurvey' ); ?></div>
		</div>
		<div class="es-card es-card--blue">
			<div class="es-card__number"><?php echo $stats['avg_cart_value'] ? wp_kses_post( wc_price( $stats['avg_cart_value'] ) ) : '—'; ?></div>
			<div class="es-card__label"><?php echo esc_html__( 'Avg Cart Value', 'exitsurvey' ); ?></div>
		</div>
		<div class="es-card es-card--orange">
			<?php
			$es_triggers = wp_list_pluck( $stats['by_trigger'] ?? [], 'count', 'trigger_type' );
			$es_top      = key( $es_triggers ) ?: '—';
			?>
			<div class="es-card__number"><?php echo esc_html( ucfirst( $es_top ) ); ?></div>
			<div class="es-card__label"><?php echo esc_html__( 'Top Trigger', 'exitsurvey' ); ?></div>
		</div>
	</div>

	<div class="es-row">
		<!-- By Trigger -->
		<div class="es-panel">
			<h2><?php echo esc_html__( 'Responses by Trigger', 'exitsurvey' ); ?></h2>
			<?php if ( ! empty( $stats['by_trigger'] ) ) : ?>
				<table class="es-table">
					<thead><tr><th><?php echo esc_html__( 'Trigger', 'exitsurvey' ); ?></th><th><?php echo esc_html__( 'Count', 'exitsurvey' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $stats['by_trigger'] as $es_row ) : ?>
						<tr>
							<td><span class="es-badge es-badge--<?php echo esc_attr( $es_row['trigger_type'] ); ?>"><?php echo esc_html( ucfirst( $es_row['trigger_type'] ) ); ?></span></td>
							<td><?php echo esc_html( number_format( $es_row['count'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="es-empty"><?php echo esc_html__( 'No responses recorded yet.', 'exitsurvey' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Top Answers -->
		<div class="es-panel">
			<h2><?php echo esc_html__( 'Top Answers', 'exitsurvey' ); ?></h2>
			<?php if ( ! empty( $stats['top_answers'] ) ) : ?>
				<ul class="es-answer-list">
					<?php foreach ( $stats['top_answers'] as $row ) : ?>
						<li>
							<span class="es-answer-text"><?php echo esc_html( $row['answer'] ); ?></span>
							<span class="es-answer-count"><?php echo esc_html( $row['count'] ); ?>×</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="es-empty"><?php echo esc_html__( 'No answers recorded yet.', 'exitsurvey' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<p class="es-quick-links">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=exitsurvey-responses' ) ); ?>" class="button button-primary"><?php echo esc_html__( 'View All Responses', 'exitsurvey' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=exitsurvey-questions' ) ); ?>" class="button"><?php echo esc_html__( 'Manage Questions', 'exitsurvey' ); ?></a>
	</p>
</div>
