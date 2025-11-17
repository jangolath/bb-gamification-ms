<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 * Creates all custom database tables with proper indexing for the gamification system.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes
 * @since      1.0.0
 */

class CR_Gamification_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates all custom database tables and sets up initial configuration.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		global $wpdb;

		// Ensure we're in a multisite environment
		if ( ! is_multisite() ) {
			wp_die( esc_html__( 'This plugin requires WordPress Multisite.', 'crossings-gamification' ) );
		}

		// Get charset
		$charset_collate = $wpdb->get_charset_collate();

		// Use base_prefix for network-wide tables
		$prefix = $wpdb->base_prefix;

		// Include upgrade functions
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create achievements table
		$table_achievements = $prefix . 'cr_achievements';
		$sql_achievements   = "CREATE TABLE {$table_achievements} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			achievement_key varchar(100) NOT NULL,
			achievement_type enum('badge','award') NOT NULL DEFAULT 'badge',
			category enum('social','groups','content','learning','commerce','events','forums') NOT NULL,
			name varchar(255) NOT NULL,
			description text,
			media_type enum('svg','lottie','image') NOT NULL DEFAULT 'svg',
			media_url varchar(500),
			lottie_data longtext,
			unlock_animation_url varchar(500),
			threshold int(11) NOT NULL DEFAULT 1,
			threshold_type enum('count','boolean','custom') NOT NULL DEFAULT 'count',
			trigger_type varchar(100) NOT NULL,
			trigger_value longtext,
			color_primary varchar(7) DEFAULT '#3B82F6',
			color_secondary varchar(7) DEFAULT '#1E40AF',
			sort_order int(11) NOT NULL DEFAULT 0,
			site_scope varchar(20) DEFAULT 'network',
			vendor_id bigint(20) unsigned DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY achievement_key (achievement_key),
			KEY category (category),
			KEY achievement_type (achievement_type),
			KEY trigger_type (trigger_type),
			KEY is_active (is_active),
			KEY vendor_id (vendor_id),
			KEY sort_order (sort_order)
		) $charset_collate;";

		dbDelta( $sql_achievements );

		// Create user achievements table
		$table_user_achievements = $prefix . 'cr_user_achievements';
		$sql_user_achievements   = "CREATE TABLE {$table_user_achievements} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			achievement_id bigint(20) unsigned NOT NULL,
			current_count int(11) NOT NULL DEFAULT 0,
			unlocked_at datetime DEFAULT NULL,
			is_featured tinyint(1) NOT NULL DEFAULT 0,
			network_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_achievement (user_id, achievement_id),
			KEY user_id (user_id),
			KEY achievement_id (achievement_id),
			KEY unlocked_at (unlocked_at),
			KEY is_featured (is_featured),
			KEY network_id (network_id)
		) $charset_collate;";

		dbDelta( $sql_user_achievements );

		// Create achievement history table
		$table_achievement_history = $prefix . 'cr_achievement_history';
		$sql_achievement_history   = "CREATE TABLE {$table_achievement_history} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			achievement_id bigint(20) unsigned NOT NULL,
			action_type enum('unlocked','manual_grant','manual_revoke') NOT NULL,
			admin_user_id bigint(20) unsigned DEFAULT NULL,
			notes text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			network_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY achievement_id (achievement_id),
			KEY action_type (action_type),
			KEY created_at (created_at),
			KEY admin_user_id (admin_user_id),
			KEY network_id (network_id)
		) $charset_collate;";

		dbDelta( $sql_achievement_history );

		// Create event queue table
		$table_event_queue = $prefix . 'cr_event_queue';
		$sql_event_queue   = "CREATE TABLE {$table_event_queue} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			event_type varchar(100) NOT NULL,
			event_data longtext,
			source_site_id bigint(20) unsigned NOT NULL,
			processed tinyint(1) NOT NULL DEFAULT 0,
			processed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY event_type (event_type),
			KEY processed (processed),
			KEY source_site_id (source_site_id),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $sql_event_queue );

		// Create vendor progress table
		$table_vendor_progress = $prefix . 'cr_vendor_progress';
		$sql_vendor_progress   = "CREATE TABLE {$table_vendor_progress} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			vendor_id bigint(20) unsigned NOT NULL,
			metric_type enum('purchase_count','total_spent','orders_over_x') NOT NULL,
			current_value decimal(10,2) NOT NULL DEFAULT 0.00,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_vendor_metric (user_id, vendor_id, metric_type),
			KEY user_id (user_id),
			KEY vendor_id (vendor_id),
			KEY metric_type (metric_type)
		) $charset_collate;";

		dbDelta( $sql_vendor_progress );

		// Create content metrics table
		$table_content_metrics = $prefix . 'cr_content_metrics';
		$sql_content_metrics   = "CREATE TABLE {$table_content_metrics} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			content_type varchar(50) NOT NULL,
			content_id bigint(20) unsigned NOT NULL,
			metric_type enum('likes','comments','shares') NOT NULL,
			current_count int(11) NOT NULL DEFAULT 0,
			milestone_reached int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY user_content_metric (user_id, content_type, content_id, metric_type),
			KEY user_id (user_id),
			KEY content_type (content_type),
			KEY content_id (content_id),
			KEY metric_type (metric_type)
		) $charset_collate;";

		dbDelta( $sql_content_metrics );

		// Create user awards table
		$table_user_awards = $prefix . 'cr_user_awards';
		$sql_user_awards   = "CREATE TABLE {$table_user_awards} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			award_type enum('course','event','product','custom') NOT NULL,
			reference_id bigint(20) unsigned DEFAULT NULL,
			reference_site_id bigint(20) unsigned DEFAULT NULL,
			achievement_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL,
			description text,
			media_url varchar(500),
			awarded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY award_type (award_type),
			KEY reference_id (reference_id),
			KEY achievement_id (achievement_id),
			KEY awarded_at (awarded_at)
		) $charset_collate;";

		dbDelta( $sql_user_awards );

		// Set database version
		update_site_option( 'cr_gamification_db_version', CROSSINGS_GAMIFICATION_VERSION );

		// Set default options
		self::set_default_options();

		// Create default badges
		self::create_default_badges();
	}

	/**
	 * Set default plugin options.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		$default_options = array(
			'activity_feed_enabled'       => 1,
			'profile_display_enabled'     => 1,
			'featured_badge_enabled'      => 1,
			'unlock_animation_enabled'    => 1,
			'redis_enabled'               => 1,
			'cache_ttl'                   => 3600,
			'event_queue_processing'      => 'cron',
			'batch_size'                  => 50,
			'vendor_badges_enabled'       => 1,
			'annual_event_tracking'       => 1,
		);

		foreach ( $default_options as $key => $value ) {
			if ( get_site_option( 'cr_gamification_' . $key ) === false ) {
				update_site_option( 'cr_gamification_' . $key, $value );
			}
		}
	}

	/**
	 * Create default achievement badges.
	 *
	 * Creates initial set of badges for social, learning, commerce, and events.
	 *
	 * @since 1.0.0
	 */
	private static function create_default_badges() {
		global $wpdb;
		$table = $wpdb->base_prefix . 'cr_achievements';

		// Social - Friends badges
		$friends_tiers = array( 5, 10, 25, 50, 100, 250, 500, 750, 1000 );
		foreach ( $friends_tiers as $index => $tier ) {
			$wpdb->insert(
				$table,
				array(
					'achievement_key'  => 'friends_' . $tier,
					'achievement_type' => 'badge',
					'category'         => 'social',
					'name'             => sprintf( __( '%d Friends', 'crossings-gamification' ), $tier ),
					'description'      => sprintf( __( 'Add %d friends to your network', 'crossings-gamification' ), $tier ),
					'media_type'       => 'svg',
					'threshold'        => $tier,
					'threshold_type'   => 'count',
					'trigger_type'     => 'friends_added',
					'color_primary'    => '#3B82F6',
					'color_secondary'  => '#1E40AF',
					'sort_order'       => $index,
					'is_active'        => 1,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		// Social - Followers badges
		$followers_tiers = array( 5, 10, 25, 50, 100, 250, 500, 750, 1000 );
		foreach ( $followers_tiers as $index => $tier ) {
			$wpdb->insert(
				$table,
				array(
					'achievement_key'  => 'followers_' . $tier,
					'achievement_type' => 'badge',
					'category'         => 'social',
					'name'             => sprintf( __( '%d Followers', 'crossings-gamification' ), $tier ),
					'description'      => sprintf( __( 'Gain %d followers in the community', 'crossings-gamification' ), $tier ),
					'media_type'       => 'svg',
					'threshold'        => $tier,
					'threshold_type'   => 'count',
					'trigger_type'     => 'followers_gained',
					'color_primary'    => '#8B5CF6',
					'color_secondary'  => '#6D28D9',
					'sort_order'       => 100 + $index,
					'is_active'        => 1,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		// Groups - Joined badges
		$groups_joined_tiers = array( 1, 5, 10, 25, 50 );
		foreach ( $groups_joined_tiers as $index => $tier ) {
			$wpdb->insert(
				$table,
				array(
					'achievement_key'  => 'groups_joined_' . $tier,
					'achievement_type' => 'badge',
					'category'         => 'groups',
					'name'             => sprintf( __( '%d Groups Joined', 'crossings-gamification' ), $tier ),
					'description'      => sprintf( __( 'Join %d groups in the community', 'crossings-gamification' ), $tier ),
					'media_type'       => 'svg',
					'threshold'        => $tier,
					'threshold_type'   => 'count',
					'trigger_type'     => 'groups_joined',
					'color_primary'    => '#10B981',
					'color_secondary'  => '#059669',
					'sort_order'       => 200 + $index,
					'is_active'        => 1,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		// Groups - Created badges
		$groups_created_tiers = array( 1, 5, 10 );
		foreach ( $groups_created_tiers as $index => $tier ) {
			$wpdb->insert(
				$table,
				array(
					'achievement_key'  => 'groups_created_' . $tier,
					'achievement_type' => 'badge',
					'category'         => 'groups',
					'name'             => sprintf( __( '%d Groups Created', 'crossings-gamification' ), $tier ),
					'description'      => sprintf( __( 'Create and manage %d groups', 'crossings-gamification' ), $tier ),
					'media_type'       => 'svg',
					'threshold'        => $tier,
					'threshold_type'   => 'count',
					'trigger_type'     => 'groups_created',
					'color_primary'    => '#F59E0B',
					'color_secondary'  => '#D97706',
					'sort_order'       => 300 + $index,
					'is_active'        => 1,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		// Content - Activity posts
		$activity_tiers = array( 1, 5, 10, 25, 50, 100 );
		foreach ( $activity_tiers as $index => $tier ) {
			$wpdb->insert(
				$table,
				array(
					'achievement_key'  => 'activity_posts_' . $tier,
					'achievement_type' => 'badge',
					'category'         => 'content',
					'name'             => sprintf( __( '%d Activity Posts', 'crossings-gamification' ), $tier ),
					'description'      => sprintf( __( 'Create %d activity posts', 'crossings-gamification' ), $tier ),
					'media_type'       => 'svg',
					'threshold'        => $tier,
					'threshold_type'   => 'count',
					'trigger_type'     => 'activity_posted',
					'color_primary'    => '#EF4444',
					'color_secondary'  => '#DC2626',
					'sort_order'       => 400 + $index,
					'is_active'        => 1,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		// Learning - Courses completed
		$courses_tiers = array( 1, 5, 10, 25 );
		foreach ( $courses_tiers as $index => $tier ) {
			$wpdb->insert(
				$table,
				array(
					'achievement_key'  => 'courses_completed_' . $tier,
					'achievement_type' => 'badge',
					'category'         => 'learning',
					'name'             => sprintf( __( '%d Courses Completed', 'crossings-gamification' ), $tier ),
					'description'      => sprintf( __( 'Complete %d courses', 'crossings-gamification' ), $tier ),
					'media_type'       => 'svg',
					'threshold'        => $tier,
					'threshold_type'   => 'count',
					'trigger_type'     => 'course_completed',
					'color_primary'    => '#06B6D4',
					'color_secondary'  => '#0891B2',
					'sort_order'       => 500 + $index,
					'is_active'        => 1,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		// Commerce - First purchase
		$wpdb->insert(
			$table,
			array(
				'achievement_key'  => 'first_purchase',
				'achievement_type' => 'badge',
				'category'         => 'commerce',
				'name'             => __( 'First Purchase', 'crossings-gamification' ),
				'description'      => __( 'Make your first purchase', 'crossings-gamification' ),
				'media_type'       => 'svg',
				'threshold'        => 1,
				'threshold_type'   => 'boolean',
				'trigger_type'     => 'product_purchased',
				'color_primary'    => '#EC4899',
				'color_secondary'  => '#DB2777',
				'sort_order'       => 600,
				'is_active'        => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		// Events - Attendance badges
		$events_tiers = array( 1, 5, 10, 25 );
		foreach ( $events_tiers as $index => $tier ) {
			$wpdb->insert(
				$table,
				array(
					'achievement_key'  => 'events_attended_' . $tier,
					'achievement_type' => 'badge',
					'category'         => 'events',
					'name'             => sprintf( __( '%d Events Attended', 'crossings-gamification' ), $tier ),
					'description'      => sprintf( __( 'Attend %d events', 'crossings-gamification' ), $tier ),
					'media_type'       => 'svg',
					'threshold'        => $tier,
					'threshold_type'   => 'count',
					'trigger_type'     => 'event_attended',
					'color_primary'    => '#14B8A6',
					'color_secondary'  => '#0D9488',
					'sort_order'       => 700 + $index,
					'is_active'        => 1,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		// Events - Annual attendance badges
		$annual_tiers = array( 2, 3, 5, 10 );
		foreach ( $annual_tiers as $index => $tier ) {
			$wpdb->insert(
				$table,
				array(
					'achievement_key'  => 'annual_event_' . $tier . '_years',
					'achievement_type' => 'badge',
					'category'         => 'events',
					'name'             => sprintf( __( '%d Year Attendee', 'crossings-gamification' ), $tier ),
					'description'      => sprintf( __( 'Attend the same annual event for %d consecutive years', 'crossings-gamification' ), $tier ),
					'media_type'       => 'svg',
					'threshold'        => $tier,
					'threshold_type'   => 'count',
					'trigger_type'     => 'annual_event_attendance',
					'color_primary'    => '#F97316',
					'color_secondary'  => '#EA580C',
					'sort_order'       => 800 + $index,
					'is_active'        => 1,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}
	}
}
