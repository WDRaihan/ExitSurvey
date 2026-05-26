/**
 * ExitSurvey — Email Marketing Admin JS
 *
 * Handles toggle visibility for provider-specific fields on the Settings page
 * and the email collector checkbox visibility on the Questions page.
 *
 * @package ExitSurvey
 */
jQuery(function ($) {
	'use strict';

	/* -----------------------------------------------------------------
	 * SETTINGS PAGE: Toggle provider fields
	 * -------------------------------------------------------------- */
	var $providerSelect = $('#es-email-provider');
	if ($providerSelect.length) {
		$providerSelect.on('change', function () {
			var provider = $(this).val();
			$('.es-provider-fields').slideUp(200);
			if (provider === 'mailchimp') {
				$('#es-mailchimp-fields').slideDown(200);
			} else if (provider === 'klaviyo') {
				$('#es-klaviyo-fields').slideDown(200);
			}
		});
	}

	/* -----------------------------------------------------------------
	 * QUESTIONS PAGE: Show/hide email collector checkbox
	 * -------------------------------------------------------------- */
	$(document).on('change', 'input[name^="q_extra_enabled"]', function () {
		var $card = $(this).closest('.es-question-card');
		var $emailWrap = $card.find('.es-email-collect-wrap');
		if ($(this).is(':checked')) {
			$emailWrap.slideDown(200);
		} else {
			$emailWrap.slideUp(200);
			// Also uncheck the email collector when extra field is disabled.
			$emailWrap.find('input[type="checkbox"]').prop('checked', false);
		}
	});
});
