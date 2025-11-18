<?php
/**
 * The Events Calendar Integration.
 *
 * Handles integration with The Events Calendar and Event Tickets for tracking:
 * - Event attendance
 * - Annual event multi-year attendance
 * - Auto-generation of event attendance awards
 * - Activity feed posting for ticket purchases
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes/integrations
 * @since      1.0.0
 */

class CR_Gamification_Events_Calendar_Integration {

	/**
	 * Initialize the integration.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Check if The Events Calendar is active
		if ( ! class_exists( 'Tribe__Events__Main' ) ) {
			return;
		}

		// Event ticket purchase tracking (WooCommerce tickets)
		if ( class_exists( 'Tribe__Tickets_Plus__Commerce__WooCommerce__Main' ) ) {
			add_action( 'event_tickets_woocommerce_ticket_created', array( __CLASS__, 'on_ticket_created' ), 10, 4 );
		}

		// Alternative: RSVP tickets
		if ( class_exists( 'Tribe__Tickets__RSVP' ) ) {
			add_action( 'event_tickets_rsvp_ticket_created', array( __CLASS__, 'on_rsvp_created' ), 10, 4 );
		}
	}

	/**
	 * Handle ticket creation (WooCommerce).
	 *
	 * @param int   $attendee_id Attendee ID.
	 * @param int   $event_id Event post ID.
	 * @param int   $order_id WooCommerce order ID.
	 * @param int   $product_id Ticket product ID.
	 */
	public static function on_ticket_created( $attendee_id, $event_id, $order_id, $product_id ) {
		// Get user from order
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_customer_id();

		if ( ! $user_id ) {
			return;
		}

		self::process_event_attendance( $user_id, $event_id, $attendee_id );
	}

	/**
	 * Handle RSVP creation.
	 *
	 * @param int   $attendee_id Attendee ID.
	 * @param int   $event_id Event post ID.
	 * @param int   $order_id Order ID.
	 * @param int   $product_id Product ID.
	 */
	public static function on_rsvp_created( $attendee_id, $event_id, $order_id, $product_id ) {
		// Get user from attendee
		$user_id = get_post_meta( $attendee_id, '_tribe_rsvp_attendee_user_id', true );

		if ( ! $user_id ) {
			// Try to get from email
			$email = get_post_meta( $attendee_id, '_tribe_rsvp_email', true );

			if ( $email ) {
				$user = get_user_by( 'email', $email );

				if ( $user ) {
					$user_id = $user->ID;
				}
			}
		}

		if ( ! $user_id ) {
			return;
		}

		self::process_event_attendance( $user_id, $event_id, $attendee_id );
	}

	/**
	 * Process event attendance for gamification.
	 *
	 * @param int $user_id User ID.
	 * @param int $event_id Event ID.
	 * @param int $attendee_id Attendee ID.
	 */
	private static function process_event_attendance( $user_id, $event_id, $attendee_id ) {
		// Get event details
		$event = get_post( $event_id );

		if ( ! $event ) {
			return;
		}

		$event_title = $event->post_title;
		$event_date  = tribe_get_start_date( $event_id, false, 'Y-m-d' );

		// Check if this is a recurring event (annual event)
		$is_recurring   = function_exists( 'tribe_is_recurring_event' ) && tribe_is_recurring_event( $event_id );
		$event_series   = '';

		if ( $is_recurring ) {
			// Get parent event ID for series tracking
			$parent_id = wp_get_post_parent_id( $event_id );

			if ( $parent_id ) {
				$event_series = 'event_series_' . $parent_id;
			} else {
				$event_series = 'event_series_' . $event_id;
			}

			// Track annual attendance
			self::track_annual_attendance( $user_id, $event_series, $event_id, $event_date );
		}

		// Queue general event attendance event
		CR_Gamification_Event_Bus::queue_event(
			$user_id,
			'event_attended',
			array(
				'event_id'     => $event_id,
				'event_name'   => $event_title,
				'event_date'   => $event_date,
				'attendee_id'  => $attendee_id,
				'is_recurring' => $is_recurring,
				'event_series' => $event_series,
				'site_id'      => get_current_blog_id(),
			)
		);

		// Create individual event attendance award
		self::create_event_attendance_award( $user_id, $event_id, $event_date );

		/**
		 * Fires after event attendance is processed for gamification.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id User ID.
		 * @param int $event_id Event ID.
		 * @param int $attendee_id Attendee ID.
		 */
		do_action( 'cr_event_attendance_processed', $user_id, $event_id, $attendee_id );
	}

	/**
	 * Track annual event attendance for multi-year badges.
	 *
	 * @param int    $user_id User ID.
	 * @param string $event_series Event series identifier.
	 * @param int    $event_id Event ID.
	 * @param string $event_date Event date.
	 */
	private static function track_annual_attendance( $user_id, $event_series, $event_id, $event_date ) {
		$meta_key = 'cr_annual_event_' . $event_series;

		// Get existing attendance records
		$attendance = get_user_meta( $user_id, $meta_key, true );

		if ( ! is_array( $attendance ) ) {
			$attendance = array();
		}

		// Extract year from date
		$year = date( 'Y', strtotime( $event_date ) );

		// Add this year's attendance if not already recorded
		if ( ! isset( $attendance[ $year ] ) ) {
			$attendance[ $year ] = array(
				'event_id'   => $event_id,
				'event_date' => $event_date,
				'attended'   => true,
			);

			update_user_meta( $user_id, $meta_key, $attendance );

			// Calculate consecutive years
			$consecutive_years = self::calculate_consecutive_years( $attendance );

			// Queue annual event attendance event if consecutive years >= 2
			if ( $consecutive_years >= 2 ) {
				CR_Gamification_Event_Bus::queue_event(
					$user_id,
					'annual_event_attendance',
					array(
						'event_series'      => $event_series,
						'consecutive_years' => $consecutive_years,
						'total_years'       => count( $attendance ),
					)
				);
			}
		}
	}

	/**
	 * Calculate consecutive years of attendance.
	 *
	 * @param array $attendance Attendance records.
	 * @return int Consecutive years.
	 */
	private static function calculate_consecutive_years( $attendance ) {
		if ( empty( $attendance ) ) {
			return 0;
		}

		$years = array_keys( $attendance );
		rsort( $years ); // Sort descending

		$consecutive = 1;
		$current_year = (int) $years[0];

		for ( $i = 1; $i < count( $years ); $i++ ) {
			$year = (int) $years[ $i ];

			if ( $year === $current_year - 1 ) {
				$consecutive++;
				$current_year = $year;
			} else {
				break;
			}
		}

		return $consecutive;
	}

	/**
	 * Create event attendance award.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $event_id Event ID.
	 * @param string $event_date Event date.
	 * @return int|false Award ID or false on failure.
	 */
	private static function create_event_attendance_award( $user_id, $event_id, $event_date ) {
		$event = get_post( $event_id );

		if ( ! $event ) {
			return false;
		}

		// Get event thumbnail
		$media_url = get_the_post_thumbnail_url( $event_id, 'medium' );

		// Get event venue
		$venue = '';
		if ( function_exists( 'tribe_get_venue' ) ) {
			$venue = tribe_get_venue( $event_id );
		}

		$description = $venue ? sprintf(
			/* translators: 1: Event date, 2: Venue name */
			__( 'Attended on %1$s at %2$s', 'crossings-gamification' ),
			date_i18n( get_option( 'date_format' ), strtotime( $event_date ) ),
			$venue
		) : sprintf(
			/* translators: %s: Event date */
			__( 'Attended on %s', 'crossings-gamification' ),
			date_i18n( get_option( 'date_format' ), strtotime( $event_date ) )
		);

		// Create award
		return CR_Gamification_User_Achievements::add_award(
			$user_id,
			'event',
			array(
				'reference_id'      => $event_id,
				'reference_site_id' => get_current_blog_id(),
				'title'             => sprintf(
					/* translators: %s: Event name */
					__( 'Attended: %s', 'crossings-gamification' ),
					$event->post_title
				),
				'description'       => $description,
				'media_url'         => $media_url,
			)
		);
	}

	/**
	 * Get user's attended events count.
	 *
	 * @param int $user_id User ID.
	 * @return int Attended events count.
	 */
	public static function get_attended_events_count( $user_id ) {
		if ( ! class_exists( 'Tribe__Tickets__Main' ) ) {
			return 0;
		}

		global $wpdb;

		// Count unique events where user has tickets
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.meta_value)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ('tribe_wooticket', 'tribe_rsvp_tickets')
				AND p.post_author = %d
				AND pm.meta_key = '_tribe_wooticket_event'",
				$user_id
			)
		);

		return $count ? (int) $count : 0;
	}

	/**
	 * Get user's attended events.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Number of events to retrieve.
	 * @return array Attended events.
	 */
	public static function get_attended_events( $user_id, $limit = 10 ) {
		// This is a simplified version; actual implementation would query attendee records
		$awards = CR_Gamification_User_Achievements::get_user_awards( $user_id, 'event', $limit );

		$events = array();

		foreach ( $awards as $award ) {
			if ( $award->reference_id ) {
				$event = get_post( $award->reference_id );

				if ( $event ) {
					$events[] = array(
						'id'        => $award->reference_id,
						'title'     => $event->post_title,
						'date'      => $award->awarded_at,
						'thumbnail' => $award->media_url,
					);
				}
			}
		}

		return $events;
	}

	/**
	 * Get user's annual event attendance records.
	 *
	 * @param int    $user_id User ID.
	 * @param string $event_series Event series identifier.
	 * @return array Attendance records.
	 */
	public static function get_annual_attendance( $user_id, $event_series ) {
		$meta_key   = 'cr_annual_event_' . $event_series;
		$attendance = get_user_meta( $user_id, $meta_key, true );

		if ( ! is_array( $attendance ) ) {
			return array();
		}

		return $attendance;
	}

	/**
	 * Get user's consecutive years for an annual event.
	 *
	 * @param int    $user_id User ID.
	 * @param string $event_series Event series identifier.
	 * @return int Consecutive years.
	 */
	public static function get_consecutive_years( $user_id, $event_series ) {
		$attendance = self::get_annual_attendance( $user_id, $event_series );

		return self::calculate_consecutive_years( $attendance );
	}
}
