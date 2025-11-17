/**
 * Admin JavaScript
 *
 * @package Crossings_Gamification
 * @since 1.0.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Initialize color pickers
		if ($.fn.wpColorPicker) {
			$('.cr-color-picker').wpColorPicker();
		}

		// Confirm delete
		$('.cr-delete-badge').on('click', function(e) {
			if (!confirm(crGamificationAdmin.strings.confirmDelete)) {
				e.preventDefault();
				return false;
			}
		});

		// Confirm revoke
		$('.cr-revoke-achievement').on('click', function(e) {
			if (!confirm(crGamificationAdmin.strings.confirmRevoke)) {
				e.preventDefault();
				return false;
			}
		});
	});

})(jQuery);
