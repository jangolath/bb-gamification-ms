/**
 * Public JavaScript
 *
 * @package Crossings_Gamification
 * @since 1.0.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Set featured badge
		$('.cr-set-featured-badge').on('click', function() {
			var $button = $(this);
			var achievementId = $button.data('badge-id');

			$button.prop('disabled', true).text('Setting...');

			$.ajax({
				url: crGamification.ajaxUrl,
				type: 'POST',
				data: {
					action: 'cr_set_featured_badge',
					nonce: crGamification.nonce,
					achievement_id: achievementId
				},
				success: function(response) {
					if (response.success) {
						$('.cr-set-featured-badge').removeClass('button-primary').text('Set as Featured');
						$button.addClass('button-primary').text('Featured');
					} else {
						alert(response.data.message || 'Error setting featured badge');
						$button.prop('disabled', false).text('Set as Featured');
					}
				},
				error: function() {
					alert('Error setting featured badge');
					$button.prop('disabled', false).text('Set as Featured');
				}
			});
		});
	});

})(jQuery);
