<?php
/**
 * Uninstall script.
 *
 * Fired when the plugin is uninstalled. Removes all plugin data from the database.
 *
 * @package    Crossings_Gamification
 * @since      1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Use base_prefix for network-wide tables
$prefix = $wpdb->base_prefix;

// Drop custom tables
$tables = array(
	$prefix . 'cr_achievements',
	$prefix . 'cr_user_achievements',
	$prefix . 'cr_achievement_history',
	$prefix . 'cr_event_queue',
	$prefix . 'cr_vendor_progress',
	$prefix . 'cr_content_metrics',
	$prefix . 'cr_user_awards',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete site options
$options = array(
	'cr_gamification_db_version',
	'cr_gamification_activity_feed_enabled',
	'cr_gamification_profile_display_enabled',
	'cr_gamification_featured_badge_enabled',
	'cr_gamification_unlock_animation_enabled',
	'cr_gamification_redis_enabled',
	'cr_gamification_cache_ttl',
	'cr_gamification_event_queue_processing',
	'cr_gamification_batch_size',
	'cr_gamification_vendor_badges_enabled',
	'cr_gamification_annual_event_tracking',
);

foreach ( $options as $option ) {
	delete_site_option( $option );
}

// Delete user meta related to gamification
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cr_%'" );

// Clear scheduled events
wp_clear_scheduled_hook( 'cr_process_event_queue' );
wp_clear_scheduled_hook( 'cr_recalculate_achievements' );

// Flush cache
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}
