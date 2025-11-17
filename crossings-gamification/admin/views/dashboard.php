<?php
/**
 * Admin Dashboard View.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/admin/views
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get statistics
$stats = CR_Gamification_Badge_Manager::get_statistics();
$queue_stats = CR_Gamification_Event_Bus::get_queue_stats();
?>

<div class="wrap cr-gamification-dashboard">
	<h1><?php esc_html_e( 'Gamification Dashboard', 'crossings-gamification' ); ?></h1>

	<div class="cr-dashboard-stats">
		<div class="cr-stat-box">
			<div class="cr-stat-icon">
				<span class="dashicons dashicons-awards"></span>
			</div>
			<div class="cr-stat-content">
				<h3><?php echo esc_html( number_format_i18n( $stats['total_badges'] ) ); ?></h3>
				<p><?php esc_html_e( 'Total Badges', 'crossings-gamification' ); ?></p>
			</div>
		</div>

		<div class="cr-stat-box">
			<div class="cr-stat-icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="cr-stat-content">
				<h3><?php echo esc_html( number_format_i18n( $stats['active_badges'] ) ); ?></h3>
				<p><?php esc_html_e( 'Active Badges', 'crossings-gamification' ); ?></p>
			</div>
		</div>

		<div class="cr-stat-box">
			<div class="cr-stat-icon">
				<span class="dashicons dashicons-star-filled"></span>
			</div>
			<div class="cr-stat-content">
				<h3><?php echo esc_html( number_format_i18n( $stats['total_unlocks'] ) ); ?></h3>
				<p><?php esc_html_e( 'Total Unlocks', 'crossings-gamification' ); ?></p>
			</div>
		</div>

		<div class="cr-stat-box">
			<div class="cr-stat-icon">
				<span class="dashicons dashicons-calendar-alt"></span>
			</div>
			<div class="cr-stat-content">
				<h3><?php echo esc_html( number_format_i18n( $stats['unlocks_today'] ) ); ?></h3>
				<p><?php esc_html_e( 'Unlocks Today', 'crossings-gamification' ); ?></p>
			</div>
		</div>
	</div>

	<div class="cr-dashboard-row">
		<div class="cr-dashboard-col-6">
			<div class="cr-dashboard-card">
				<h2><?php esc_html_e( 'Event Queue Status', 'crossings-gamification' ); ?></h2>
				<table class="widefat">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Pending Events', 'crossings-gamification' ); ?></th>
							<td><?php echo esc_html( number_format_i18n( $queue_stats['pending'] ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Processed Events', 'crossings-gamification' ); ?></th>
							<td><?php echo esc_html( number_format_i18n( $queue_stats['processed'] ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Last Processing Run', 'crossings-gamification' ); ?></th>
							<td>
								<?php
								if ( $queue_stats['last_run'] ) {
									echo esc_html( human_time_diff( strtotime( $queue_stats['last_run'] ), current_time( 'timestamp' ) ) );
									esc_html_e( ' ago', 'crossings-gamification' );
								} else {
									esc_html_e( 'Never', 'crossings-gamification' );
								}
								?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Avg Processing Time', 'crossings-gamification' ); ?></th>
							<td><?php echo esc_html( $queue_stats['avg_time'] ); ?> <?php esc_html_e( 'seconds', 'crossings-gamification' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="cr-dashboard-col-6">
			<div class="cr-dashboard-card">
				<h2><?php esc_html_e( 'System Status', 'crossings-gamification' ); ?></h2>
				<table class="widefat">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Redis Cache', 'crossings-gamification' ); ?></th>
							<td>
								<?php if ( CR_Gamification_Cache_Manager::is_redis_available() ) : ?>
									<span class="cr-status-active"><?php esc_html_e( 'Active', 'crossings-gamification' ); ?></span>
								<?php else : ?>
									<span class="cr-status-inactive"><?php esc_html_e( 'Inactive', 'crossings-gamification' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'BuddyBoss', 'crossings-gamification' ); ?></th>
							<td>
								<?php if ( function_exists( 'bp_is_active' ) ) : ?>
									<span class="cr-status-active"><?php esc_html_e( 'Active', 'crossings-gamification' ); ?></span>
								<?php else : ?>
									<span class="cr-status-inactive"><?php esc_html_e( 'Not Installed', 'crossings-gamification' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'TutorLMS', 'crossings-gamification' ); ?></th>
							<td>
								<?php if ( function_exists( 'tutor' ) ) : ?>
									<span class="cr-status-active"><?php esc_html_e( 'Active', 'crossings-gamification' ); ?></span>
								<?php else : ?>
									<span class="cr-status-inactive"><?php esc_html_e( 'Not Installed', 'crossings-gamification' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Dokan', 'crossings-gamification' ); ?></th>
							<td>
								<?php if ( function_exists( 'dokan' ) ) : ?>
									<span class="cr-status-active"><?php esc_html_e( 'Active', 'crossings-gamification' ); ?></span>
								<?php else : ?>
									<span class="cr-status-inactive"><?php esc_html_e( 'Not Installed', 'crossings-gamification' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'The Events Calendar', 'crossings-gamification' ); ?></th>
							<td>
								<?php if ( class_exists( 'Tribe__Events__Main' ) ) : ?>
									<span class="cr-status-active"><?php esc_html_e( 'Active', 'crossings-gamification' ); ?></span>
								<?php else : ?>
									<span class="cr-status-inactive"><?php esc_html_e( 'Not Installed', 'crossings-gamification' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="cr-dashboard-card">
		<h2><?php esc_html_e( 'Quick Actions', 'crossings-gamification' ); ?></h2>
		<p>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'cr-badges', 'action' => 'add' ), network_admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Create New Badge', 'crossings-gamification' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'page', 'cr-badges', network_admin_url( 'admin.php' ) ) ); ?>" class="button">
				<?php esc_html_e( 'Manage Badges', 'crossings-gamification' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'page', 'cr-users', network_admin_url( 'admin.php' ) ) ); ?>" class="button">
				<?php esc_html_e( 'View User Progress', 'crossings-gamification' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'page', 'cr-settings', network_admin_url( 'admin.php' ) ) ); ?>" class="button">
				<?php esc_html_e( 'Settings', 'crossings-gamification' ); ?>
			</a>
		</p>
	</div>
</div>
