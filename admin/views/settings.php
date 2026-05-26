<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap es-admin">
	<h1 class="es-page-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php echo esc_html__( 'ExitSurvey Settings', 'exitsurvey' ); ?>
	</h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved!', 'exitsurvey' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'exitsurvey_settings' ); ?>
		<input type="hidden" name="action" value="exitsurvey_save_settings">

		<div class="es-settings-grid">
			<!-- General -->
			<div class="es-settings-section">
				<h2><?php echo esc_html__( 'General', 'exitsurvey' ); ?></h2>

				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enable Plugin', 'exitsurvey' ); ?></th>
						<td><label><input type="checkbox" name="exitsurvey_enabled" value="yes" <?php checked( ExitSurvey_Settings::get('enabled'), 'yes' ); ?>> <?php echo esc_html__( 'Enable exit intent survey', 'exitsurvey' ); ?></label></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Show on Mobile', 'exitsurvey' ); ?></th>
						<td><label><input type="checkbox" name="exitsurvey_show_on_mobile" value="yes" <?php checked( ExitSurvey_Settings::get('show_on_mobile'), 'yes' ); ?>> <?php echo esc_html__( 'Show popup on mobile devices', 'exitsurvey' ); ?></label></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Show Cart Items', 'exitsurvey' ); ?></th>
						<td><label><input type="checkbox" name="exitsurvey_show_cart_items" value="yes" <?php checked( ExitSurvey_Settings::get('show_cart_items'), 'yes' ); ?>> <?php echo esc_html__( 'Display cart items in the popup', 'exitsurvey' ); ?></label></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Cookie Duration (days)', 'exitsurvey' ); ?></th>
						<td>
							<input type="number" name="exitsurvey_cookie_days" value="<?php echo esc_attr( ExitSurvey_Settings::get('cookie_days', 3) ); ?>" min="1" max="365" class="small-text">
							<p class="description"><?php echo esc_html__( 'Days before showing the survey again to the same visitor.', 'exitsurvey' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Admin Bypass', 'exitsurvey' ); ?></th>
						<td>
							<label><input type="checkbox" name="exitsurvey_admin_bypass" value="yes" <?php checked( ExitSurvey_Settings::get('admin_bypass'), 'yes' ); ?>> <?php echo esc_html__( 'Bypass cookie duration for administrators (useful for testing)', 'exitsurvey' ); ?></label>
						</td>
					</tr>
				</table>
			</div>

			<!-- Trigger -->
			<div class="es-settings-section">
				<h2><?php echo esc_html__( 'Exit Intent Detection', 'exitsurvey' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Delay (ms)', 'exitsurvey' ); ?></th>
						<td>
							<input type="number" name="exitsurvey_delay_ms" value="<?php echo esc_attr( ExitSurvey_Settings::get('delay_ms', 500) ); ?>" min="0" max="5000" class="small-text"> ms
							<p class="description"><?php echo esc_html__( 'Delay before showing popup after intent detected.', 'exitsurvey' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Sensitivity (px)', 'exitsurvey' ); ?></th>
						<td>
							<input type="number" name="exitsurvey_sensitivity" value="<?php echo esc_attr( ExitSurvey_Settings::get('sensitivity', 20) ); ?>" min="5" max="100" class="small-text"> px
							<p class="description"><?php echo esc_html__( 'How close to the top of the page the cursor needs to be to trigger.', 'exitsurvey' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Popup Content -->
			<div class="es-settings-section">
				<h2><?php echo esc_html__( 'Popup Content', 'exitsurvey' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Popup Title', 'exitsurvey' ); ?></th>
						<td><input type="text" name="exitsurvey_popup_title" value="<?php echo esc_attr( ExitSurvey_Settings::get('popup_title') ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Popup Subtitle', 'exitsurvey' ); ?></th>
						<td><input type="text" name="exitsurvey_popup_subtitle" value="<?php echo esc_attr( ExitSurvey_Settings::get('popup_subtitle') ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Submit Button Label', 'exitsurvey' ); ?></th>
						<td><input type="text" name="exitsurvey_submit_label" value="<?php echo esc_attr( ExitSurvey_Settings::get('submit_label') ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Skip Button Label', 'exitsurvey' ); ?></th>
						<td><input type="text" name="exitsurvey_skip_label" value="<?php echo esc_attr( ExitSurvey_Settings::get('skip_label') ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Thank You Message', 'exitsurvey' ); ?></th>
						<td><input type="text" name="exitsurvey_thank_you_msg" value="<?php echo esc_attr( ExitSurvey_Settings::get('thank_you_msg') ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Brand Color (Start)', 'exitsurvey' ); ?></th>
						<td><input type="text" name="exitsurvey_branding_color" value="<?php echo esc_attr( ExitSurvey_Settings::get('branding_color', '#2563eb') ); ?>" class="es-color-picker"></td>
					</tr>
					<tr>
						<td><input type="text" name="exitsurvey_branding_color_2" value="<?php echo esc_attr( ExitSurvey_Settings::get('branding_color_2', '#3b82f6') ); ?>" class="es-color-picker"></td>
					</tr>
				</table>
			</div>

			<!-- Notifications -->
			<div class="es-settings-section">
				<h2><?php echo esc_html__( 'Email Notifications', 'exitsurvey' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enable Notifications', 'exitsurvey' ); ?></th>
						<td><label><input type="checkbox" name="exitsurvey_email_notify" value="yes" <?php checked( ExitSurvey_Settings::get('email_notify'), 'yes' ); ?>> <?php echo esc_html__( 'Send email on new response', 'exitsurvey' ); ?></label></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Notification Email', 'exitsurvey' ); ?></th>
						<td><input type="email" name="exitsurvey_notify_email" value="<?php echo esc_attr( ExitSurvey_Settings::get('notify_email', get_option('admin_email')) ); ?>" class="regular-text"></td>
					</tr>
				</table>
			</div>

			<?php do_action( 'exitsurvey_settings_sections' ); ?>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary button-hero"><?php echo esc_html__( 'Save Settings', 'exitsurvey' ); ?></button>
		</p>
	</form>
</div>
