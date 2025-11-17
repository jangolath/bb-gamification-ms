<?php
/**
 * Badges List View.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/admin/views
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get all badges
$badges = CR_Gamification_Badge_Manager::get_all();
$categories = CR_Gamification_Event_Registry::get_categories();
?>

<div class="wrap cr-gamification-badges">
	<h1>
		<?php esc_html_e( 'Badges', 'crossings-gamification' ); ?>
		<a href="<?php echo esc_url( add_query_arg( 'action', 'add' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'crossings-gamification' ); ?>
		</a>
	</h1>

	<?php if ( ! empty( $badges ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Badge', 'crossings-gamification' ); ?></th>
					<th><?php esc_html_e( 'Category', 'crossings-gamification' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'crossings-gamification' ); ?></th>
					<th><?php esc_html_e( 'Threshold', 'crossings-gamification' ); ?></th>
					<th><?php esc_html_e( 'Unlocks', 'crossings-gamification' ); ?></th>
					<th><?php esc_html_e( 'Status', 'crossings-gamification' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'crossings-gamification' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $badges as $badge ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $badge->name ); ?></strong>
							<br><small><?php echo esc_html( $badge->achievement_key ); ?></small>
						</td>
						<td><?php echo esc_html( isset( $categories[ $badge->category ] ) ? $categories[ $badge->category ] : $badge->category ); ?></td>
						<td><?php echo esc_html( $badge->trigger_type ); ?></td>
						<td><?php echo esc_html( $badge->threshold ); ?></td>
						<td>
							<?php
							global $wpdb;
							$unlocks = $wpdb->get_var(
								$wpdb->prepare(
									"SELECT COUNT(*) FROM {$wpdb->base_prefix}cr_user_achievements WHERE achievement_id = %d AND unlocked_at IS NOT NULL",
									$badge->id
								)
							);
							echo esc_html( number_format_i18n( $unlocks ) );
							?>
						</td>
						<td>
							<?php if ( $badge->is_active ) : ?>
								<span class="cr-badge-active"><?php esc_html_e( 'Active', 'crossings-gamification' ); ?></span>
							<?php else : ?>
								<span class="cr-badge-inactive"><?php esc_html_e( 'Inactive', 'crossings-gamification' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => $badge->id ) ) ); ?>">
								<?php esc_html_e( 'Edit', 'crossings-gamification' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No badges found.', 'crossings-gamification' ); ?></p>
	<?php endif; ?>
</div>
