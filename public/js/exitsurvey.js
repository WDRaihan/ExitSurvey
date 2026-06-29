/**
 * ExitSurvey - Smart Exit Survey for WooCommerce
 * Front-end core: tracks browsing, detects exit intent, renders popup.
 *
 * @package ExitSurvey
 */
(function ($) {
  'use strict';

  /* =========================================================
   * CONFIG & CONSTANTS
   * ====================================================== */
  const CFG = window.ExitSurveyConfig || {};
  const STORAGE_KEY  = 'es_history';
  const SESSION_KEY  = 'es_session_id';
  const COOKIE_KEY   = 'es_shown';

  /* =========================================================
   * UTILITIES
   * ====================================================== */
  function generateId() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      const r = Math.random() * 16 | 0;
      return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
  }

  function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = name + '=' + value + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
  }

  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
  }

  function isMobile() {
    return /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);
  }

  /* =========================================================
   * SESSION
   * ====================================================== */
  function getSessionId() {
    let sid = sessionStorage.getItem(SESSION_KEY);
    if (!sid) {
      sid = generateId();
      sessionStorage.setItem(SESSION_KEY, sid);
    }
    return sid;
  }

  /* =========================================================
   * BROWSING HISTORY TRACKER (localStorage)
   * ====================================================== */
  const History = {
    MAX_ENTRIES: 20,

    load() {
      try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      } catch (e) {
        return [];
      }
    },

    save(history) {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
      } catch (e) { /* localStorage full or unavailable */ }
    },

    record(ctx) {
      const history = this.load();
      history.push({
        url:       ctx.pageUrl,
        title:     ctx.pageTitle,
        isCart:    ctx.isCart,
        isCheckout:ctx.isCheckout,
        isShop:    ctx.isShop,
        isProduct: ctx.isProduct,
        productId: ctx.productId,
        ts:        Date.now(),
      });
      // Keep only latest MAX_ENTRIES
      if (history.length > this.MAX_ENTRIES) {
        history.splice(0, history.length - this.MAX_ENTRIES);
      }
      this.save(history);
    },

    /** Analyse history to determine best trigger type. */
    resolveTrigger(cartCount, currentPage) {
      // When a current page context is provided (admin bypass), use it directly
      if (currentPage) {
        if (currentPage.isCheckout && cartCount > 0) return 'checkout';
        if (currentPage.isCart && cartCount > 0) return 'cart';
        if (currentPage.isProduct) return 'product';
        if (currentPage.isShop) return 'shop';
        return 'general';
      }

      const history = this.load();

      const visitedCheckout = history.some(h => h.isCheckout);
      const visitedProduct  = history.some(h => h.isProduct);
      const visitedShop     = history.some(h => h.isShop);

      if (visitedCheckout && cartCount > 0) return 'checkout';
      if (cartCount > 0)   return 'cart';
      if (visitedProduct)  return 'product';
      if (visitedShop)     return 'shop';
      return 'general';
    },

    clear() {
      localStorage.removeItem(STORAGE_KEY);
    },

    serialize() {
      return JSON.stringify(this.load());
    },
  };

  /* =========================================================
   * POPUP UI
   * ====================================================== */
  const Popup = {
    $overlay:   null,
    $popup:     null,
    shown:      false,
    cartData:   null,
    question:   null,
    answer:     null,
    triggerType: 'general',
    couponCode:   null,
    couponDiscount: null,

    init() {
      this.$overlay = $('#es-overlay');
      this.$popup   = $('#es-popup');

      if (!this.$overlay.length) return;

      // Apply branding colours
      const color  = CFG.brandingColor || '#7c3aed';
      const color2 = CFG.brandingColor2 || '#a855f7';
      document.documentElement.style.setProperty('--es-brand', color);
      document.documentElement.style.setProperty('--es-brand-2', color2);

      // Populate static labels
      $('#es-popup-title').text(CFG.popupTitle || 'Wait! Before you go...');
      $('#es-popup-subtitle').text(CFG.popupSubtitle || '');
      $('#es-submit-btn').text(CFG.submitLabel || 'Submit');
      $('#es-skip-btn').text(CFG.skipLabel || 'No thanks');

      // Events
      $('#es-close-btn, #es-skip-btn').on('click', () => this.close());
      this.$overlay.on('click', (e) => { if ($(e.target).is(this.$overlay)) this.close(); });
      $('#es-submit-btn').on('click', () => this.submitAnswer());
      $(document).on('keydown', (e) => { if (e.key === 'Escape' && this.shown) this.close(); });
    },

    hasMatchingQuestion(triggerType, cartData) {
      const allQ   = CFG.questions || {};
      let questions = allQ[triggerType] || allQ['general'] || [];

      // Client-side cart value filtering
      const cartValue = cartData ? parseFloat(cartData.raw_total || 0) : 0;
      questions = questions.filter(q => {
        const seg = q.segment_rules || {};
        const minCart = parseFloat(seg.min_cart_value || 0);
        const maxCart = parseFloat(seg.max_cart_value || 0);
        if (minCart > 0 && cartValue < minCart) return false;
        if (maxCart > 0 && cartValue > maxCart) return false;
        return true;
      });

      return questions.length > 0;
    },

    open(triggerType, cartData) {
      if (this.shown) return;

      if (!this.hasMatchingQuestion(triggerType, cartData)) {
        return;
      }

      this.shown      = true;
      this.triggerType = triggerType;
      this.cartData   = cartData;

      this.renderCart(cartData, triggerType);
      this.renderQuestion(triggerType, cartData);

      this.$overlay.fadeIn(200);
      this.$popup.addClass('es-popup--enter');
      $('body').addClass('es-no-scroll');
      this.$popup.find('#es-submit-btn, #es-skip-btn, #es-close-btn').first().trigger('focus');
    },

    close() {
      this.shown = false;
      CountdownTimer.stop();
      this.$overlay.fadeOut(200, () => {
        $('body').removeClass('es-no-scroll');
      });

      // Reset history for admin bypass testing (fresh trigger on next page)
      const bypass = CFG.isAdmin && CFG.adminBypass;
      if (bypass) {
        History.clear();
      }
    },

    renderCart(cartData, trigger) {
      const $section = $('#es-cart-section');
      if (!CFG.showCartItems || !cartData || !cartData.items || cartData.items.length === 0 || trigger === 'shop') {
        $section.hide();
        return;
      }

      const $items = $('#es-cart-items').empty();
      cartData.items.forEach(item => {
        $items.append(
          $('<div>').addClass('es-cart-item').append(
            $('<img>').attr({ src: item.image, alt: item.name }).addClass('es-cart-item__img'),
            $('<div>').addClass('es-cart-item__info').append(
              $('<div>').addClass('es-cart-item__name').text(item.name),
              $('<div>').addClass('es-cart-item__meta').html('Qty: ' + item.qty + ' &middot; ' + item.price)
            )
          )
        );
      });

      const $footer = $('#es-cart-footer').empty();
      $footer.append(
        $('<div>').addClass('es-cart-total').html('<span>Total:</span><strong>' + cartData.total + '</strong>'),
        $('<a>').addClass('es-btn es-btn--cart').attr('href', cartData.checkout_url).text('✓ Complete Purchase')
      );

      $section.show();
    },

    renderQuestion(triggerType, cartData) {
      const allQ   = CFG.questions || {};
      let questions = allQ[triggerType] || allQ['general'] || [];

      // Client-side cart value filtering
      const cartValue = cartData ? parseFloat(cartData.raw_total || 0) : 0;
      questions = questions.filter(q => {
        const seg = q.segment_rules || {};
        const minCart = parseFloat(seg.min_cart_value || 0);
        const maxCart = parseFloat(seg.max_cart_value || 0);
        if (minCart > 0 && cartValue < minCart) return false;
        if (maxCart > 0 && cartValue > maxCart) return false;
        return true;
      });

      if (!questions.length) {
        // No question matches — just show cart
        $('#es-survey-section').hide();
        return;
      }

      // Pick first matching question
      this.question = questions[0];
      this.answer   = null;

      const $q    = $('#es-question-text').text(this.question.question_text);
      const $opts = $('#es-options').empty();
      const $txt  = $('#es-text-answer');

      if (this.question.question_type === 'text') {
        $opts.hide();
        $txt.show().val('');
      } else {
        $txt.hide();
        $opts.show();
        const options = this.question.options || [];
        options.forEach((opt, idx) => {
          const id = 'es-opt-' + idx;
          $opts.append(
            $('<label>').addClass('es-option').attr('for', id).append(
              $('<input>').attr({ type: 'radio', name: 'es_answer', id: id, value: opt }),
              $('<span>').text(opt)
            )
          );
        });
      }

      // Handle extra field (per-question)
      const $extraContainer = $('#es-extra-field-container');
      if (this.question && this.question.extra_field_enabled) {
        $('#es-extra-field-label').text(this.question.extra_field_label || '');
        $('#es-extra-field-input').attr('placeholder', this.question.extra_field_placeholder || '').val('');
        $extraContainer.show();
      } else {
        $extraContainer.hide();
      }
    },

    submitAnswer() {
      let answer = '';

      if (this.question && this.question.question_type === 'text') {
        answer = $('#es-text-answer').val().trim();
      } else {
        answer = $('input[name="es_answer"]:checked').val() || '';
      }

      if (!answer) {
        this.shake();
        return;
      }

      this.answer = answer;
      this.showLoading();

      const extraInfo = $('#es-extra-field-input').val() || '';
      const finalAnswer = extraInfo ? `${answer} | Note: ${extraInfo}` : answer;

      const payload = {
        action:        'exitsurvey_submit',
        nonce:         CFG.nonce,
        session_id:    getSessionId(),
        question_id:   this.question ? this.question.question_key : 'unknown',
        question_text: this.question ? this.question.question_text : '',
        answer:        finalAnswer,
        trigger_type:  this.triggerType,
        cart_value:    this.cartData ? this.cartData.raw_total : 0,
        cart_items:    this.cartData ? JSON.stringify(this.cartData.items) : '',
        page_history:  History.serialize(),
      };

      $.post(CFG.ajaxUrl, payload)
        .done((res) => {
          this.hideLoading();
          // Capture coupon data if returned.
          if (res && res.success && res.data) {
            this.couponCode     = res.data.coupon_code     || null;
            this.couponDiscount = res.data.coupon_discount || null;
            this.couponExpiry   = res.data.coupon_expiry   || 0;

            if (this.couponCode) {
              try {
                localStorage.setItem('es_coupon_code', this.couponCode);
                if (this.couponDiscount) localStorage.setItem('es_coupon_discount', this.couponDiscount);
                if (this.couponExpiry)   localStorage.setItem('es_coupon_expiry', this.couponExpiry);
              } catch (e) {
                // Ignore localStorage errors (e.g., incognito)
              }
            }
          }
          this.showThankYou();
        })
        .fail(() => {
          this.hideLoading();
          this.showThankYou();
        });
    },

    showThankYou() {
      $('#es-survey-section').hide();
      $('#es-cart-section').hide();
      $('#es-thankyou').show();
      $('#es-thankyou-msg').text(CFG.thankYouMsg || 'Thank you! 🙏');

      // Show coupon banner if a code was returned.
      if (this.couponCode) {
        $('#es-coupon-code-text').text(this.couponCode);
        if (this.couponDiscount) {
          $('#es-coupon-discount').text('Save ' + this.couponDiscount + ' on your order!');
        }
        $('#es-coupon-banner').slideDown(300);
        CountdownTimer.start(CFG.couponCountdownMinutes || 10);

        // Copy to clipboard.
        $('#es-copy-coupon-btn').off('click').on('click', () => {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(this.couponCode).then(() => {
              $('#es-copy-icon').text('✅');
              setTimeout(() => $('#es-copy-icon').text('📋'), 2000);
            });
          } else {
            // Fallback for older browsers.
            const $tmp = $('<textarea>').val(this.couponCode).appendTo('body').select();
            document.execCommand('copy');
            $tmp.remove();
            $('#es-copy-icon').text('✅');
            setTimeout(() => $('#es-copy-icon').text('📋'), 2000);
          }
        });
      }

      if (this.cartData && this.cartData.checkout_url) {
        $('#es-checkout-btn').attr('href', this.cartData.checkout_url).show();
      }
      // Auto-close after 5s (or longer if coupon shown).
      const autoClose = this.couponCode ? 8000 : 4000;
      setTimeout(() => this.close(), autoClose);
    },

    showLoading() { $('#es-loading').show(); },
    hideLoading() { $('#es-loading').hide(); },

    shake() {
      this.$popup.addClass('es-popup--shake');
      setTimeout(() => this.$popup.removeClass('es-popup--shake'), 400);
    },
  };

  /* =========================================================
   * COUNTDOWN TIMER
   * ====================================================== */
  const CountdownTimer = {
    _interval: null,

    /**
     * Start a countdown of `minutes` minutes, updating #es-countdown-timer
     * every second. When it hits 0:00, hide the countdown and show
     * the expired message.
     *
     * @param {number} minutes
     */
    start(minutes) {
      this.stop(); // Clear any previous timer.

      let remaining = Math.max(1, Math.round(minutes)) * 60; // seconds

      const $timer   = $('#es-countdown-timer');
      const $wrap    = $('#es-countdown-wrap');
      const $expired = $('#es-coupon-expired');

      const tick = () => {
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        $timer.text(String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0'));

        if (remaining <= 0) {
          this.stop();
          $wrap.hide();
          $expired.fadeIn(400);
        }
        remaining--;
      };

      tick(); // Show immediately.
      this._interval = setInterval(tick, 1000);
    },

    stop() {
      if (this._interval) {
        clearInterval(this._interval);
        this._interval = null;
      }
    },
  };

  /* =========================================================
   * PERSISTENT COUPON BANNER
   * ====================================================== */
  const PersistentCouponBanner = {
    init() {
      // If we are on the order received page, the user has checked out. Clear the coupon.
      if (CFG.isOrderReceived) {
        this.clearCoupon();
        return;
      }

      // Check if a coupon is dismissed for the session.
      if (sessionStorage.getItem('es_coupon_banner_dismissed')) {
        return;
      }

      const code   = localStorage.getItem('es_coupon_code');
      const expiry = parseInt(localStorage.getItem('es_coupon_expiry') || '0', 10);

      if (!code) {
        return;
      }

      // Check expiry (expiry is in seconds, Date.now() is in milliseconds)
      if (expiry > 0 && (Date.now() / 1000) > expiry) {
        this.clearCoupon();
        return;
      }

      this.render(code);
    },

    clearCoupon() {
      localStorage.removeItem('es_coupon_code');
      localStorage.removeItem('es_coupon_discount');
      localStorage.removeItem('es_coupon_expiry');
    },

    render(code) {
      // Don't show if the main popup is currently visible.
      if ($('#es-overlay').is(':visible')) {
        return; // It will show on the next page load.
      }

      const $banner = $(`
        <div class="es-floating-coupon-banner" id="es-floating-coupon-banner">
          <div class="es-floating-coupon-banner__content">
            <span class="es-floating-coupon-banner__icon">🎉</span>
            <span class="es-floating-coupon-banner__text">
              ${CFG.thankYouMsg ? 'Your discount is unlocked!' : 'Your discount is unlocked!'}
            </span>
            <div class="es-floating-coupon-banner__badge">
              <span class="es-floating-coupon-banner__code">${code}</span>
              <button class="es-floating-coupon-banner__copy" id="es-floating-copy-btn" aria-label="Copy coupon code">
                <span id="es-floating-copy-icon">📋</span>
              </button>
            </div>
          </div>
          <button class="es-floating-coupon-banner__close" id="es-floating-close-btn" aria-label="Close">&times;</button>
        </div>
      `);

      $('body').append($banner);

      // Copy to clipboard
      $('#es-floating-copy-btn').on('click', () => {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(code).then(() => {
            $('#es-floating-copy-icon').text('✅');
            setTimeout(() => $('#es-floating-copy-icon').text('📋'), 2000);
          });
        } else {
          const $tmp = $('<textarea>').val(code).appendTo('body').select();
          document.execCommand('copy');
          $tmp.remove();
          $('#es-floating-copy-icon').text('✅');
          setTimeout(() => $('#es-floating-copy-icon').text('📋'), 2000);
        }
      });

      // Dismiss banner
      $('#es-floating-close-btn').on('click', () => {
        $banner.removeClass('es-floating-coupon-banner--visible');
        setTimeout(() => $banner.remove(), 400);
        sessionStorage.setItem('es_coupon_banner_dismissed', '1');
      });

      // Slide it in slightly after page load
      setTimeout(() => {
        $banner.addClass('es-floating-coupon-banner--visible');
      }, 500);
    }
  };

  /* =========================================================
   * EXIT INTENT DETECTOR
   * ====================================================== */
  const ExitIntent = {
    triggered: false,
    timer:     null,

    init() {
      // Mouse leave at top
      $(document).on('mouseleave', (e) => {
        if (e.clientY <= (CFG.sensitivity || 20)) {
          this.schedule();
        }
      });

      // Cancel if mouse comes back
      $(document).on('mouseenter', () => {
        clearTimeout(this.timer);
      });

      // Mobile: back button / page hide
      if (CFG.showOnMobile && isMobile()) {
        window.addEventListener('pagehide', () => this.fire(), { once: true });
      }
    },

    schedule() {
      if (this.triggered || Popup.shown) return;
      clearTimeout(this.timer);
      this.timer = setTimeout(() => this.fire(), CFG.delayMs || 500);
    },

    fire() {
      if (this.triggered || Popup.shown) return;

      const bypass = CFG.isAdmin && CFG.adminBypass;
      if (!bypass && getCookie(COOKIE_KEY)) return; // Already shown recently

      this.triggered = true;
      if (!bypass) {
        setCookie(COOKIE_KEY, '1', CFG.cookieDays || 3);
      }

      // Determine trigger from history
      ES.launch();
    },
  };

  /* =========================================================
   * MAIN CONTROLLER
   * ====================================================== */
  const ES = {
    init() {
      if (!CFG.enabled) return;
      if (isMobile() && !CFG.showOnMobile) return;

      PersistentCouponBanner.init();

      // Only check excluded pages / exit intent if popup is meant to trigger
      if (CFG.excludedPages && CFG.excludedPages.length > 0) {
        if (CFG.excludedPages.includes(window.location.pathname)) return;
      }

      // Record current page visit
      History.record(CFG.pageContext || {});

      // Boot modules
      Popup.init();
      ExitIntent.init();
    },

    launch() {
      // Fetch live cart from server, then determine trigger & open popup
      $.post(CFG.ajaxUrl, { action: 'exitsurvey_get_cart', nonce: CFG.nonce }, (res) => {
        const cartData   = res.success ? res.data : null;
        const cartCount  = cartData ? (cartData.count || 0) : 0;
        const bypass     = CFG.isAdmin && CFG.adminBypass;
        const trigger    = History.resolveTrigger(cartCount, bypass ? CFG.pageContext : null);

        Popup.open(trigger, cartData);
      }).fail(() => {
        // Open with no cart data on network failure
        const bypass  = CFG.isAdmin && CFG.adminBypass;
        const trigger = History.resolveTrigger(0, bypass ? CFG.pageContext : null);
        Popup.open(trigger, null);
      });
    },
  };

  /* =========================================================
   * BOOT
   * ====================================================== */
  $(function () {
    ES.init();
  });

})(jQuery);
