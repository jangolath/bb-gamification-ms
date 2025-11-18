<?php
/**
 * Admin Controller.
 *
 * Handles admin-specific functionality including enqueuing styles and scripts.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/admin
 * @since      1.0.0
 */

class CR_Gamification_Admin_Controller {

	/**
	 * Initialize the admin controller.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue_styles( $hook_suffix ) {
		// Only load on our plugin pages
		if ( strpos( $hook_suffix, 'crossings-gamification' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'cr-gamification-admin',
			CROSSINGS_GAMIFICATION_URL . 'assets/css/admin.css',
			array(),
			CROSSINGS_GAMIFICATION_VERSION,
			'all'
		);

		// Color picker
		wp_enqueue_style( 'wp-color-picker' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		// Only load on our plugin pages
		if ( strpos( $hook_suffix, 'crossings-gamification' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'cr-gamification-admin',
			CROSSINGS_GAMIFICATION_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			CROSSINGS_GAMIFICATION_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'cr-gamification-admin',
			'crGamificationAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'cr_gamification_admin' ),
				'strings'       => array(
					'confirmDelete' => __( 'Are you sure you want to delete this achievement?', 'crossings-gamification' ),
					'confirmRevoke' => __( 'Are you sure you want to revoke this achievement from the user?', 'crossings-gamification' ),
					'loading'       => __( 'Loading...', 'crossings-gamification' ),
					'error'         => __( 'An error occurred. Please try again.', 'crossings-gamification' ),
				),
			)
		);
	}
}
