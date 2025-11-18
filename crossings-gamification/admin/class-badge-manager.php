<?php
/**
 * Badge Manager.
 *
 * Handles CRUD operations for achievements/badges from the admin interface.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/admin
 * @since      1.0.0
 */

class CR_Gamification_Badge_Manager {

	/**
	 * Get all achievements.
	 *
	 * @param array $args {
	 *     Optional query arguments.
	 *
	 *     @type string $category Achievement category filter.
	 *     @type string $type     Achievement type filter (badge|award).
	 *     @type bool   $active_only Only return active achievements.
	 *     @type string $order_by Order by field.
	 *     @type string $order    Order direction (ASC|DESC).
	 * }
	 * @return array Achievements.
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'category'    => '',
			'type'        => '',
			'active_only' => false,
			'order_by'    => 'sort_order',
			'order'       => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$table = $wpdb->base_prefix . 'cr_achievements';

		$where = array( '1=1' );

		if ( ! empty( $args['category'] ) ) {
			$where[] = $wpdb->prepare( 'category = %s', $args['category'] );
		}

		if ( ! empty( $args['type'] ) ) {
			$where[] = $wpdb->prepare( 'achievement_type = %s', $args['type'] );
		}

		if ( $args['active_only'] ) {
			$where[] = 'is_active = 1';
		}

		$where_clause = implode( ' AND ', $where );
		$order_by     = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );

		if ( ! $order_by ) {
			$order_by = 'sort_order ASC';
		}

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by}"
		);
	}

	/**
	 * Get a single achievement by ID.
	 *
	 * @param int $achievement_id Achievement ID.
	 * @return object|null Achievement object or null if not found.
	 */
	public static function get( $achievement_id ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_achievements';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$achievement_id
			)
		);
	}

	/**
	 * Create a new achievement.
	 *
	 * @param array $data Achievement data.
	 * @return int|false Achievement ID or false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_achievements';

		$defaults = array(
			'achievement_key'      => '',
			'achievement_type'     => 'badge',
			'category'             => 'social',
			'name'                 => '',
			'description'          => '',
			'media_type'           => 'svg',
			'media_url'            => '',
			'lottie_data'          => '',
			'unlock_animation_url' => '',
			'threshold'            => 1,
			'threshold_type'       => 'count',
			'trigger_type'         => '',
			'trigger_value'        => '',
			'color_primary'        => '#3B82F6',
			'color_secondary'      => '#1E40AF',
			'sort_order'           => 0,
			'site_scope'           => 'network',
			'vendor_id'            => null,
			'is_active'            => 1,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields
		if ( empty( $data['achievement_key'] ) || empty( $data['name'] ) || empty( $data['trigger_type'] ) ) {
			return false;
		}

		// Ensure trigger_value is JSON
		if ( is_array( $data['trigger_value'] ) ) {
			$data['trigger_value'] = wp_json_encode( $data['trigger_value'] );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'achievement_key'      => $data['achievement_key'],
				'achievement_type'     => $data['achievement_type'],
				'category'             => $data['category'],
				'name'                 => $data['name'],
				'description'          => $data['description'],
				'media_type'           => $data['media_type'],
				'media_url'            => $data['media_url'],
				'lottie_data'          => $data['lottie_data'],
				'unlock_animation_url' => $data['unlock_animation_url'],
				'threshold'            => $data['threshold'],
				'threshold_type'       => $data['threshold_type'],
				'trigger_type'         => $data['trigger_type'],
				'trigger_value'        => $data['trigger_value'],
				'color_primary'        => $data['color_primary'],
				'color_secondary'      => $data['color_secondary'],
				'sort_order'           => $data['sort_order'],
				'site_scope'           => $data['site_scope'],
				'vendor_id'            => $data['vendor_id'],
				'is_active'            => $data['is_active'],
			),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d',
			)
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing achievement.
	 *
	 * @param int   $achievement_id Achievement ID.
	 * @param array $data Achievement data.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $achievement_id, $data ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_achievements';

		// Ensure trigger_value is JSON
		if ( isset( $data['trigger_value'] ) && is_array( $data['trigger_value'] ) ) {
			$data['trigger_value'] = wp_json_encode( $data['trigger_value'] );
		}

		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $achievement_id ),
			null,
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Delete an achievement.
	 *
	 * @param int $achievement_id Achievement ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $achievement_id ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_achievements';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $achievement_id ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Toggle achievement active status.
	 *
	 * @param int $achievement_id Achievement ID.
	 * @return bool True on success, false on failure.
	 */
	public static function toggle_active( $achievement_id ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_achievements';

		$current = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT is_active FROM {$table} WHERE id = %d",
				$achievement_id
			)
		);

		if ( is_null( $current ) ) {
			return false;
		}

		$new_status = $current ? 0 : 1;

		return self::update(
			$achievement_id,
			array( 'is_active' => $new_status )
		);
	}

	/**
	 * Get achievement statistics.
	 *
	 * @return array Statistics.
	 */
	public static function get_statistics() {
		global $wpdb;

		$table_achievements = $wpdb->base_prefix . 'cr_achievements';
		$table_user_achievements = $wpdb->base_prefix . 'cr_user_achievements';

		$stats = array(
			'total_badges'        => 0,
			'active_badges'       => 0,
			'total_unlocks'       => 0,
			'unlocks_today'       => 0,
			'unlocks_this_week'   => 0,
			'unlocks_this_month'  => 0,
		);

		// Total badges
		$stats['total_badges'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_achievements} WHERE achievement_type = 'badge'"
		);

		// Active badges
		$stats['active_badges'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_achievements} WHERE achievement_type = 'badge' AND is_active = 1"
		);

		// Total unlocks
		$stats['total_unlocks'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_user_achievements} WHERE unlocked_at IS NOT NULL"
		);

		// Unlocks today
		$stats['unlocks_today'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_user_achievements}
			WHERE unlocked_at IS NOT NULL
			AND DATE(unlocked_at) = CURDATE()"
		);

		// Unlocks this week
		$stats['unlocks_this_week'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_user_achievements}
			WHERE unlocked_at IS NOT NULL
			AND YEARWEEK(unlocked_at) = YEARWEEK(NOW())"
		);

		// Unlocks this month
		$stats['unlocks_this_month'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_user_achievements}
			WHERE unlocked_at IS NOT NULL
			AND YEAR(unlocked_at) = YEAR(NOW())
			AND MONTH(unlocked_at) = MONTH(NOW())"
		);

		return $stats;
	}
}
