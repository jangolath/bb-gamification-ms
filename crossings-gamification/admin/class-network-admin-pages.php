<?php
/**
 * Network Admin Pages.
 *
 * Registers and handles all network admin pages for the gamification system.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/admin
 * @since      1.0.0
 */

class CR_Gamification_Network_Admin {

	/**
	 * Initialize network admin pages.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'network_admin_menu', array( __CLASS__, 'add_menu_pages' ) );
		add_action( 'network_admin_edit_cr_gamification_settings', array( __CLASS__, 'save_settings' ) );
	}

	/**
	 * Add network admin menu pages.
	 *
	 * @since 1.0.0
	 */
	public static function add_menu_pages() {
		// Main menu
		add_menu_page(
			__( 'Gamification', 'crossings-gamification' ),
			__( 'Gamification', 'crossings-gamification' ),
			'manage_network_options',
			'crossings-gamification',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-awards',
			30
		);

		// Dashboard
		add_submenu_page(
			'crossings-gamification',
			__( 'Dashboard', 'crossings-gamification' ),
			__( 'Dashboard', 'crossings-gamification' ),
			'manage_network_options',
			'crossings-gamification',
			array( __CLASS__, 'render_dashboard' )
		);

		// Badges
		add_submenu_page(
			'crossings-gamification',
			__( 'Badges', 'crossings-gamification' ),
			__( 'Badges', 'crossings-gamification' ),
			'manage_network_options',
			'cr-badges',
			array( __CLASS__, 'render_badges' )
		);

		// Awards
		add_submenu_page(
			'crossings-gamification',
			__( 'Awards', 'crossings-gamification' ),
			__( 'Awards', 'crossings-gamification' ),
			'manage_network_options',
			'cr-awards',
			array( __CLASS__, 'render_awards' )
		);

		// Users
		add_submenu_page(
			'crossings-gamification',
			__( 'User Progress', 'crossings-gamification' ),
			__( 'Users', 'crossings-gamification' ),
			'manage_network_options',
			'cr-users',
			array( __CLASS__, 'render_users' )
		);

		// Events & Triggers
		add_submenu_page(
			'crossings-gamification',
			__( 'Events & Triggers', 'crossings-gamification' ),
			__( 'Events', 'crossings-gamification' ),
			'manage_network_options',
			'cr-events',
			array( __CLASS__, 'render_events' )
		);

		// Settings
		add_submenu_page(
			'crossings-gamification',
			__( 'Settings', 'crossings-gamification' ),
			__( 'Settings', 'crossings-gamification' ),
			'manage_network_options',
			'cr-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @since 1.0.0
	 */
	public static function render_dashboard() {
		require_once CROSSINGS_GAMIFICATION_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Render badges page.
	 *
	 * @since 1.0.0
	 */
	public static function render_badges() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		if ( $action === 'edit' || $action === 'add' ) {
			require_once CROSSINGS_GAMIFICATION_PATH . 'admin/views/badge-edit.php';
		} else {
			require_once CROSSINGS_GAMIFICATION_PATH . 'admin/views/badges-list.php';
		}
	}

	/**
	 * Render awards page.
	 *
	 * @since 1.0.0
	 */
	public static function render_awards() {
		require_once CROSSINGS_GAMIFICATION_PATH . 'admin/views/awards-list.php';
	}

	/**
	 * Render users page.
	 *
	 * @since 1.0.0
	 */
	public static function render_users() {
		require_once CROSSINGS_GAMIFICATION_PATH . 'admin/views/user-progress.php';
	}

	/**
	 * Render events page.
	 *
	 * @since 1.0.0
	 */
	public static function render_events() {
		require_once CROSSINGS_GAMIFICATION_PATH . 'admin/views/events-triggers.php';
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public static function render_settings() {
		require_once CROSSINGS_GAMIFICATION_PATH . 'admin/views/settings.php';
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 */
	public static function save_settings() {
		check_admin_referer( 'cr-gamification-settings' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'crossings-gamification' ) );
		}

		$settings = array(
			'activity_feed_enabled',
			'profile_display_enabled',
			'featured_badge_enabled',
			'unlock_animation_enabled',
			'redis_enabled',
			'cache_ttl',
			'event_queue_processing',
			'batch_size',
			'vendor_badges_enabled',
			'annual_event_tracking',
		);

		foreach ( $settings as $setting ) {
			if ( isset( $_POST[ $setting ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $setting ] ) );
				update_site_option( 'cr_gamification_' . $setting, $value );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'cr-settings',
					'updated' => 'true',
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
