<?php
/**
 * Badge Edit/Add View.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/admin/views
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$badge_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$badge = $badge_id ? CR_Gamification_Badge_Manager::get( $badge_id ) : null;
$is_edit = ! empty( $badge );

$title = $is_edit ? __( 'Edit Badge', 'crossings-gamification' ) : __( 'Add New Badge', 'crossings-gamification' );
?>

<div class="wrap cr-gamification-badge-edit">
	<h1><?php echo esc_html( $title ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'cr_save_badge' ); ?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="badge_name"><?php esc_html_e( 'Badge Name', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<input type="text" name="name" id="badge_name" class="regular-text" value="<?php echo $is_edit ? esc_attr( $badge->name ) : ''; ?>" required>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="badge_key"><?php esc_html_e( 'Badge Key', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<input type="text" name="achievement_key" id="badge_key" class="regular-text" value="<?php echo $is_edit ? esc_attr( $badge->achievement_key ) : ''; ?>" required>
						<p class="description"><?php esc_html_e( 'Unique identifier (e.g., friends_100)', 'crossings-gamification' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="badge_description"><?php esc_html_e( 'Description', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<textarea name="description" id="badge_description" class="large-text" rows="3"><?php echo $is_edit ? esc_textarea( $badge->description ) : ''; ?></textarea>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="badge_category"><?php esc_html_e( 'Category', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<select name="category" id="badge_category">
							<?php
							$categories = CR_Gamification_Event_Registry::get_categories();
							foreach ( $categories as $key => $label ) :
								$selected = $is_edit && $badge->category === $key ? 'selected' : '';
								?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php echo esc_attr( $selected ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="badge_trigger"><?php esc_html_e( 'Trigger Event', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<select name="trigger_type" id="badge_trigger" required>
							<option value=""><?php esc_html_e( '-- Select Event --', 'crossings-gamification' ); ?></option>
							<?php
							$events = CR_Gamification_Event_Registry::get_events_for_dropdown();
							foreach ( $events as $group_label => $group_events ) :
								?>
								<optgroup label="<?php echo esc_attr( $group_label ); ?>">
									<?php foreach ( $group_events as $event_key => $event_label ) : ?>
										<option value="<?php echo esc_attr( $event_key ); ?>" <?php echo $is_edit && $badge->trigger_type === $event_key ? 'selected' : ''; ?>>
											<?php echo esc_html( $event_label ); ?>
										</option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="badge_threshold"><?php esc_html_e( 'Threshold', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<input type="number" name="threshold" id="badge_threshold" min="1" value="<?php echo $is_edit ? esc_attr( $badge->threshold ) : '1'; ?>" required>
						<p class="description"><?php esc_html_e( 'Number required to unlock this badge', 'crossings-gamification' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="badge_media_url"><?php esc_html_e( 'Media URL', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<input type="url" name="media_url" id="badge_media_url" class="large-text" value="<?php echo $is_edit ? esc_url( $badge->media_url ) : ''; ?>">
						<p class="description"><?php esc_html_e( 'Bunny.net CDN URL for badge image/SVG/Lottie', 'crossings-gamification' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="badge_active"><?php esc_html_e( 'Status', 'crossings-gamification' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="is_active" id="badge_active" value="1" <?php echo ! $is_edit || $badge->is_active ? 'checked' : ''; ?>>
							<?php esc_html_e( 'Active', 'crossings-gamification' ); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php echo $is_edit ? esc_html__( 'Update Badge', 'crossings-gamification' ) : esc_html__( 'Create Badge', 'crossings-gamification' ); ?>
			</button>
			<a href="<?php echo esc_url( remove_query_arg( array( 'action', 'id' ) ) ); ?>" class="button">
				<?php esc_html_e( 'Cancel', 'crossings-gamification' ); ?>
			</a>
		</p>
	</form>
</div>
