<?php
/**
 * Event Bus for cross-site event queue processing.
 *
 * Handles queuing and processing of events from subsites, allowing achievements
 * to be tracked across the entire multisite network.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes
 * @since      1.0.0
 */

class CR_Gamification_Event_Bus {

	/**
	 * Initialize the event bus.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Hook into cron for queue processing
		add_action( 'cr_process_event_queue', array( __CLASS__, 'process_queue' ) );

		// Allow real-time processing (optional)
		if ( get_site_option( 'cr_gamification_event_queue_processing', 'cron' ) === 'realtime' ) {
			add_action( 'cr_event_queued', array( __CLASS__, 'process_single_event' ), 10, 1 );
		}
	}

	/**
	 * Queue an event for processing.
	 *
	 * @param int    $user_id User ID.
	 * @param string $event_type Event type key.
	 * @param array  $event_data Additional event data.
	 * @param int    $source_site_id Source site ID (defaults to current site).
	 * @return int|false Event queue ID or false on failure.
	 */
	public static function queue_event( $user_id, $event_type, $event_data = array(), $source_site_id = null ) {
		global $wpdb;

		if ( is_null( $source_site_id ) ) {
			$source_site_id = get_current_blog_id();
		}

		// Validate event type
		if ( ! CR_Gamification_Event_Registry::is_registered( $event_type ) ) {
			error_log( "CR Gamification: Unknown event type '{$event_type}' queued" );
			return false;
		}

		$table = $wpdb->base_prefix . 'cr_event_queue';

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id,
				'event_type'     => $event_type,
				'event_data'     => wp_json_encode( $event_data ),
				'source_site_id' => $source_site_id,
				'processed'      => 0,
			),
			array( '%d', '%s', '%s', '%d', '%d' )
		);

		if ( $result ) {
			$event_id = $wpdb->insert_id;

			/**
			 * Fires when an event is queued.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $event_id Event queue ID.
			 * @param int   $user_id User ID.
			 * @param string $event_type Event type.
			 */
			do_action( 'cr_event_queued', $event_id, $user_id, $event_type );

			return $event_id;
		}

		return false;
	}

	/**
	 * Process the event queue.
	 *
	 * Processes a batch of unprocessed events.
	 *
	 * @since 1.0.0
	 * @return int Number of events processed.
	 */
	public static function process_queue() {
		global $wpdb;

		$batch_size = (int) get_site_option( 'cr_gamification_batch_size', 50 );
		$table      = $wpdb->base_prefix . 'cr_event_queue';

		// Get unprocessed events
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE processed = 0 ORDER BY created_at ASC LIMIT %d",
				$batch_size
			)
		);

		if ( empty( $events ) ) {
			return 0;
		}

		$processed_count = 0;

		foreach ( $events as $event ) {
			$success = self::process_single_event( $event->id );

			if ( $success ) {
				$processed_count++;
			}
		}

		return $processed_count;
	}

	/**
	 * Process a single event.
	 *
	 * @param int $event_id Event queue ID.
	 * @return bool True on success, false on failure.
	 */
	public static function process_single_event( $event_id ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_event_queue';

		// Get event
		$event = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$event_id
			)
		);

		if ( ! $event || $event->processed ) {
			return false;
		}

		// Decode event data
		$event_data = json_decode( $event->event_data, true );
		if ( is_null( $event_data ) ) {
			$event_data = array();
		}

		// Process the event through the Achievement Engine
		$result = CR_Gamification_Achievement_Engine::process_event(
			$event->user_id,
			$event->event_type,
			$event_data,
			$event->source_site_id
		);

		// Mark as processed
		$wpdb->update(
			$table,
			array(
				'processed'    => 1,
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $event_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after an event is processed.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $event_id Event queue ID.
		 * @param object $event Event object.
		 * @param bool   $result Processing result.
		 */
		do_action( 'cr_event_processed', $event_id, $event, $result );

		return true;
	}

	/**
	 * Get queue statistics.
	 *
	 * @return array Queue statistics.
	 */
	public static function get_queue_stats() {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_event_queue';

		$stats = array(
			'total'      => 0,
			'pending'    => 0,
			'processed'  => 0,
			'last_run'   => null,
			'avg_time'   => 0,
		);

		// Total events
		$stats['total'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// Pending events
		$stats['pending'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE processed = 0" );

		// Processed events
		$stats['processed'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE processed = 1" );

		// Last processing time
		$last_run = $wpdb->get_var( "SELECT MAX(processed_at) FROM {$table} WHERE processed = 1" );
		if ( $last_run ) {
			$stats['last_run'] = $last_run;
		}

		// Average processing time (calculated from timestamp differences)
		$avg_time = $wpdb->get_var(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, processed_at))
			FROM {$table}
			WHERE processed = 1 AND processed_at IS NOT NULL"
		);
		if ( $avg_time ) {
			$stats['avg_time'] = round( $avg_time, 2 );
		}

		return $stats;
	}

	/**
	 * Clean up old processed events.
	 *
	 * Removes events older than the specified number of days.
	 *
	 * @param int $days Number of days to keep (default: 30).
	 * @return int Number of events deleted.
	 */
	public static function cleanup_old_events( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_event_queue';

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE processed = 1
				AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Get recent events for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Number of events to retrieve.
	 * @return array Recent events.
	 */
	public static function get_user_events( $user_id, $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_event_queue';

		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT %d",
				$user_id,
				$limit
			)
		);

		if ( ! empty( $events ) ) {
			foreach ( $events as &$event ) {
				$event->event_data = json_decode( $event->event_data, true );
			}
		}

		return $events;
	}

	/**
	 * Trigger an event immediately without queuing.
	 *
	 * Use this for real-time processing on the main site.
	 *
	 * @param int    $user_id User ID.
	 * @param string $event_type Event type key.
	 * @param array  $event_data Additional event data.
	 * @return bool True on success, false on failure.
	 */
	public static function trigger_immediate( $user_id, $event_type, $event_data = array() ) {
		// Validate event type
		if ( ! CR_Gamification_Event_Registry::is_registered( $event_type ) ) {
			return false;
		}

		// Process immediately
		return CR_Gamification_Achievement_Engine::process_event(
			$user_id,
			$event_type,
			$event_data,
			get_current_blog_id()
		);
	}

	/**
	 * Get events by type.
	 *
	 * @param string $event_type Event type.
	 * @param int    $limit Number of events to retrieve.
	 * @param bool   $processed_only Only get processed events.
	 * @return array Events.
	 */
	public static function get_events_by_type( $event_type, $limit = 100, $processed_only = false ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_event_queue';

		$where = $wpdb->prepare( 'event_type = %s', $event_type );

		if ( $processed_only ) {
			$where .= ' AND processed = 1';
		}

		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);

		if ( ! empty( $events ) ) {
			foreach ( $events as &$event ) {
				$event->event_data = json_decode( $event->event_data, true );
			}
		}

		return $events;
	}
}
