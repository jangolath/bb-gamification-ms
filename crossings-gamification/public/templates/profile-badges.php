<?php
/**
 * Profile Badges Template.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/public/templates
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$achievements = CR_Gamification_User_Achievements::get_user_achievements(
	$user_id,
	array(
		'category'      => $category,
		'unlocked_only' => true,
	)
);

$awards = CR_Gamification_User_Achievements::get_user_awards( $user_id );
?>

<div class="cr-profile-badges">
	<?php if ( ! empty( $achievements ) ) : ?>
		<div class="cr-badges-grid">
			<?php foreach ( $achievements as $achievement ) : ?>
				<?php $badge = $achievement; ?>
				<div class="cr-badge-card" data-badge-id="<?php echo esc_attr( $badge->id ); ?>">
					<div class="cr-badge-image">
						<?php if ( $badge->media_type === 'lottie' && ! empty( $badge->media_url ) ) : ?>
							<lottie-player src="<?php echo esc_url( $badge->media_url ); ?>" background="transparent" speed="1" style="width: 100px; height: 100px;" loop autoplay></lottie-player>
						<?php elseif ( ! empty( $badge->media_url ) ) : ?>
							<img src="<?php echo esc_url( $badge->media_url ); ?>" alt="<?php echo esc_attr( $badge->name ); ?>">
						<?php else : ?>
							<div class="cr-badge-placeholder" style="background: <?php echo esc_attr( $badge->color_primary ); ?>;">
								<span class="dashicons dashicons-awards"></span>
							</div>
						<?php endif; ?>
					</div>
					<div class="cr-badge-info">
						<h4><?php echo esc_html( $badge->name ); ?></h4>
						<p><?php echo esc_html( $badge->description ); ?></p>
						<small><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $badge->unlocked_at ) ) ); ?></small>
						<?php if ( $user_id === get_current_user_id() && get_site_option( 'cr_gamification_featured_badge_enabled', 1 ) ) : ?>
							<button class="cr-set-featured-badge button" data-badge-id="<?php echo esc_attr( $badge->achievement_id ); ?>">
								<?php esc_html_e( 'Set as Featured', 'crossings-gamification' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'No badges earned yet.', 'crossings-gamification' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $awards ) ) : ?>
		<h3><?php esc_html_e( 'Awards', 'crossings-gamification' ); ?></h3>
		<div class="cr-awards-grid">
			<?php foreach ( $awards as $award ) : ?>
				<div class="cr-award-card">
					<?php if ( ! empty( $award->media_url ) ) : ?>
						<img src="<?php echo esc_url( $award->media_url ); ?>" alt="<?php echo esc_attr( $award->title ); ?>">
					<?php endif; ?>
					<h4><?php echo esc_html( $award->title ); ?></h4>
					<p><?php echo esc_html( $award->description ); ?></p>
					<small><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $award->awarded_at ) ) ); ?></small>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
