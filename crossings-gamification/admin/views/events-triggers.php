<?php
/**
 * Events & Triggers View.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/admin/views
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$queue_stats = CR_Gamification_Event_Bus::get_queue_stats();
$events = CR_Gamification_Event_Registry::get_events();
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Events & Triggers', 'crossings-gamification' ); ?></h1>

	<h2><?php esc_html_e( 'Event Queue Monitor', 'crossings-gamification' ); ?></h2>
	<table class="widefat">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'Pending', 'crossings-gamification' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $queue_stats['pending'] ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Processed', 'crossings-gamification' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $queue_stats['processed'] ) ); ?></td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Registered Events', 'crossings-gamification' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Event', 'crossings-gamification' ); ?></th>
				<th><?php esc_html_e( 'Category', 'crossings-gamification' ); ?></th>
				<th><?php esc_html_e( 'Hook', 'crossings-gamification' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $events as $event ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $event['label'] ); ?></strong></td>
					<td><?php echo esc_html( $event['category'] ); ?></td>
					<td><code><?php echo esc_html( $event['hook'] ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
