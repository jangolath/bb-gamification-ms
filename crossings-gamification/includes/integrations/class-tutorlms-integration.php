<?php
/**
 * TutorLMS Integration.
 *
 * Handles integration with TutorLMS for tracking learning achievements:
 * - Course completions
 * - Auto-generation of course completion awards
 * - Course completion badges
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes/integrations
 * @since      1.0.0
 */

class CR_Gamification_TutorLMS_Integration {

	/**
	 * Initialize the integration.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Check if TutorLMS is active
		if ( ! function_exists( 'tutor' ) ) {
			return;
		}

		// Course completion tracking
		add_action( 'tutor_course_complete_after', array( __CLASS__, 'on_course_completed' ), 10, 1 );
	}

	/**
	 * Handle course completion.
	 *
	 * Triggers badge progress and creates individual course completion award.
	 *
	 * @param int $course_id Course ID.
	 */
	public static function on_course_completed( $course_id ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Get course details
		$course = get_post( $course_id );

		if ( ! $course ) {
			return;
		}

		// Queue event for course completion badge progression
		CR_Gamification_Event_Bus::queue_event(
			$user_id,
			'course_completed',
			array(
				'course_id'   => $course_id,
				'course_name' => $course->post_title,
				'site_id'     => get_current_blog_id(),
			)
		);

		// Create individual course completion award
		self::create_course_completion_award( $user_id, $course_id );

		/**
		 * Fires after course completion is processed for gamification.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id User ID.
		 * @param int $course_id Course ID.
		 */
		do_action( 'cr_tutor_course_completed', $user_id, $course_id );
	}

	/**
	 * Create individual course completion award.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return int|false Award ID or false on failure.
	 */
	public static function create_course_completion_award( $user_id, $course_id ) {
		$course = get_post( $course_id );

		if ( ! $course ) {
			return false;
		}

		// Get course thumbnail
		$media_url = get_the_post_thumbnail_url( $course_id, 'medium' );

		// Get course description
		$description = wp_trim_words( $course->post_excerpt ? $course->post_excerpt : $course->post_content, 30 );

		// Create award
		return CR_Gamification_User_Achievements::add_award(
			$user_id,
			'course',
			array(
				'reference_id'      => $course_id,
				'reference_site_id' => get_current_blog_id(),
				'title'             => sprintf(
					/* translators: %s: Course name */
					__( 'Completed: %s', 'crossings-gamification' ),
					$course->post_title
				),
				'description'       => $description,
				'media_url'         => $media_url,
			)
		);
	}

	/**
	 * Get user's completed courses count.
	 *
	 * @param int $user_id User ID.
	 * @return int Completed courses count.
	 */
	public static function get_completed_courses_count( $user_id ) {
		if ( ! function_exists( 'tutor_utils' ) ) {
			return 0;
		}

		global $wpdb;

		$completed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id)
				FROM {$wpdb->comments}
				WHERE comment_type = 'tutor_course_complete'
				AND user_id = %d
				AND comment_approved = 'approved'",
				$user_id
			)
		);

		return $completed ? (int) $completed : 0;
	}

	/**
	 * Get user's completed courses.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Number of courses to retrieve.
	 * @return array Completed courses.
	 */
	public static function get_completed_courses( $user_id, $limit = 10 ) {
		if ( ! function_exists( 'tutor_utils' ) ) {
			return array();
		}

		global $wpdb;

		$course_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id
				FROM {$wpdb->comments}
				WHERE comment_type = 'tutor_course_complete'
				AND user_id = %d
				AND comment_approved = 'approved'
				ORDER BY comment_ID DESC
				LIMIT %d",
				$user_id,
				$limit
			)
		);

		if ( empty( $course_ids ) ) {
			return array();
		}

		$courses = array();

		foreach ( $course_ids as $course_id ) {
			$course = get_post( $course_id );

			if ( $course ) {
				$courses[] = array(
					'id'        => $course_id,
					'title'     => $course->post_title,
					'thumbnail' => get_the_post_thumbnail_url( $course_id, 'thumbnail' ),
					'url'       => get_permalink( $course_id ),
				);
			}
		}

		return $courses;
	}

	/**
	 * Check if user has completed a specific course.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return bool True if completed, false otherwise.
	 */
	public static function has_completed_course( $user_id, $course_id ) {
		if ( ! function_exists( 'tutor_utils' ) ) {
			return false;
		}

		return (bool) tutor_utils()->is_completed_course( $course_id, $user_id );
	}

	/**
	 * Get course completion date.
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return string|null Completion date or null.
	 */
	public static function get_completion_date( $user_id, $course_id ) {
		if ( ! function_exists( 'tutor_utils' ) ) {
			return null;
		}

		global $wpdb;

		$completion_date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT comment_date
				FROM {$wpdb->comments}
				WHERE comment_type = 'tutor_course_complete'
				AND user_id = %d
				AND post_id = %d
				AND comment_approved = 'approved'
				ORDER BY comment_ID DESC
				LIMIT 1",
				$user_id,
				$course_id
			)
		);

		return $completion_date;
	}
}
