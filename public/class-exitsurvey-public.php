<?php
/**
 * Public-facing functionality.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Public {

	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_footer',          [ __CLASS__, 'render_popup_html' ] );
	}

	/**
	 * Enqueue front-end assets.
	 */
	public static function enqueue_assets() {
		if ( ExitSurvey_Settings::get( 'enabled' ) !== 'yes' ) {
			return;
		}

		wp_enqueue_style(
			'exitsurvey-popup',
			EXITSURVEY_URL . 'public/css/exitsurvey-popup.css',
			[],
			EXITSURVEY_VERSION
		);

		wp_enqueue_script(
			'exitsurvey-public',
			EXITSURVEY_URL . 'public/js/exitsurvey.js',
			[ 'jquery' ],
			EXITSURVEY_VERSION,
			true
		);

		// Pass config + questions to JS
		$config     = ExitSurvey_Settings::get_js_config();
		$questions  = ExitSurvey_Settings::get_questions_by_trigger();

		// Determine current page context
		$page_context = self::get_page_context();

		wp_localize_script( 'exitsurvey-public', 'ExitSurveyConfig', array_merge( $config, [
			'questions'   => $questions,
			'pageContext' => $page_context,
			'isAdmin'     => current_user_can( 'manage_woocommerce' ),
		] ) );
	}

	/**
	 * Return context info about the current page.
	 */
	private static function get_page_context() {
		return [
			'isCart'     => is_cart(),
			'isCheckout' => is_checkout(),
			'isShop'     => is_shop() || is_product_category() || is_product_tag(),
			'isProduct'  => is_product(),
			'productId'  => is_product() ? get_the_ID() : 0,
			'pageUrl'    => esc_url( home_url( add_query_arg( [] ) ) ),
			'pageTitle'  => get_the_title(),
		];
	}

	/**
	 * Render the popup HTML shell.
	 */
	public static function render_popup_html() {
		if ( ExitSurvey_Settings::get( 'enabled' ) !== 'yes' ) {
			return;
		}
		?>
		<div id="es-overlay" class="es-overlay" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Exit Survey', 'exitsurvey' ); ?>" style="display:none;">
			<div class="es-popup" id="es-popup">
				<button class="es-close" id="es-close-btn" aria-label="<?php esc_attr_e( 'Close', 'exitsurvey' ); ?>">&#x2715;</button>

				<div class="es-popup__header">
					<div class="es-popup__icon">😔</div>
					<h2 class="es-popup__title" id="es-popup-title"></h2>
					<p class="es-popup__subtitle" id="es-popup-subtitle"></p>
				</div>

				<!-- Cart items section -->
				<div class="es-cart-section" id="es-cart-section" style="display:none;">
					<h3 class="es-cart-title">
						<span>🛒</span>
						<?php echo esc_html__( 'Your Cart', 'exitsurvey' ); ?>
					</h3>
					<div class="es-cart-items" id="es-cart-items"></div>
					<div class="es-cart-footer" id="es-cart-footer"></div>
				</div>

				<!-- Survey section -->
				<div class="es-survey-section" id="es-survey-section">
					<p class="es-question-text" id="es-question-text"></p>

					<!-- Multiple choice options -->
					<div class="es-options" id="es-options"></div>

					<!-- Open text input -->
					<textarea class="es-textarea" id="es-text-answer" rows="3" placeholder="<?php esc_attr_e( 'Share your thoughts...', 'exitsurvey' ); ?>" style="display:none;"></textarea>

					<!-- Extra Field -->
					<div id="es-extra-field-container" class="es-extra-field" style="display:none;">
						<label id="es-extra-field-label" class="es-extra-label"></label>
						<input type="text" id="es-extra-field-input" class="es-extra-input">
					</div>

					<div class="es-actions">
						<button class="es-btn es-btn--primary" id="es-submit-btn"></button>
						<button class="es-btn es-btn--ghost" id="es-skip-btn"></button>
					</div>
				</div>

				<!-- Thank you state -->
				<div class="es-thankyou" id="es-thankyou" style="display:none;">
					<div class="es-thankyou__icon">🙏</div>
					<p class="es-thankyou__msg" id="es-thankyou-msg"></p>

					<!-- Coupon banner (shown when auto-coupon is enabled & generated) -->
					<div id="es-coupon-banner" class="es-coupon-banner" style="display:none;" aria-live="polite">
						<p class="es-coupon-banner__label">🎉 <?php echo esc_html__( "Here's your exclusive discount!", 'exitsurvey' ); ?></p>
						<div class="es-coupon-badge" id="es-coupon-badge">
							<span class="es-coupon-badge__code" id="es-coupon-code-text"></span>
							<button class="es-coupon-badge__copy" id="es-copy-coupon-btn" aria-label="<?php esc_attr_e( 'Copy coupon code', 'exitsurvey' ); ?>">
								<span id="es-copy-icon">📋</span>
							</button>
						</div>
						<p class="es-coupon-discount" id="es-coupon-discount"></p>
						<div class="es-countdown-wrap" id="es-countdown-wrap">
							<span class="es-countdown-label"><?php echo esc_html__( 'Offer expires in', 'exitsurvey' ); ?></span>
							<span class="es-countdown-timer" id="es-countdown-timer">10:00</span>
						</div>
						<p class="es-coupon-expired" id="es-coupon-expired" style="display:none;">
							⏰ <?php echo esc_html__( "Offer expired — use the code above, it's still valid!", 'exitsurvey' ); ?>
						</p>
					</div>

					<a href="#" class="es-btn es-btn--primary" id="es-checkout-btn" style="display:none;">
						<?php echo esc_html__( 'Complete My Purchase →', 'exitsurvey' ); ?>
					</a>
				</div>

				<div class="es-loading" id="es-loading" style="display:none;">
					<div class="es-spinner"></div>
				</div>
			</div>
		</div>
		<?php
	}
}
