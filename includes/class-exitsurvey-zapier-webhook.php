<?php
/**
 * Zapier Webhook Integration.
 *
 * Self-contained module: hooks into the core plugin via action/filter hooks.
 * Can be extracted to a standalone pro plugin in the future.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Zapier_Webhook {

	/**
	 * Register all hooks.
	 */
	public static function init() {
		// Admin: inject UI in settings.
		add_action( 'exitsurvey_settings_sections', [ __CLASS__, 'render_settings_section' ] );

		// Admin: save settings.
		add_action( 'exitsurvey_save_settings', [ __CLASS__, 'save_settings_fields' ] );

		// Response: react to saved response.
		add_action( 'exitsurvey_after_response_saved', [ __CLASS__, 'handle_response' ], 10, 3 );
	}

	/**
	 * Render settings section for Zapier Webhook.
	 */
	public static function render_settings_section() {
		$enabled = get_option( 'exitsurvey_zapier_enabled', 'no' ) === 'yes';
		$webhook_url = get_option( 'exitsurvey_zapier_webhook_url', '' );
		?>
		<div class="es-settings-section" style="grid-column: 1 / -1;">
			<h2><?php echo esc_html__( '🔌 Zapier Webhook', 'exitsurvey' ); ?></h2>
			<p class="description" style="margin-top: -8px; margin-bottom: 16px; color: #64748b; font-size: 13px;">
				<?php echo esc_html__( 'Send survey response data to any app (like Zapier, Make/Integromat, or custom API endpoints) automatically when a user completes a survey.', 'exitsurvey' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th><?php echo esc_html__( 'Enable Webhook', 'exitsurvey' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="exitsurvey_zapier_enabled" value="yes" <?php checked( $enabled ); ?>>
							<?php echo esc_html__( 'Send response data via webhook', 'exitsurvey' ); ?>
						</label>
					</td>
				</tr>
				<tr id="es-zapier-url-row" style="<?php echo ! $enabled ? 'display:none;' : ''; ?>">
					<th><?php echo esc_html__( 'Webhook URL', 'exitsurvey' ); ?></th>
					<td>
						<input type="url" name="exitsurvey_zapier_webhook_url" value="<?php echo esc_url( $webhook_url ); ?>" class="regular-text" placeholder="https://hooks.zapier.com/hooks/catch/..."/>
						<p class="description"><?php echo esc_html__( 'Enter the Catch Hook URL from Zapier or other automation platforms.', 'exitsurvey' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<script>
			jQuery(function($) {
				$('input[name="exitsurvey_zapier_enabled"]').on('change', function() {
					if ($(this).is(':checked')) {
						$('#es-zapier-url-row').show();
					} else {
						$('#es-zapier-url-row').hide();
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Save settings fields.
	 */
	public static function save_settings_fields() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$enabled = isset( $_POST['exitsurvey_zapier_enabled'] ) && $_POST['exitsurvey_zapier_enabled'] === 'yes' ? 'yes' : 'no';
		$webhook_url = isset( $_POST['exitsurvey_zapier_webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['exitsurvey_zapier_webhook_url'] ) ) : '';

		update_option( 'exitsurvey_zapier_enabled', $enabled );
		update_option( 'exitsurvey_zapier_webhook_url', $webhook_url );
		// phpcs:enable
	}

	/**
	 * Trigger the webhook when a survey response is saved.
	 *
	 * @param string $question_id  Question key.
	 * @param string $answer       Answer text.
	 * @param int    $response_id  Database response ID.
	 */
	public static function handle_response( $question_id, $answer, $response_id ) {
		if ( get_option( 'exitsurvey_zapier_enabled', 'no' ) !== 'yes' ) {
			return;
		}

		$webhook_url = get_option( 'exitsurvey_zapier_webhook_url', '' );
		if ( empty( $webhook_url ) || ! filter_var( $webhook_url, FILTER_VALIDATE_URL ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'exitsurvey_responses';
		$response_row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $response_id ),
			ARRAY_A
		);

		if ( ! $response_row ) {
			return;
		}

		// Decode cart items and page history if possible for a nicer payload structure
		if ( ! empty( $response_row['cart_items'] ) ) {
			$decoded_cart = json_decode( $response_row['cart_items'], true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$response_row['cart_items'] = $decoded_cart;
			}
		}
		if ( ! empty( $response_row['page_history'] ) ) {
			$decoded_history = json_decode( $response_row['page_history'], true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$response_row['page_history'] = $decoded_history;
			}
		}

		// Dispatch request asynchronously
		wp_remote_post( $webhook_url, [
			'timeout'     => 15,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => false, // fire-and-forget
			'headers'     => [
				'Content-Type' => 'application/json; charset=utf-8',
			],
			'body'        => wp_json_encode( $response_row ),
			'data_format' => 'body',
		] );
	}
}
