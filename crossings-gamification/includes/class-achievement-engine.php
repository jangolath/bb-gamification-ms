<?php
/**
 * Achievement Engine - Core badge award logic.
 *
 * Processes events and determines when badges should be awarded based on
 * achievement criteria and user progress.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes
 * @since      1.0.0
 */

class CR_Gamification_Achievement_Engine {

	/**
	 * Initialize the achievement engine.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Hook into achievement unlock to trigger activity feed posting
		add_action( 'cr_achievement_unlocked', array( __CLASS__, 'handle_achievement_unlock' ), 10, 3 );
	}

	/**
	 * Process an event and check for achievement unlocks.
	 *
	 * @param int    $user_id User ID.
	 * @param string $event_type Event type key.
	 * @param array  $event_data Event data.
	 * @param int    $source_site_id Source site ID.
	 * @return bool True on success, false on failure.
	 */
	public static function process_event( $user_id, $event_type, $event_data = array(), $source_site_id = null ) {
		global $wpdb;

		// Get all achievements matching this event type
		$table_achievements = $wpdb->base_prefix . 'cr_achievements';

		$achievements = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_achievements}
				WHERE trigger_type = %s AND is_active = 1",
				$event_type
			)
		);

		if ( empty( $achievements ) ) {
			return false;
		}

		$unlocked_count = 0;

		foreach ( $achievements as $achievement ) {
			// Check if event matches achievement criteria
			if ( ! self::matches_criteria( $achievement, $event_data ) ) {
				continue;
			}

			// Update progress and check for unlock
			$unlocked = self::check_and_unlock( $user_id, $achievement, $event_data );

			if ( $unlocked ) {
				$unlocked_count++;
			}
		}

		return $unlocked_count > 0;
	}

	/**
	 * Check if event data matches achievement criteria.
	 *
	 * @param object $achievement Achievement object.
	 * @param array  $event_data Event data.
	 * @return bool True if matches, false otherwise.
	 */
	private static function matches_criteria( $achievement, $event_data ) {
		// If no trigger_value (criteria), always match
		if ( empty( $achievement->trigger_value ) ) {
			return true;
		}

		$criteria = json_decode( $achievement->trigger_value, true );

		if ( ! is_array( $criteria ) ) {
			return true;
		}

		// Check vendor-specific criteria
		if ( ! empty( $criteria['vendor_id'] ) ) {
			if ( empty( $event_data['vendor_id'] ) || $event_data['vendor_id'] != $criteria['vendor_id'] ) {
				return false;
			}
		}

		// Check minimum amount criteria
		if ( isset( $criteria['min_amount'] ) && $criteria['min_amount'] > 0 ) {
			if ( empty( $event_data['amount'] ) || $event_data['amount'] < $criteria['min_amount'] ) {
				return false;
			}
		}

		// Check product category criteria
		if ( ! empty( $criteria['product_category'] ) ) {
			if ( empty( $event_data['product_categories'] ) ||
				! in_array( $criteria['product_category'], $event_data['product_categories'] ) ) {
				return false;
			}
		}

		// Check event series for annual events
		if ( ! empty( $criteria['event_series'] ) ) {
			if ( empty( $event_data['event_series'] ) || $event_data['event_series'] !== $criteria['event_series'] ) {
				return false;
			}
		}

		/**
		 * Filter achievement criteria matching.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $matches Whether criteria matches.
		 * @param object $achievement Achievement object.
		 * @param array  $event_data Event data.
		 */
		return apply_filters( 'cr_achievement_matches_criteria', true, $achievement, $event_data );
	}

	/**
	 * Check progress and unlock achievement if threshold is met.
	 *
	 * @param int    $user_id User ID.
	 * @param object $achievement Achievement object.
	 * @param array  $event_data Event data.
	 * @return bool True if unlocked, false otherwise.
	 */
	private static function check_and_unlock( $user_id, $achievement, $event_data ) {
		// Get current progress
		$progress = CR_Gamification_User_Achievements::get_achievement_progress(
			$user_id,
			$achievement->id
		);

		// Already unlocked
		if ( $progress['unlocked'] ) {
			return false;
		}

		// Determine increment value
		$increment = 1;

		// For threshold_type = 'boolean', just unlock immediately
		if ( $achievement->threshold_type === 'boolean' ) {
			return CR_Gamification_User_Achievements::unlock_achievement(
				$user_id,
				$achievement->id,
				'unlocked'
			);
		}

		// For custom threshold types, allow filtering
		if ( $achievement->threshold_type === 'custom' ) {
			/**
			 * Filter custom threshold check.
			 *
			 * @since 1.0.0
			 *
			 * @param bool   $should_unlock Whether to unlock achievement.
			 * @param int    $user_id User ID.
			 * @param object $achievement Achievement object.
			 * @param array  $event_data Event data.
			 */
			$should_unlock = apply_filters(
				'cr_achievement_custom_threshold_check',
				false,
				$user_id,
				$achievement,
				$event_data
			);

			if ( $should_unlock ) {
				return CR_Gamification_User_Achievements::unlock_achievement(
					$user_id,
					$achievement->id,
					'unlocked'
				);
			}

			return false;
		}

		// Update progress
		CR_Gamification_User_Achievements::update_progress(
			$user_id,
			$achievement->id,
			$increment
		);

		// Get updated progress
		$new_progress = CR_Gamification_User_Achievements::get_achievement_progress(
			$user_id,
			$achievement->id
		);

		// Check if threshold is met
		if ( $new_progress['current'] >= $achievement->threshold ) {
			return CR_Gamification_User_Achievements::unlock_achievement(
				$user_id,
				$achievement->id,
				'unlocked'
			);
		}

		return false;
	}

	/**
	 * Handle achievement unlock (post to activity feed, etc.).
	 *
	 * @param int    $user_id User ID.
	 * @param int    $achievement_id Achievement ID.
	 * @param string $action_type Action type.
	 */
	public static function handle_achievement_unlock( $user_id, $achievement_id, $action_type ) {
		// Only handle automatic unlocks for activity feed
		if ( $action_type !== 'unlocked' ) {
			return;
		}

		// Get achievement details
		global $wpdb;
		$table_achievements = $wpdb->base_prefix . 'cr_achievements';

		$achievement = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_achievements} WHERE id = %d",
				$achievement_id
			)
		);

		if ( ! $achievement ) {
			return;
		}

		/**
		 * Fires when an achievement is unlocked (after processing).
		 *
		 * Use this hook for additional processing like notifications, emails, etc.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id User ID.
		 * @param object $achievement Achievement object.
		 */
		do_action( 'cr_after_achievement_unlocked', $user_id, $achievement );
	}

	/**
	 * Recalculate achievements for a user.
	 *
	 * Useful for fixing progress or recalculating after changes.
	 *
	 * @param int    $user_id User ID.
	 * @param string $trigger_type Optional specific trigger type to recalculate.
	 * @return array Array of unlocked achievement IDs.
	 */
	public static function recalculate_user_achievements( $user_id, $trigger_type = '' ) {
		global $wpdb;

		$unlocked = array();

		// This is a complex operation that would need to:
		// 1. Get all user's activities/interactions
		// 2. Recount them
		// 3. Update progress
		// 4. Unlock achievements as needed

		// For V1, this is a placeholder for future implementation
		// It would require integration-specific logic to count actual activities

		/**
		 * Allows custom recalculation logic.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $unlocked Array of unlocked achievement IDs.
		 * @param int    $user_id User ID.
		 * @param string $trigger_type Trigger type.
		 */
		return apply_filters( 'cr_recalculate_user_achievements', $unlocked, $user_id, $trigger_type );
	}

	/**
	 * Bulk recalculate achievements for all users.
	 *
	 * Should be run via WP-CLI or admin action, not on regular page loads.
	 *
	 * @param int $batch_size Number of users to process per batch.
	 * @return array Statistics about the recalculation.
	 */
	public static function bulk_recalculate( $batch_size = 50 ) {
		global $wpdb;

		$stats = array(
			'users_processed' => 0,
			'achievements_unlocked' => 0,
			'errors' => 0,
		);

		// Get all users
		$users = get_users(
			array(
				'number' => $batch_size,
				'fields' => 'ID',
			)
		);

		foreach ( $users as $user_id ) {
			try {
				$unlocked = self::recalculate_user_achievements( $user_id );
				$stats['users_processed']++;
				$stats['achievements_unlocked'] += count( $unlocked );
			} catch ( Exception $e ) {
				$stats['errors']++;
				error_log( 'CR Gamification: Error recalculating for user ' . $user_id . ' - ' . $e->getMessage() );
			}
		}

		return $stats;
	}

	/**
	 * Get achievements available for a specific trigger type.
	 *
	 * @param string $trigger_type Trigger type.
	 * @return array Achievements.
	 */
	public static function get_achievements_by_trigger( $trigger_type ) {
		global $wpdb;

		$table_achievements = $wpdb->base_prefix . 'cr_achievements';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_achievements}
				WHERE trigger_type = %s AND is_active = 1
				ORDER BY threshold ASC",
				$trigger_type
			)
		);
	}

	/**
	 * Get next tier achievement for a user.
	 *
	 * Finds the next achievement in a progression (e.g., next friend count tier).
	 *
	 * @param int    $user_id User ID.
	 * @param string $trigger_type Trigger type.
	 * @param int    $current_achievement_id Current achievement ID.
	 * @return object|null Next achievement or null.
	 */
	public static function get_next_tier( $user_id, $trigger_type, $current_achievement_id ) {
		global $wpdb;

		$table_achievements = $wpdb->base_prefix . 'cr_achievements';

		// Get current achievement threshold
		$current = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT threshold FROM {$table_achievements} WHERE id = %d",
				$current_achievement_id
			)
		);

		if ( ! $current ) {
			return null;
		}

		// Get next achievement with higher threshold
		$next = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_achievements}
				WHERE trigger_type = %s
				AND threshold > %d
				AND is_active = 1
				ORDER BY threshold ASC
				LIMIT 1",
				$trigger_type,
				$current->threshold
			)
		);

		return $next;
	}

	/**
	 * Preview what achievements a user would unlock with given progress.
	 *
	 * Useful for showing "X more to next badge" messages.
	 *
	 * @param int    $user_id User ID.
	 * @param string $trigger_type Trigger type.
	 * @param int    $hypothetical_count Hypothetical count to check.
	 * @return array Achievements that would be unlocked.
	 */
	public static function preview_unlocks( $user_id, $trigger_type, $hypothetical_count ) {
		$achievements = self::get_achievements_by_trigger( $trigger_type );
		$would_unlock = array();

		foreach ( $achievements as $achievement ) {
			$progress = CR_Gamification_User_Achievements::get_achievement_progress(
				$user_id,
				$achievement->id
			);

			// Skip already unlocked
			if ( $progress['unlocked'] ) {
				continue;
			}

			// Check if hypothetical count would unlock it
			if ( $hypothetical_count >= $achievement->threshold ) {
				$would_unlock[] = $achievement;
			}
		}

		return $would_unlock;
	}
}
