<?php
/**
 * Activity Feed Integration.
 *
 * Handles posting badge unlocks and other gamification events to BuddyBoss activity feed.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/public
 * @since      1.0.0
 */

class CR_Gamification_Activity_Feed {

	/**
	 * Initialize the activity feed integration.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Check if BuddyPress/BuddyBoss is active
		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'activity' ) ) {
			return;
		}

		// Register custom activity types
		add_action( 'bp_register_activity_actions', array( __CLASS__, 'register_activity_types' ) );

		// Hook into achievement unlocks
		add_action( 'cr_after_achievement_unlocked', array( __CLASS__, 'post_badge_unlock' ), 10, 2 );
	}

	/**
	 * Register custom activity types.
	 *
	 * @since 1.0.0
	 */
	public static function register_activity_types() {
		bp_activity_set_action(
			'cr_gamification',
			'cr_badge_unlocked',
			__( 'Unlocked a badge', 'crossings-gamification' ),
			array( __CLASS__, 'format_activity' ),
			__( 'Badges', 'crossings-gamification' )
		);

		bp_activity_set_action(
			'cr_gamification',
			'cr_course_completed',
			__( 'Completed a course', 'crossings-gamification' ),
			array( __CLASS__, 'format_activity' ),
			__( 'Learning', 'crossings-gamification' )
		);

		bp_activity_set_action(
			'cr_gamification',
			'cr_event_attended',
			__( 'Attended an event', 'crossings-gamification' ),
			array( __CLASS__, 'format_activity' ),
			__( 'Events', 'crossings-gamification' )
		);
	}

	/**
	 * Post badge unlock to activity feed.
	 *
	 * @param int    $user_id User ID.
	 * @param object $achievement Achievement object.
	 */
	public static function post_badge_unlock( $user_id, $achievement ) {
		if ( ! get_site_option( 'cr_gamification_activity_feed_enabled', 1 ) ) {
			return;
		}

		$action = sprintf(
			/* translators: 1: User link, 2: Badge name */
			__( '%1$s unlocked the badge %2$s', 'crossings-gamification' ),
			bp_core_get_userlink( $user_id ),
			'<strong>' . esc_html( $achievement->name ) . '</strong>'
		);

		$content = '';

		if ( ! empty( $achievement->description ) ) {
			$content = '<p>' . esc_html( $achievement->description ) . '</p>';
		}

		// Add badge image if available
		if ( ! empty( $achievement->media_url ) ) {
			$content .= sprintf(
				'<div class="cr-badge-unlock-image"><img src="%s" alt="%s" style="max-width: 150px; height: auto;"></div>',
				esc_url( $achievement->media_url ),
				esc_attr( $achievement->name )
			);
		}

		bp_activity_add(
			array(
				'user_id'      => $user_id,
				'action'       => $action,
				'content'      => $content,
				'component'    => 'cr_gamification',
				'type'         => 'cr_badge_unlocked',
				'item_id'      => $achievement->id,
			)
		);
	}

	/**
	 * Post course completion to activity feed.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $course_id Course ID.
	 * @param string $course_title Course title.
	 */
	public static function post_course_completion( $user_id, $course_id, $course_title ) {
		if ( ! get_site_option( 'cr_gamification_activity_feed_enabled', 1 ) ) {
			return;
		}

		$action = sprintf(
			/* translators: 1: User link, 2: Course title */
			__( '%1$s completed the course %2$s', 'crossings-gamification' ),
			bp_core_get_userlink( $user_id ),
			'<strong>' . esc_html( $course_title ) . '</strong>'
		);

		bp_activity_add(
			array(
				'user_id'   => $user_id,
				'action'    => $action,
				'component' => 'cr_gamification',
				'type'      => 'cr_course_completed',
				'item_id'   => $course_id,
			)
		);
	}

	/**
	 * Post event attendance to activity feed.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $event_id Event ID.
	 * @param string $event_title Event title.
	 */
	public static function post_event_attendance( $user_id, $event_id, $event_title ) {
		if ( ! get_site_option( 'cr_gamification_activity_feed_enabled', 1 ) ) {
			return;
		}

		$action = sprintf(
			/* translators: 1: User link, 2: Event title */
			__( '%1$s is attending %2$s', 'crossings-gamification' ),
			bp_core_get_userlink( $user_id ),
			'<strong>' . esc_html( $event_title ) . '</strong>'
		);

		bp_activity_add(
			array(
				'user_id'   => $user_id,
				'action'    => $action,
				'component' => 'cr_gamification',
				'type'      => 'cr_event_attended',
				'item_id'   => $event_id,
			)
		);
	}

	/**
	 * Format activity action for display.
	 *
	 * @param string $action Action string.
	 * @param object $activity Activity object.
	 * @return string Formatted action.
	 */
	public static function format_activity( $action, $activity ) {
		return $action;
	}
}
