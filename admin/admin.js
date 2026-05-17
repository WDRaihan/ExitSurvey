/* ExitSurvey Admin JS */
jQuery(function($) {
	// Color picker
	$('.es-color-picker').wpColorPicker();

	// Toggle extra field settings
	$(document).on('change', 'input[name^="q_extra_enabled"]', function() {
		const $wrap = $(this).closest('.es-extra-field-settings').find('.es-extra-field-label-wrap');
		if ($(this).is(':checked')) {
			$wrap.slideDown(200);
		} else {
			$wrap.slideUp(200);
		}
	});

	// Toggle targeting rules
	$(document).on('click', '.es-segment-toggle', function(e) {
		e.preventDefault();
		const $body = $(this).closest('.es-segment-settings').find('.es-segment-body');
		const $arrow = $(this).find('.es-segment-arrow');
		$body.slideToggle(200);
		$arrow.toggleClass('open');
	});
});
