<?php
/**
 * Event Registry for tracking all available events.
 *
 * Provides an extensible system where integrations can register trackable events.
 * Events are used by the Achievement Engine to determine when badges should be awarded.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes
 * @since      1.0.0
 */

class CR_Gamification_Event_Registry {

	/**
	 * Registered events.
	 *
	 * @var array
	 */
	private static $events = array();

	/**
	 * Initialize the event registry.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Register core events
		self::register_core_events();

		/**
		 * Allow other plugins/integrations to register custom events.
		 *
		 * @since 1.0.0
		 */
		do_action( 'cr_gamification_register_events' );
	}

	/**
	 * Register a new trackable event.
	 *
	 * @param array $args {
	 *     Event configuration arguments.
	 *
	 *     @type string $key                Unique event key (required).
	 *     @type string $label              Human-readable event label (required).
	 *     @type string $category           Event category (social|groups|content|learning|commerce|events|forums).
	 *     @type string $hook               WordPress hook that triggers this event (required).
	 *     @type bool   $supports_threshold Whether event supports threshold counting (default: true).
	 *     @type array  $config_fields      Additional configuration fields for this event (default: empty).
	 *     @type string $description        Event description for admin interface (optional).
	 * }
	 * @return bool True on success, false on failure.
	 */
	public static function register( $args ) {
		$defaults = array(
			'key'                => '',
			'label'              => '',
			'category'           => 'social',
			'hook'               => '',
			'supports_threshold' => true,
			'config_fields'      => array(),
			'description'        => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate required fields
		if ( empty( $args['key'] ) || empty( $args['label'] ) || empty( $args['hook'] ) ) {
			return false;
		}

		// Check if event already registered
		if ( isset( self::$events[ $args['key'] ] ) ) {
			return false;
		}

		// Store the event
		self::$events[ $args['key'] ] = $args;

		return true;
	}

	/**
	 * Get a registered event by key.
	 *
	 * @param string $key Event key.
	 * @return array|null Event configuration or null if not found.
	 */
	public static function get_event( $key ) {
		return isset( self::$events[ $key ] ) ? self::$events[ $key ] : null;
	}

	/**
	 * Get all registered events.
	 *
	 * @param string $category Optional category filter.
	 * @return array Registered events.
	 */
	public static function get_events( $category = '' ) {
		if ( empty( $category ) ) {
			return self::$events;
		}

		return array_filter(
			self::$events,
			function( $event ) use ( $category ) {
				return $event['category'] === $category;
			}
		);
	}

	/**
	 * Get all event categories.
	 *
	 * @return array Event categories with labels.
	 */
	public static function get_categories() {
		return array(
			'social'   => __( 'Social', 'crossings-gamification' ),
			'groups'   => __( 'Groups', 'crossings-gamification' ),
			'content'  => __( 'Content', 'crossings-gamification' ),
			'learning' => __( 'Learning', 'crossings-gamification' ),
			'commerce' => __( 'Commerce', 'crossings-gamification' ),
			'events'   => __( 'Events', 'crossings-gamification' ),
			'forums'   => __( 'Forums', 'crossings-gamification' ),
		);
	}

	/**
	 * Check if an event is registered.
	 *
	 * @param string $key Event key.
	 * @return bool True if registered, false otherwise.
	 */
	public static function is_registered( $key ) {
		return isset( self::$events[ $key ] );
	}

	/**
	 * Register core events from integrations.
	 *
	 * @since 1.0.0
	 */
	private static function register_core_events() {
		// Social events
		self::register(
			array(
				'key'                => 'friends_added',
				'label'              => __( 'Friend Added', 'crossings-gamification' ),
				'category'           => 'social',
				'hook'               => 'friends_friendship_accepted',
				'supports_threshold' => true,
				'description'        => __( 'Triggered when a user adds a friend', 'crossings-gamification' ),
			)
		);

		self::register(
			array(
				'key'                => 'followers_gained',
				'label'              => __( 'Follower Gained', 'crossings-gamification' ),
				'category'           => 'social',
				'hook'               => 'bp_start_following',
				'supports_threshold' => true,
				'description'        => __( 'Triggered when a user gains a follower', 'crossings-gamification' ),
			)
		);

		// Group events
		self::register(
			array(
				'key'                => 'groups_joined',
				'label'              => __( 'Group Joined', 'crossings-gamification' ),
				'category'           => 'groups',
				'hook'               => 'groups_join_group',
				'supports_threshold' => true,
				'description'        => __( 'Triggered when a user joins a group', 'crossings-gamification' ),
			)
		);

		self::register(
			array(
				'key'                => 'groups_created',
				'label'              => __( 'Group Created', 'crossings-gamification' ),
				'category'           => 'groups',
				'hook'               => 'groups_create_group',
				'supports_threshold' => true,
				'description'        => __( 'Triggered when a user creates a group', 'crossings-gamification' ),
			)
		);

		// Content events
		self::register(
			array(
				'key'                => 'activity_posted',
				'label'              => __( 'Activity Post Created', 'crossings-gamification' ),
				'category'           => 'content',
				'hook'               => 'bp_activity_posted_update',
				'supports_threshold' => true,
				'description'        => __( 'Triggered when a user posts an activity update', 'crossings-gamification' ),
			)
		);

		// Learning events
		self::register(
			array(
				'key'                => 'course_completed',
				'label'              => __( 'Course Completed', 'crossings-gamification' ),
				'category'           => 'learning',
				'hook'               => 'tutor_course_complete_after',
				'supports_threshold' => true,
				'description'        => __( 'Triggered when a user completes a course', 'crossings-gamification' ),
			)
		);

		// Commerce events
		self::register(
			array(
				'key'                => 'product_purchased',
				'label'              => __( 'Product Purchased', 'crossings-gamification' ),
				'category'           => 'commerce',
				'hook'               => 'woocommerce_order_status_completed',
				'supports_threshold' => true,
				'config_fields'      => array(
					array(
						'key'         => 'vendor_id',
						'label'       => __( 'Specific Vendor', 'crossings-gamification' ),
						'type'        => 'select',
						'description' => __( 'Track purchases from a specific vendor only', 'crossings-gamification' ),
						'options'     => 'vendors', // Dynamic options loaded from Dokan
					),
					array(
						'key'         => 'min_amount',
						'label'       => __( 'Minimum Order Amount', 'crossings-gamification' ),
						'type'        => 'number',
						'description' => __( 'Only count orders above this amount', 'crossings-gamification' ),
					),
					array(
						'key'         => 'product_category',
						'label'       => __( 'Product Category', 'crossings-gamification' ),
						'type'        => 'select',
						'description' => __( 'Track purchases from a specific category', 'crossings-gamification' ),
						'options'     => 'product_categories', // Dynamic options from WooCommerce
					),
				),
				'description'        => __( 'Triggered when an order is completed', 'crossings-gamification' ),
			)
		);

		self::register(
			array(
				'key'                => 'vendor_purchase',
				'label'              => __( 'Vendor Purchase', 'crossings-gamification' ),
				'category'           => 'commerce',
				'hook'               => 'woocommerce_order_status_completed',
				'supports_threshold' => true,
				'config_fields'      => array(
					array(
						'key'         => 'vendor_id',
						'label'       => __( 'Vendor', 'crossings-gamification' ),
						'type'        => 'select',
						'description' => __( 'Select the vendor to track', 'crossings-gamification' ),
						'options'     => 'vendors',
						'required'    => true,
					),
					array(
						'key'         => 'tier_type',
						'label'       => __( 'Tier Type', 'crossings-gamification' ),
						'type'        => 'select',
						'description' => __( 'Bronze, Silver, or Gold tier criteria', 'crossings-gamification' ),
						'options'     => array(
							'bronze' => __( 'Bronze (purchase count)', 'crossings-gamification' ),
							'silver' => __( 'Silver (total spent)', 'crossings-gamification' ),
							'gold'   => __( 'Gold (orders over X)', 'crossings-gamification' ),
						),
					),
				),
				'description'        => __( 'Triggered for vendor-specific purchase milestones', 'crossings-gamification' ),
			)
		);

		// Event events
		self::register(
			array(
				'key'                => 'event_attended',
				'label'              => __( 'Event Attended', 'crossings-gamification' ),
				'category'           => 'events',
				'hook'               => 'event_tickets_woocommerce_ticket_created',
				'supports_threshold' => true,
				'description'        => __( 'Triggered when a user attends an event', 'crossings-gamification' ),
			)
		);

		self::register(
			array(
				'key'                => 'annual_event_attendance',
				'label'              => __( 'Annual Event Attendance', 'crossings-gamification' ),
				'category'           => 'events',
				'hook'               => 'event_tickets_woocommerce_ticket_created',
				'supports_threshold' => true,
				'config_fields'      => array(
					array(
						'key'         => 'event_series',
						'label'       => __( 'Event Series', 'crossings-gamification' ),
						'type'        => 'text',
						'description' => __( 'Event series identifier for annual tracking', 'crossings-gamification' ),
					),
				),
				'description'        => __( 'Triggered for multi-year attendance of the same annual event', 'crossings-gamification' ),
			)
		);
	}

	/**
	 * Get events formatted for admin dropdown.
	 *
	 * @return array Events formatted as value => label pairs.
	 */
	public static function get_events_for_dropdown() {
		$dropdown = array();

		$categories = self::get_categories();

		foreach ( $categories as $cat_key => $cat_label ) {
			$cat_events = self::get_events( $cat_key );

			if ( ! empty( $cat_events ) ) {
				$dropdown[ $cat_label ] = array();

				foreach ( $cat_events as $event ) {
					$dropdown[ $cat_label ][ $event['key'] ] = $event['label'];
				}
			}
		}

		return $dropdown;
	}

	/**
	 * Get config fields for a specific event.
	 *
	 * @param string $event_key Event key.
	 * @return array Config fields or empty array.
	 */
	public static function get_event_config_fields( $event_key ) {
		$event = self::get_event( $event_key );

		if ( ! $event || empty( $event['config_fields'] ) ) {
			return array();
		}

		return $event['config_fields'];
	}
}
