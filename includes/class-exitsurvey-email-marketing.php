<?php
/**
 * Email Marketing Integration — Mailchimp & Klaviyo.
 *
 * Self-contained module: hooks into the core plugin via action/filter hooks.
 * Can be extracted to a standalone pro plugin in the future.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Email_Marketing {

	/* ------------------------------------------------------------------
	 * BOOTSTRAP
	 * --------------------------------------------------------------- */

	/**
	 * Register all hooks.
	 */
	public static function init() {
		// Database migration.
		add_action( 'exitsurvey_database_update', [ __CLASS__, 'migrate_database' ] );

		// Admin: inject UI.
		add_action( 'exitsurvey_question_extra_fields', [ __CLASS__, 'render_question_checkbox' ], 10, 2 );
		add_action( 'exitsurvey_settings_sections', [ __CLASS__, 'render_settings_section' ] );

		// Admin: save data.
		add_action( 'exitsurvey_save_question', [ __CLASS__, 'save_question_field' ], 10, 2 );
		add_action( 'exitsurvey_save_settings', [ __CLASS__, 'save_settings_fields' ] );

		// Admin: enqueue scripts.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_scripts' ] );

		// Frontend: enrich question data for JS.
		add_filter( 'exitsurvey_question_row_data', [ __CLASS__, 'enrich_question_data' ] );

		// Subscription: react to saved response.
		add_action( 'exitsurvey_after_response_saved', [ __CLASS__, 'handle_response' ], 10, 3 );
	}

	/* ------------------------------------------------------------------
	 * DATABASE MIGRATION
	 * --------------------------------------------------------------- */

	/**
	 * Add extra_email_collect column to questions table if missing.
	 */
	public static function migrate_database() {
		global $wpdb;
		$q_table = $wpdb->prefix . 'exitsurvey_questions';

		$col = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = 'extra_email_collect' AND TABLE_SCHEMA = %s",
				$q_table,
				DB_NAME
			)
		);

		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$q_table} ADD COLUMN extra_email_collect TINYINT(1) NOT NULL DEFAULT 0 AFTER extra_field_label" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/* ------------------------------------------------------------------
	 * ADMIN: QUESTIONS PAGE — EMAIL COLLECTOR CHECKBOX
	 * --------------------------------------------------------------- */

	/**
	 * Render the "Use as email collector" checkbox inside each question card.
	 *
	 * @param array $q         Question data row.
	 * @param int   $row_index Current row index.
	 */
	public static function render_question_checkbox( $q, $row_index ) {
		$checked = ! empty( $q['extra_email_collect'] ) ? 1 : 0;
		$visible = ! empty( $q['extra_field_enabled'] ) ? '' : 'display:none;';
		?>
		<div class="es-email-collect-wrap" style="margin-top: 10px; <?php echo esc_attr( $visible ); ?>">
			<label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; font-size: 13px; color: #1e40af;">
				<input type="checkbox"
					   name="q_extra_email_collect[<?php echo (int) $row_index; ?>]"
					   value="1"
					   <?php checked( $checked, 1 ); ?>>
				<?php echo esc_html__( '📧 Use as email collector (Mailchimp / Klaviyo)', 'exitsurvey' ); ?>
			</label>
			<p class="description" style="margin: 4px 0 0; font-size: 12px; color: #94a3b8;">
				<?php echo esc_html__( 'When checked, values entered in this field will be treated as email addresses and auto-subscribed to your configured mailing list.', 'exitsurvey' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save the email collector flag for a question.
	 *
	 * @param int $question_id The question ID.
	 * @param int $row_index   The row index from the form.
	 */
	public static function save_question_field( $question_id, $row_index ) {
		global $wpdb;

		$email_collect = wp_unslash( $_POST['q_extra_email_collect'] ?? [] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value         = isset( $email_collect[ $row_index ] ) ? 1 : 0;

		$wpdb->update(
			$wpdb->prefix . 'exitsurvey_questions',
			[ 'extra_email_collect' => $value ],
			[ 'id' => $question_id ]
		);
	}

	/* ------------------------------------------------------------------
	 * ADMIN: SETTINGS PAGE — EMAIL MARKETING SECTION
	 * --------------------------------------------------------------- */

	/**
	 * Render the Email Marketing settings section.
	 */
	public static function render_settings_section() {
		$provider       = get_option( 'exitsurvey_email_provider', 'none' );
		$mc_api_key     = get_option( 'exitsurvey_mailchimp_api_key', '' );
		$mc_list_id     = get_option( 'exitsurvey_mailchimp_list_id', '' );
		$kl_api_key     = get_option( 'exitsurvey_klaviyo_api_key', '' );
		$kl_list_id     = get_option( 'exitsurvey_klaviyo_list_id', '' );
		?>
		<div class="es-settings-section" style="grid-column: 1 / -1;">
			<h2><?php echo esc_html__( '📧 Email Marketing', 'exitsurvey' ); ?></h2>
			<p class="description" style="margin-top: -8px; margin-bottom: 16px; color: #64748b; font-size: 13px;">
				<?php echo esc_html__( 'Auto-subscribe emails collected via survey questions to your mailing list. Enable the "Email Collector" checkbox on individual questions to activate.', 'exitsurvey' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th><?php echo esc_html__( 'Email Provider', 'exitsurvey' ); ?></th>
					<td>
						<select name="exitsurvey_email_provider" id="es-email-provider">
							<option value="none" <?php selected( $provider, 'none' ); ?>><?php echo esc_html__( 'None (disabled)', 'exitsurvey' ); ?></option>
							<option value="mailchimp" <?php selected( $provider, 'mailchimp' ); ?>><?php echo esc_html__( 'Mailchimp', 'exitsurvey' ); ?></option>
							<option value="klaviyo" <?php selected( $provider, 'klaviyo' ); ?>><?php echo esc_html__( 'Klaviyo', 'exitsurvey' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<!-- Mailchimp Fields -->
			<div id="es-mailchimp-fields" class="es-provider-fields" style="<?php echo $provider !== 'mailchimp' ? 'display:none;' : ''; ?>">
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Mailchimp API Key', 'exitsurvey' ); ?></th>
						<td>
							<input type="password" name="exitsurvey_mailchimp_api_key" value="<?php echo esc_attr( $mc_api_key ); ?>" class="regular-text" autocomplete="off">
							<p class="description"><?php echo esc_html__( 'Found in Mailchimp → Account → Extras → API keys. Format: xxxxxxxx-us1', 'exitsurvey' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Audience / List ID', 'exitsurvey' ); ?></th>
						<td>
							<input type="text" name="exitsurvey_mailchimp_list_id" value="<?php echo esc_attr( $mc_list_id ); ?>" class="regular-text">
							<p class="description"><?php echo esc_html__( 'Found in Mailchimp → Audience → Settings → Audience ID.', 'exitsurvey' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Klaviyo Fields -->
			<div id="es-klaviyo-fields" class="es-provider-fields" style="<?php echo $provider !== 'klaviyo' ? 'display:none;' : ''; ?>">
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Klaviyo Private API Key', 'exitsurvey' ); ?></th>
						<td>
							<input type="password" name="exitsurvey_klaviyo_api_key" value="<?php echo esc_attr( $kl_api_key ); ?>" class="regular-text" autocomplete="off">
							<p class="description"><?php echo esc_html__( 'Found in Klaviyo → Settings → API Keys → Create Private API Key.', 'exitsurvey' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Klaviyo List ID', 'exitsurvey' ); ?></th>
						<td>
							<input type="text" name="exitsurvey_klaviyo_list_id" value="<?php echo esc_attr( $kl_list_id ); ?>" class="regular-text">
							<p class="description"><?php echo esc_html__( 'Found in Klaviyo → Audience → Lists & Segments → select list → Settings → List ID.', 'exitsurvey' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Save email marketing settings.
	 */
	public static function save_settings_fields() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by the caller.
		$fields = [
			'exitsurvey_email_provider'     => 'text',
			'exitsurvey_mailchimp_api_key'  => 'text',
			'exitsurvey_mailchimp_list_id'  => 'text',
			'exitsurvey_klaviyo_api_key'    => 'text',
			'exitsurvey_klaviyo_list_id'    => 'text',
		];

		foreach ( $fields as $key => $type ) {
			$raw = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			update_option( $key, $raw );
		}
		// phpcs:enable
	}

	/* ------------------------------------------------------------------
	 * ADMIN: ENQUEUE SCRIPTS
	 * --------------------------------------------------------------- */

	/**
	 * Enqueue admin JS for the email marketing settings toggle.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'exitsurvey' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'exitsurvey-email-marketing-admin',
			EXITSURVEY_URL . 'admin/js/email-marketing-admin.js',
			[ 'jquery' ],
			EXITSURVEY_VERSION,
			true
		);
	}

	/* ------------------------------------------------------------------
	 * FRONTEND: ENRICH QUESTION DATA
	 * --------------------------------------------------------------- */

	/**
	 * Add email_collect flag to question data sent to the frontend.
	 *
	 * @param array $row Question data.
	 * @return array Modified question data.
	 */
	public static function enrich_question_data( $row ) {
		$row['extra_email_collect'] = ! empty( $row['extra_email_collect'] ) ? true : false;
		return $row;
	}

	/* ------------------------------------------------------------------
	 * SUBSCRIPTION: HANDLE SURVEY RESPONSE
	 * --------------------------------------------------------------- */

	/**
	 * After a survey response is saved, check if the question collects emails
	 * and subscribe the address to the configured provider.
	 *
	 * @param string $question_id  The question key identifier.
	 * @param string $answer       The full answer text (may contain "| Note: email").
	 * @param int    $response_id  The DB insert ID.
	 */
	public static function handle_response( $question_id, $answer, $response_id ) {
		// Look up the question to check if email collection is enabled.
		global $wpdb;
		$table = $wpdb->prefix . 'exitsurvey_questions';

		$question = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT extra_email_collect FROM " . sanitize_key( $table ) . " WHERE question_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$question_id
			),
			ARRAY_A
		);

		if ( empty( $question ) || empty( $question['extra_email_collect'] ) ) {
			return;
		}

		// Extract the email from the answer.
		// The frontend sends: "{answer} | Note: {extra_input_value}"
		$email = self::extract_email_from_answer( $answer );

		if ( empty( $email ) || ! is_email( $email ) ) {
			return;
		}

		// Attempt subscription — fire-and-forget, don't block the response.
		self::subscribe( sanitize_email( $email ) );
	}

	/**
	 * Extract email address from the answer string.
	 *
	 * The frontend appends extra field input as: "{answer} | Note: {value}"
	 *
	 * @param string $answer Full answer text.
	 * @return string Extracted email or empty string.
	 */
	private static function extract_email_from_answer( $answer ) {
		// Check for the "| Note: " separator used by the frontend JS.
		if ( strpos( $answer, '| Note: ' ) !== false ) {
			$parts = explode( '| Note: ', $answer, 2 );
			$candidate = trim( $parts[1] ?? '' );
			if ( is_email( $candidate ) ) {
				return $candidate;
			}
		}

		// Fallback: check if the entire answer is an email (for open-text questions).
		$trimmed = trim( $answer );
		if ( is_email( $trimmed ) ) {
			return $trimmed;
		}

		return '';
	}

	/* ------------------------------------------------------------------
	 * SUBSCRIPTION: ROUTING
	 * --------------------------------------------------------------- */

	/**
	 * Subscribe an email to the configured provider.
	 *
	 * @param string $email Validated email address.
	 * @return bool True on success, false on failure.
	 */
	public static function subscribe( $email ) {
		$provider = get_option( 'exitsurvey_email_provider', 'none' );

		if ( 'mailchimp' === $provider ) {
			return self::subscribe_mailchimp( $email );
		}

		if ( 'klaviyo' === $provider ) {
			return self::subscribe_klaviyo( $email );
		}

		return false;
	}

	/**
	 * Check if a provider is properly configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$provider = get_option( 'exitsurvey_email_provider', 'none' );

		if ( 'mailchimp' === $provider ) {
			return ! empty( get_option( 'exitsurvey_mailchimp_api_key' ) )
				&& ! empty( get_option( 'exitsurvey_mailchimp_list_id' ) );
		}

		if ( 'klaviyo' === $provider ) {
			return ! empty( get_option( 'exitsurvey_klaviyo_api_key' ) )
				&& ! empty( get_option( 'exitsurvey_klaviyo_list_id' ) );
		}

		return false;
	}

	/* ------------------------------------------------------------------
	 * MAILCHIMP API v3
	 * --------------------------------------------------------------- */

	/**
	 * Subscribe an email to Mailchimp via API v3.
	 *
	 * Uses PUT to /lists/{list_id}/members/{md5(email)} which creates
	 * or updates the subscriber in a single call.
	 *
	 * @param string $email Email address.
	 * @return bool True on success.
	 */
	private static function subscribe_mailchimp( $email ) {
		$api_key = get_option( 'exitsurvey_mailchimp_api_key', '' );
		$list_id = get_option( 'exitsurvey_mailchimp_list_id', '' );

		if ( empty( $api_key ) || empty( $list_id ) ) {
			return false;
		}

		// Extract data center from API key (e.g. "abc123-us18" → "us18").
		$dc_pos = strrpos( $api_key, '-' );
		if ( false === $dc_pos ) {
			return false;
		}
		$data_center = substr( $api_key, $dc_pos + 1 );

		$member_hash = md5( strtolower( $email ) );
		$url         = sprintf(
			'https://%s.api.mailchimp.com/3.0/lists/%s/members/%s',
			$data_center,
			$list_id,
			$member_hash
		);

		$body = wp_json_encode( [
			'email_address' => $email,
			'status_if_new' => 'subscribed',
		] );

		$response = wp_remote_request( $url, [
			'method'  => 'PUT',
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ),
				'Content-Type'  => 'application/json',
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( '[ExitSurvey] Mailchimp error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		error_log( '[ExitSurvey] Mailchimp HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
		return false;
	}

	/* ------------------------------------------------------------------
	 * KLAVIYO API (Revision 2024-10-15)
	 * --------------------------------------------------------------- */

	/**
	 * Subscribe an email to Klaviyo via the current API.
	 *
	 * Uses POST to /api/profile-subscription-bulk-create-jobs/
	 * with JSON:API body format and private API key auth.
	 *
	 * @param string $email Email address.
	 * @return bool True on success.
	 */
	private static function subscribe_klaviyo( $email ) {
		$api_key = get_option( 'exitsurvey_klaviyo_api_key', '' );
		$list_id = get_option( 'exitsurvey_klaviyo_list_id', '' );

		if ( empty( $api_key ) || empty( $list_id ) ) {
			return false;
		}

		$url = 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs';

		$body = wp_json_encode( [
			'data' => [
				'type'       => 'profile-subscription-bulk-create-job',
				'attributes' => [
					'profiles' => [
						'data' => [
							[
								'type'       => 'profile',
								'attributes' => [
									'email'         => $email,
									'subscriptions' => [
										'email' => [
											'marketing' => [
												'consent' => 'SUBSCRIBED',
											],
										],
									],
								],
							],
						],
					],
				],
				'relationships' => [
					'list' => [
						'data' => [
							'type' => 'list',
							'id'   => $list_id,
						],
					],
				],
			],
		] );

		$response = wp_remote_post( $url, [
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Klaviyo-API-Key ' . $api_key,
				'Content-Type'  => 'application/json',
				'revision'      => '2024-10-15',
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( '[ExitSurvey] Klaviyo error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// Klaviyo returns 202 Accepted on success.
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		error_log( '[ExitSurvey] Klaviyo HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
		return false;
	}
}
