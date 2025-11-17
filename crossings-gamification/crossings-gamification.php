<?php
/**
 * Plugin Name: Crossings Gamification
 * Plugin URI: https://crossings.com/gamification
 * Description: Comprehensive gamification and achievement system for BuddyBoss multisite network. Tracks user achievements across social interactions, learning, commerce, and events with badge awards and profile display.
 * Version: 1.0.0
 * Author: Crossings
 * Author URI: https://crossings.com
 * Network: true
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: crossings-gamification
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Crossings_Gamification
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'CROSSINGS_GAMIFICATION_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 */
define( 'CROSSINGS_GAMIFICATION_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'CROSSINGS_GAMIFICATION_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'CROSSINGS_GAMIFICATION_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_crossings_gamification() {
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/class-activator.php';
	CR_Gamification_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_crossings_gamification() {
	// Clean up scheduled events
	wp_clear_scheduled_hook( 'cr_process_event_queue' );
	wp_clear_scheduled_hook( 'cr_recalculate_achievements' );
}

register_activation_hook( __FILE__, 'activate_crossings_gamification' );
register_deactivation_hook( __FILE__, 'deactivate_crossings_gamification' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 1.0.0
 */
function run_crossings_gamification() {
	// Check if this is a multisite installation
	if ( ! is_multisite() ) {
		add_action( 'admin_notices', 'cr_multisite_required_notice' );
		return;
	}

	// Check if network activated
	if ( ! is_plugin_active_for_network( CROSSINGS_GAMIFICATION_BASENAME ) ) {
		add_action( 'network_admin_notices', 'cr_network_activation_required_notice' );
		return;
	}

	// Load dependencies
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/class-cache-manager.php';
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/class-event-registry.php';
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/class-event-bus.php';
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/class-user-achievements.php';
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/class-achievement-engine.php';

	// Load integrations
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/integrations/class-buddyboss-integration.php';
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/integrations/class-tutorlms-integration.php';
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/integrations/class-dokan-integration.php';
	require_once CROSSINGS_GAMIFICATION_PATH . 'includes/integrations/class-events-calendar-integration.php';

	// Load admin functionality
	if ( is_admin() ) {
		require_once CROSSINGS_GAMIFICATION_PATH . 'admin/class-admin-controller.php';
		require_once CROSSINGS_GAMIFICATION_PATH . 'admin/class-network-admin-pages.php';
		require_once CROSSINGS_GAMIFICATION_PATH . 'admin/class-badge-manager.php';

		CR_Gamification_Admin_Controller::init();
		CR_Gamification_Network_Admin::init();
	}

	// Load public-facing functionality
	if ( ! is_admin() ) {
		require_once CROSSINGS_GAMIFICATION_PATH . 'public/class-profile-display.php';
		require_once CROSSINGS_GAMIFICATION_PATH . 'public/class-activity-feed.php';

		CR_Gamification_Profile_Display::init();
		CR_Gamification_Activity_Feed::init();
	}

	// Initialize core components
	CR_Gamification_Cache_Manager::init();
	CR_Gamification_Event_Registry::init();
	CR_Gamification_Event_Bus::init();
	CR_Gamification_Achievement_Engine::init();

	// Initialize integrations
	CR_Gamification_BuddyBoss_Integration::init();
	CR_Gamification_TutorLMS_Integration::init();
	CR_Gamification_Dokan_Integration::init();
	CR_Gamification_Events_Calendar_Integration::init();

	// Schedule cron jobs if not already scheduled
	if ( ! wp_next_scheduled( 'cr_process_event_queue' ) ) {
		wp_schedule_event( time(), 'every_minute', 'cr_process_event_queue' );
	}
}

add_action( 'plugins_loaded', 'run_crossings_gamification' );

/**
 * Add custom cron schedule for event queue processing.
 *
 * @param array $schedules Existing schedules.
 * @return array Modified schedules.
 */
function cr_add_cron_schedules( $schedules ) {
	$schedules['every_minute'] = array(
		'interval' => 60,
		'display'  => __( 'Every Minute', 'crossings-gamification' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'cr_add_cron_schedules' );

/**
 * Display notice when multisite is required.
 */
function cr_multisite_required_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Crossings Gamification requires WordPress Multisite to be enabled.', 'crossings-gamification' ); ?></p>
	</div>
	<?php
}

/**
 * Display notice when network activation is required.
 */
function cr_network_activation_required_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Crossings Gamification must be network activated. Please activate it from the Network Admin → Plugins page.', 'crossings-gamification' ); ?></p>
	</div>
	<?php
}

/**
 * Load plugin text domain for translations.
 */
function cr_load_textdomain() {
	load_plugin_textdomain(
		'crossings-gamification',
		false,
		dirname( CROSSINGS_GAMIFICATION_BASENAME ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'cr_load_textdomain' );
