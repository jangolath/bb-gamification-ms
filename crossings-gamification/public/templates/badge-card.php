<?php
/**
 * Badge Card Template.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/public/templates
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $badge ) ) {
	return;
}
?>

<div class="cr-featured-badge">
	<?php if ( $badge->media_type === 'lottie' && ! empty( $badge->media_url ) ) : ?>
		<lottie-player src="<?php echo esc_url( $badge->media_url ); ?>" background="transparent" speed="1" style="width: 50px; height: 50px;" loop autoplay></lottie-player>
	<?php elseif ( ! empty( $badge->media_url ) ) : ?>
		<img src="<?php echo esc_url( $badge->media_url ); ?>" alt="<?php echo esc_attr( $badge->name ); ?>" style="width: 50px; height: 50px;">
	<?php endif; ?>
	<span class="cr-badge-name"><?php echo esc_html( $badge->name ); ?></span>
</div>
