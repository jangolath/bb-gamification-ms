<?php
/**
 * User Achievements Manager.
 *
 * Handles user achievement progress, badge unlocks, and award tracking.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes
 * @since      1.0.0
 */

class CR_Gamification_User_Achievements {

	/**
	 * Get user's achievements.
	 *
	 * @param int    $user_id User ID.
	 * @param array  $args {
	 *     Optional query arguments.
	 *
	 *     @type string $category        Achievement category filter.
	 *     @type bool   $unlocked_only   Only return unlocked achievements.
	 *     @type bool   $featured_only   Only return featured achievement.
	 *     @type string $order_by        Order by field (default: 'unlocked_at').
	 *     @type string $order           Order direction (ASC|DESC, default: DESC).
	 *     @type int    $limit           Limit number of results.
	 * }
	 * @return array User achievements.
	 */
	public static function get_user_achievements( $user_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'category'      => '',
			'unlocked_only' => false,
			'featured_only' => false,
			'order_by'      => 'unlocked_at',
			'order'         => 'DESC',
			'limit'         => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$network_id              = get_current_network_id();
		$table_user_achievements = $wpdb->base_prefix . 'cr_user_achievements';
		$table_achievements      = $wpdb->base_prefix . 'cr_achievements';

		// Build query
		$where = array(
			$wpdb->prepare( 'ua.user_id = %d', $user_id ),
			$wpdb->prepare( 'ua.network_id = %d', $network_id ),
			'a.is_active = 1',
		);

		if ( ! empty( $args['category'] ) ) {
			$where[] = $wpdb->prepare( 'a.category = %s', $args['category'] );
		}

		if ( $args['unlocked_only'] ) {
			$where[] = 'ua.unlocked_at IS NOT NULL';
		}

		if ( $args['featured_only'] ) {
			$where[] = 'ua.is_featured = 1';
		}

		$where_clause = implode( ' AND ', $where );

		// Order
		$order_by = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );
		if ( ! $order_by ) {
			$order_by = 'unlocked_at DESC';
		}

		// Limit
		$limit_clause = '';
		if ( $args['limit'] > 0 ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d', $args['limit'] );
		}

		$query = "SELECT ua.*, a.*
			FROM {$table_user_achievements} ua
			INNER JOIN {$table_achievements} a ON ua.achievement_id = a.id
			WHERE {$where_clause}
			ORDER BY {$order_by}
			{$limit_clause}";

		return $wpdb->get_results( $query );
	}

	/**
	 * Get user's achievement count.
	 *
	 * @param int    $user_id User ID.
	 * @param string $category Optional category filter.
	 * @return int Achievement count.
	 */
	public static function get_user_achievement_count( $user_id, $category = '' ) {
		// Try cache first
		$network_id = get_current_network_id();
		$cached     = CR_Gamification_Cache_Manager::get_user_badge_count( $user_id, $network_id, $category );

		if ( $cached !== false ) {
			return (int) $cached;
		}

		global $wpdb;

		$table_user_achievements = $wpdb->base_prefix . 'cr_user_achievements';
		$table_achievements      = $wpdb->base_prefix . 'cr_achievements';

		$where = array(
			$wpdb->prepare( 'ua.user_id = %d', $user_id ),
			$wpdb->prepare( 'ua.network_id = %d', $network_id ),
			'ua.unlocked_at IS NOT NULL',
			'a.is_active = 1',
		);

		if ( ! empty( $category ) ) {
			$where[] = $wpdb->prepare( 'a.category = %s', $category );
		}

		$where_clause = implode( ' AND ', $where );

		$count = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$table_user_achievements} ua
			INNER JOIN {$table_achievements} a ON ua.achievement_id = a.id
			WHERE {$where_clause}"
		);

		$count = $count ? (int) $count : 0;

		// Cache the result
		CR_Gamification_Cache_Manager::set_user_badge_count( $user_id, $network_id, $count, $category );

		return $count;
	}

	/**
	 * Update user achievement progress.
	 *
	 * @param int $user_id User ID.
	 * @param int $achievement_id Achievement ID.
	 * @param int $increment Increment value (default: 1).
	 * @return bool True on success, false on failure.
	 */
	public static function update_progress( $user_id, $achievement_id, $increment = 1 ) {
		global $wpdb;

		$network_id              = get_current_network_id();
		$table_user_achievements = $wpdb->base_prefix . 'cr_user_achievements';

		// Check if record exists
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_user_achievements}
				WHERE user_id = %d AND achievement_id = %d",
				$user_id,
				$achievement_id
			)
		);

		if ( $existing ) {
			// Update existing record
			$result = $wpdb->update(
				$table_user_achievements,
				array( 'current_count' => $existing->current_count + $increment ),
				array(
					'user_id'        => $user_id,
					'achievement_id' => $achievement_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);

			return $result !== false;
		} else {
			// Insert new record
			$result = $wpdb->insert(
				$table_user_achievements,
				array(
					'user_id'        => $user_id,
					'achievement_id' => $achievement_id,
					'current_count'  => $increment,
					'network_id'     => $network_id,
				),
				array( '%d', '%d', '%d', '%d' )
			);

			return $result !== false;
		}
	}

	/**
	 * Unlock an achievement for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $achievement_id Achievement ID.
	 * @param string $action_type Action type (unlocked|manual_grant).
	 * @param int    $admin_user_id Admin user ID (for manual grants).
	 * @param string $notes Optional notes.
	 * @return bool True on success, false on failure.
	 */
	public static function unlock_achievement( $user_id, $achievement_id, $action_type = 'unlocked', $admin_user_id = null, $notes = '' ) {
		global $wpdb;

		$network_id              = get_current_network_id();
		$table_user_achievements = $wpdb->base_prefix . 'cr_user_achievements';
		$table_achievement_history = $wpdb->base_prefix . 'cr_achievement_history';

		// Check if already unlocked
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_user_achievements}
				WHERE user_id = %d AND achievement_id = %d",
				$user_id,
				$achievement_id
			)
		);

		$unlocked_at = current_time( 'mysql' );

		if ( $existing ) {
			// Already exists, just update unlock time if not already unlocked
			if ( $existing->unlocked_at ) {
				return false; // Already unlocked
			}

			$wpdb->update(
				$table_user_achievements,
				array( 'unlocked_at' => $unlocked_at ),
				array(
					'user_id'        => $user_id,
					'achievement_id' => $achievement_id,
				),
				array( '%s' ),
				array( '%d', '%d' )
			);
		} else {
			// Insert new unlocked achievement
			$wpdb->insert(
				$table_user_achievements,
				array(
					'user_id'        => $user_id,
					'achievement_id' => $achievement_id,
					'current_count'  => 1,
					'unlocked_at'    => $unlocked_at,
					'network_id'     => $network_id,
				),
				array( '%d', '%d', '%d', '%s', '%d' )
			);
		}

		// Add to history
		$wpdb->insert(
			$table_achievement_history,
			array(
				'user_id'        => $user_id,
				'achievement_id' => $achievement_id,
				'action_type'    => $action_type,
				'admin_user_id'  => $admin_user_id,
				'notes'          => $notes,
				'network_id'     => $network_id,
			),
			array( '%d', '%d', '%s', '%d', '%s', '%d' )
		);

		// Invalidate cache
		CR_Gamification_Cache_Manager::invalidate_user_badges( $user_id, $network_id );

		/**
		 * Fires when an achievement is unlocked.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id User ID.
		 * @param int    $achievement_id Achievement ID.
		 * @param string $action_type Action type.
		 */
		do_action( 'cr_achievement_unlocked', $user_id, $achievement_id, $action_type );

		return true;
	}

	/**
	 * Revoke an achievement from a user.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $achievement_id Achievement ID.
	 * @param int    $admin_user_id Admin user ID.
	 * @param string $notes Optional notes.
	 * @return bool True on success, false on failure.
	 */
	public static function revoke_achievement( $user_id, $achievement_id, $admin_user_id, $notes = '' ) {
		global $wpdb;

		$network_id                 = get_current_network_id();
		$table_user_achievements    = $wpdb->base_prefix . 'cr_user_achievements';
		$table_achievement_history  = $wpdb->base_prefix . 'cr_achievement_history';

		// Remove unlock
		$result = $wpdb->update(
			$table_user_achievements,
			array(
				'unlocked_at'   => null,
				'current_count' => 0,
			),
			array(
				'user_id'        => $user_id,
				'achievement_id' => $achievement_id,
			),
			array( '%s', '%d' ),
			array( '%d', '%d' )
		);

		if ( $result !== false ) {
			// Add to history
			$wpdb->insert(
				$table_achievement_history,
				array(
					'user_id'        => $user_id,
					'achievement_id' => $achievement_id,
					'action_type'    => 'manual_revoke',
					'admin_user_id'  => $admin_user_id,
					'notes'          => $notes,
					'network_id'     => $network_id,
				),
				array( '%d', '%d', '%s', '%d', '%s', '%d' )
			);

			// Invalidate cache
			CR_Gamification_Cache_Manager::invalidate_user_badges( $user_id, $network_id );

			/**
			 * Fires when an achievement is revoked.
			 *
			 * @since 1.0.0
			 *
			 * @param int $user_id User ID.
			 * @param int $achievement_id Achievement ID.
			 */
			do_action( 'cr_achievement_revoked', $user_id, $achievement_id );

			return true;
		}

		return false;
	}

	/**
	 * Set featured badge for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $achievement_id Achievement ID.
	 * @return bool True on success, false on failure.
	 */
	public static function set_featured_badge( $user_id, $achievement_id ) {
		global $wpdb;

		$table_user_achievements = $wpdb->base_prefix . 'cr_user_achievements';

		// First, unfeatured all other badges for this user
		$wpdb->update(
			$table_user_achievements,
			array( 'is_featured' => 0 ),
			array( 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Then feature the selected badge
		$result = $wpdb->update(
			$table_user_achievements,
			array( 'is_featured' => 1 ),
			array(
				'user_id'        => $user_id,
				'achievement_id' => $achievement_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);

		if ( $result !== false ) {
			// Update user meta for quick access
			update_user_meta( $user_id, 'cr_featured_badge_id', $achievement_id );

			/**
			 * Fires when a featured badge is set.
			 *
			 * @since 1.0.0
			 *
			 * @param int $user_id User ID.
			 * @param int $achievement_id Achievement ID.
			 */
			do_action( 'cr_featured_badge_set', $user_id, $achievement_id );

			return true;
		}

		return false;
	}

	/**
	 * Get user's featured badge.
	 *
	 * @param int $user_id User ID.
	 * @return object|null Featured badge or null if not set.
	 */
	public static function get_featured_badge( $user_id ) {
		$achievements = self::get_user_achievements(
			$user_id,
			array(
				'featured_only' => true,
				'limit'         => 1,
			)
		);

		return ! empty( $achievements ) ? $achievements[0] : null;
	}

	/**
	 * Get user's achievement history.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Number of records to retrieve.
	 * @return array Achievement history.
	 */
	public static function get_user_history( $user_id, $limit = 20 ) {
		global $wpdb;

		$network_id                = get_current_network_id();
		$table_achievement_history = $wpdb->base_prefix . 'cr_achievement_history';
		$table_achievements        = $wpdb->base_prefix . 'cr_achievements';

		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.*, a.name, a.media_url, a.media_type, a.color_primary
				FROM {$table_achievement_history} h
				INNER JOIN {$table_achievements} a ON h.achievement_id = a.id
				WHERE h.user_id = %d AND h.network_id = %d
				ORDER BY h.created_at DESC
				LIMIT %d",
				$user_id,
				$network_id,
				$limit
			)
		);

		return $history;
	}

	/**
	 * Get user's progress towards an achievement.
	 *
	 * @param int $user_id User ID.
	 * @param int $achievement_id Achievement ID.
	 * @return array Progress data with current count, threshold, and percentage.
	 */
	public static function get_achievement_progress( $user_id, $achievement_id ) {
		global $wpdb;

		$table_user_achievements = $wpdb->base_prefix . 'cr_user_achievements';
		$table_achievements      = $wpdb->base_prefix . 'cr_achievements';

		$data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ua.current_count, ua.unlocked_at, a.threshold
				FROM {$table_user_achievements} ua
				INNER JOIN {$table_achievements} a ON ua.achievement_id = a.id
				WHERE ua.user_id = %d AND ua.achievement_id = %d",
				$user_id,
				$achievement_id
			)
		);

		if ( ! $data ) {
			// Get threshold from achievement
			$achievement = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT threshold FROM {$table_achievements} WHERE id = %d",
					$achievement_id
				)
			);

			return array(
				'current'    => 0,
				'threshold'  => $achievement ? $achievement->threshold : 1,
				'percentage' => 0,
				'unlocked'   => false,
			);
		}

		$current    = (int) $data->current_count;
		$threshold  = (int) $data->threshold;
		$percentage = $threshold > 0 ? min( 100, ( $current / $threshold ) * 100 ) : 0;

		return array(
			'current'    => $current,
			'threshold'  => $threshold,
			'percentage' => round( $percentage, 1 ),
			'unlocked'   => ! is_null( $data->unlocked_at ),
		);
	}

	/**
	 * Get user's awards (courses, events, products).
	 *
	 * @param int    $user_id User ID.
	 * @param string $award_type Optional award type filter.
	 * @param int    $limit Number of awards to retrieve.
	 * @return array User awards.
	 */
	public static function get_user_awards( $user_id, $award_type = '', $limit = 0 ) {
		global $wpdb;

		$table_user_awards = $wpdb->base_prefix . 'cr_user_awards';

		$where = array( $wpdb->prepare( 'user_id = %d', $user_id ) );

		if ( ! empty( $award_type ) ) {
			$where[] = $wpdb->prepare( 'award_type = %s', $award_type );
		}

		$where_clause = implode( ' AND ', $where );

		$limit_clause = '';
		if ( $limit > 0 ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d', $limit );
		}

		$awards = $wpdb->get_results(
			"SELECT * FROM {$table_user_awards}
			WHERE {$where_clause}
			ORDER BY awarded_at DESC
			{$limit_clause}"
		);

		return $awards;
	}

	/**
	 * Add an award to a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $award_type Award type (course|event|product|custom).
	 * @param array  $award_data Award data.
	 * @return int|false Award ID or false on failure.
	 */
	public static function add_award( $user_id, $award_type, $award_data ) {
		global $wpdb;

		$table_user_awards = $wpdb->base_prefix . 'cr_user_awards';

		$defaults = array(
			'reference_id'      => null,
			'reference_site_id' => get_current_blog_id(),
			'achievement_id'    => null,
			'title'             => '',
			'description'       => '',
			'media_url'         => '',
		);

		$award_data = wp_parse_args( $award_data, $defaults );

		$result = $wpdb->insert(
			$table_user_awards,
			array(
				'user_id'           => $user_id,
				'award_type'        => $award_type,
				'reference_id'      => $award_data['reference_id'],
				'reference_site_id' => $award_data['reference_site_id'],
				'achievement_id'    => $award_data['achievement_id'],
				'title'             => $award_data['title'],
				'description'       => $award_data['description'],
				'media_url'         => $award_data['media_url'],
			),
			array( '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( $result ) {
			$award_id = $wpdb->insert_id;

			/**
			 * Fires when an award is added.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $award_id Award ID.
			 * @param int    $user_id User ID.
			 * @param string $award_type Award type.
			 */
			do_action( 'cr_award_added', $award_id, $user_id, $award_type );

			return $award_id;
		}

		return false;
	}
}
