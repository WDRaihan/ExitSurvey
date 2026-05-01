<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap es-admin">
	<h1 class="es-page-title">
		<span class="dashicons dashicons-list-view"></span>
		<?php echo esc_html__( 'Survey Responses', 'exitsurvey' ); ?>
	</h1>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Response deleted.', 'exitsurvey' ); ?></p></div>
	<?php endif; ?>

	<!-- Filters -->
	<form method="get" class="es-filter-bar">
		<input type="hidden" name="page" value="exitsurvey-responses">
		<select name="trigger">
			<option value=""><?php echo esc_html__( 'All Triggers', 'exitsurvey' ); ?></option>
			<?php foreach ( [ 'cart', 'checkout', 'product', 'shop', 'general' ] as $t ) : ?>
				<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $filter['trigger_type'], $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search answers...', 'exitsurvey' ); ?>" value="<?php echo esc_attr( $filter['search'] ); ?>">
		<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'exitsurvey' ); ?></button>
		<?php if ( $filter['trigger_type'] || $filter['search'] ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=exitsurvey-responses' ) ); ?>" class="button"><?php echo esc_html__( 'Clear', 'exitsurvey' ); ?></a>
		<?php endif; ?>
		<span class="es-total"><?php printf( esc_html__( '%d responses', 'exitsurvey' ), $data['total'] ); ?></span>
	</form>

	<?php if ( $data['rows'] ) : ?>
		<table class="es-table es-table--full wp-list-table widefat fixed">
			<thead>
				<tr>
					<th width="80"><?php echo esc_html__( 'ID', 'exitsurvey' ); ?></th>
					<th width="100"><?php echo esc_html__( 'Trigger', 'exitsurvey' ); ?></th>
					<th><?php echo esc_html__( 'Question', 'exitsurvey' ); ?></th>
					<th><?php echo esc_html__( 'Answer', 'exitsurvey' ); ?></th>
					<th width="100"><?php echo esc_html__( 'Cart Value', 'exitsurvey' ); ?></th>
					<th width="140"><?php echo esc_html__( 'Date', 'exitsurvey' ); ?></th>
					<th width="80"><?php echo esc_html__( 'Actions', 'exitsurvey' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $data['rows'] as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['id'] ?? '' ); ?></td>
					<td><span class="es-badge es-badge--<?php echo esc_attr( $row['trigger_type'] ?? 'general' ); ?>"><?php echo esc_html( ucfirst( $row['trigger_type'] ?? 'general' ) ); ?></span></td>
					<td><?php echo esc_html( wp_trim_words( $row['question_text'] ?? '', 10 ) ); ?></td>
					<td><?php echo esc_html( $row['answer'] ?? '' ); ?></td>
					<td><?php echo ! empty( $row['cart_value'] ) ? wc_price( $row['cart_value'] ) : '—'; ?></td>
					<td><?php echo esc_html( date_i18n( 'M j, Y H:i', strtotime( $row['created_at'] ?? 'now' ) ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=exitsurvey_delete_response&id=' . ( $row['id'] ?? 0 ) ), 'exitsurvey_delete_response' ) ); ?>"
						   class="es-delete-link"
						   onclick="return confirm('<?php esc_attr_e( 'Delete this response?', 'exitsurvey' ); ?>')">
							<?php echo esc_html__( 'Delete', 'exitsurvey' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $data['pages'] > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo paginate_links( [
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $filter['page'],
						'total'   => $data['pages'],
					] );
					?>
				</div>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<div class="es-empty-state">
			<span class="dashicons dashicons-format-chat"></span>
			<p><?php echo esc_html__( 'No responses found. They will appear here once visitors interact with the exit survey popup.', 'exitsurvey' ); ?></p>
		</div>
	<?php endif; ?>
</div>
