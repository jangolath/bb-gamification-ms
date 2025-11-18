<?php
/**
 * BuddyBoss/BuddyPress Integration.
 *
 * Handles integration with BuddyBoss for tracking social interactions:
 * - Friends
 * - Followers
 * - Groups
 * - Activity posts
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes/integrations
 * @since      1.0.0
 */

class CR_Gamification_BuddyBoss_Integration {

	/**
	 * Initialize the integration.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Check if BuddyPress/BuddyBoss is active
		if ( ! function_exists( 'bp_is_active' ) ) {
			return;
		}

		// Friends tracking
		if ( bp_is_active( 'friends' ) ) {
			add_action( 'friends_friendship_accepted', array( __CLASS__, 'on_friendship_accepted' ), 10, 4 );
		}

		// Follow tracking (if BP Follow or similar is active)
		if ( function_exists( 'bp_follow_start_following' ) ) {
			add_action( 'bp_start_following', array( __CLASS__, 'on_start_following' ), 10, 2 );
		}

		// Groups tracking
		if ( bp_is_active( 'groups' ) ) {
			add_action( 'groups_join_group', array( __CLASS__, 'on_group_joined' ), 10, 2 );
			add_action( 'groups_create_group', array( __CLASS__, 'on_group_created' ), 10, 3 );
		}

		// Activity tracking
		if ( bp_is_active( 'activity' ) ) {
			add_action( 'bp_activity_posted_update', array( __CLASS__, 'on_activity_posted' ), 10, 3 );
		}
	}

	/**
	 * Handle friendship accepted.
	 *
	 * @param int    $id Friendship ID.
	 * @param int    $initiator_user_id Initiator user ID.
	 * @param int    $friend_user_id Friend user ID.
	 * @param object $friendship Friendship object.
	 */
	public static function on_friendship_accepted( $id, $initiator_user_id, $friend_user_id, $friendship ) {
		// Queue event for initiator
		CR_Gamification_Event_Bus::queue_event(
			$initiator_user_id,
			'friends_added',
			array(
				'friend_id'     => $friend_user_id,
				'friendship_id' => $id,
			)
		);

		// Queue event for friend (they also gained a friend)
		CR_Gamification_Event_Bus::queue_event(
			$friend_user_id,
			'friends_added',
			array(
				'friend_id'     => $initiator_user_id,
				'friendship_id' => $id,
			)
		);
	}

	/**
	 * Handle follower gained.
	 *
	 * @param int $leader_id User being followed.
	 * @param int $follower_id User who is following.
	 */
	public static function on_start_following( $leader_id, $follower_id ) {
		// Queue event for the user being followed (they gained a follower)
		CR_Gamification_Event_Bus::queue_event(
			$leader_id,
			'followers_gained',
			array(
				'follower_id' => $follower_id,
			)
		);
	}

	/**
	 * Handle group joined.
	 *
	 * @param int $group_id Group ID.
	 * @param int $user_id User ID.
	 */
	public static function on_group_joined( $group_id, $user_id ) {
		// Get group details
		$group = groups_get_group( $group_id );

		CR_Gamification_Event_Bus::queue_event(
			$user_id,
			'groups_joined',
			array(
				'group_id'   => $group_id,
				'group_name' => $group->name,
			)
		);
	}

	/**
	 * Handle group created.
	 *
	 * @param int    $group_id Group ID.
	 * @param object $member Member object.
	 * @param object $group Group object.
	 */
	public static function on_group_created( $group_id, $member, $group ) {
		// Get creator user ID
		$user_id = $group->creator_id;

		CR_Gamification_Event_Bus::queue_event(
			$user_id,
			'groups_created',
			array(
				'group_id'   => $group_id,
				'group_name' => $group->name,
			)
		);
	}

	/**
	 * Handle activity post created.
	 *
	 * @param string $content Activity content.
	 * @param int    $user_id User ID.
	 * @param int    $activity_id Activity ID.
	 */
	public static function on_activity_posted( $content, $user_id, $activity_id ) {
		CR_Gamification_Event_Bus::queue_event(
			$user_id,
			'activity_posted',
			array(
				'activity_id' => $activity_id,
				'content'     => wp_trim_words( $content, 20 ),
			)
		);
	}

	/**
	 * Get user's friend count.
	 *
	 * @param int $user_id User ID.
	 * @return int Friend count.
	 */
	public static function get_friend_count( $user_id ) {
		if ( ! bp_is_active( 'friends' ) ) {
			return 0;
		}

		return (int) friends_get_total_friend_count( $user_id );
	}

	/**
	 * Get user's follower count.
	 *
	 * @param int $user_id User ID.
	 * @return int Follower count.
	 */
	public static function get_follower_count( $user_id ) {
		if ( ! function_exists( 'bp_follow_total_follow_counts' ) ) {
			return 0;
		}

		$counts = bp_follow_total_follow_counts(
			array(
				'user_id' => $user_id,
			)
		);

		return isset( $counts['followers'] ) ? (int) $counts['followers'] : 0;
	}

	/**
	 * Get user's group count.
	 *
	 * @param int $user_id User ID.
	 * @return int Group count.
	 */
	public static function get_group_count( $user_id ) {
		if ( ! bp_is_active( 'groups' ) ) {
			return 0;
		}

		return (int) bp_get_user_meta( $user_id, 'total_group_count', true );
	}

	/**
	 * Get user's created groups count.
	 *
	 * @param int $user_id User ID.
	 * @return int Created groups count.
	 */
	public static function get_created_groups_count( $user_id ) {
		if ( ! bp_is_active( 'groups' ) ) {
			return 0;
		}

		global $wpdb;
		$bp = buddypress();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bp->groups->table_name}
				WHERE creator_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Get user's activity post count.
	 *
	 * @param int $user_id User ID.
	 * @return int Activity post count.
	 */
	public static function get_activity_count( $user_id ) {
		if ( ! bp_is_active( 'activity' ) ) {
			return 0;
		}

		global $wpdb;
		$bp = buddypress();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bp->activity->table_name}
				WHERE user_id = %d AND type = 'activity_update'",
				$user_id
			)
		);
	}
}
