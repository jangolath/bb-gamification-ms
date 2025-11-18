<?php
/**
 * Settings View.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/admin/views
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$updated = isset( $_GET['updated'] ) && $_GET['updated'] === 'true';
?>

<div class="wrap cr-gamification-settings">
	<h1><?php esc_html_e( 'Gamification Settings', 'crossings-gamification' ); ?></h1>

	<?php if ( $updated ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'crossings-gamification' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=cr_gamification_settings' ) ); ?>">
		<?php wp_nonce_field( 'cr-gamification-settings' ); ?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Activity Feed Integration', 'crossings-gamification' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="activity_feed_enabled" value="1" <?php checked( get_site_option( 'cr_gamification_activity_feed_enabled', 1 ), 1 ); ?>>
							<?php esc_html_e( 'Post to activity feed when badges are unlocked', 'crossings-gamification' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Profile Display', 'crossings-gamification' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="profile_display_enabled" value="1" <?php checked( get_site_option( 'cr_gamification_profile_display_enabled', 1 ), 1 ); ?>>
							<?php esc_html_e( 'Show badges on user profiles', 'crossings-gamification' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Featured Badge', 'crossings-gamification' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="featured_badge_enabled" value="1" <?php checked( get_site_option( 'cr_gamification_featured_badge_enabled', 1 ), 1 ); ?>>
							<?php esc_html_e( 'Allow users to select a featured badge', 'crossings-gamification' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Redis Cache', 'crossings-gamification' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="redis_enabled" value="1" <?php checked( get_site_option( 'cr_gamification_redis_enabled', 1 ), 1 ); ?>>
							<?php esc_html_e( 'Enable Redis caching', 'crossings-gamification' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Status:', 'crossings-gamification' ); ?>
							<?php if ( CR_Gamification_Cache_Manager::is_redis_available() ) : ?>
								<strong style="color: green;"><?php esc_html_e( 'Connected', 'crossings-gamification' ); ?></strong>
							<?php else : ?>
								<strong style="color: red;"><?php esc_html_e( 'Not Available', 'crossings-gamification' ); ?></strong>
							<?php endif; ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="cache_ttl"><?php esc_html_e( 'Cache TTL (seconds)', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<input type="number" name="cache_ttl" id="cache_ttl" min="60" value="<?php echo esc_attr( get_site_option( 'cr_gamification_cache_ttl', 3600 ) ); ?>">
						<p class="description"><?php esc_html_e( 'Default cache time-to-live in seconds (3600 = 1 hour)', 'crossings-gamification' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="batch_size"><?php esc_html_e( 'Event Queue Batch Size', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<input type="number" name="batch_size" id="batch_size" min="10" max="200" value="<?php echo esc_attr( get_site_option( 'cr_gamification_batch_size', 50 ) ); ?>">
						<p class="description"><?php esc_html_e( 'Number of events to process per cron run', 'crossings-gamification' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'crossings-gamification' ); ?>
			</button>
		</p>
	</form>
</div>
