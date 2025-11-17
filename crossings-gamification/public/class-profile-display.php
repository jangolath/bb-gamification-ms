<?php
/**
 * Profile Display.
 *
 * Handles display of badges and awards on BuddyBoss user profiles.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/public
 * @since      1.0.0
 */

class CR_Gamification_Profile_Display {

	/**
	 * Initialize the profile display.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Check if BuddyPress/BuddyBoss is active
		if ( ! function_exists( 'bp_is_active' ) ) {
			return;
		}

		// Add profile tab
		add_action( 'bp_setup_nav', array( __CLASS__, 'add_profile_tab' ), 100 );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		// AJAX handlers
		add_action( 'wp_ajax_cr_set_featured_badge', array( __CLASS__, 'ajax_set_featured_badge' ) );
	}

	/**
	 * Add badges tab to BuddyBoss profile.
	 *
	 * @since 1.0.0
	 */
	public static function add_profile_tab() {
		if ( ! get_site_option( 'cr_gamification_profile_display_enabled', 1 ) ) {
			return;
		}

		bp_core_new_nav_item(
			array(
				'name'                    => __( 'Badges & Awards', 'crossings-gamification' ),
				'slug'                    => 'badges',
				'screen_function'         => array( __CLASS__, 'display_badges_screen' ),
				'position'                => 80,
				'default_subnav_slug'     => 'all',
			)
		);

		bp_core_new_subnav_item(
			array(
				'name'            => __( 'All', 'crossings-gamification' ),
				'slug'            => 'all',
				'parent_url'      => bp_displayed_user_domain() . 'badges/',
				'parent_slug'     => 'badges',
				'screen_function' => array( __CLASS__, 'display_badges_screen' ),
				'position'        => 10,
			)
		);

		$categories = CR_Gamification_Event_Registry::get_categories();
		$position   = 20;

		foreach ( $categories as $cat_key => $cat_label ) {
			bp_core_new_subnav_item(
				array(
					'name'            => $cat_label,
					'slug'            => $cat_key,
					'parent_url'      => bp_displayed_user_domain() . 'badges/',
					'parent_slug'     => 'badges',
					'screen_function' => array( __CLASS__, 'display_badges_screen' ),
					'position'        => $position,
				)
			);

			$position += 10;
		}
	}

	/**
	 * Display badges screen content.
	 *
	 * @since 1.0.0
	 */
	public static function display_badges_screen() {
		add_action( 'bp_template_content', array( __CLASS__, 'display_badges_content' ) );
		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * Display badges content.
	 *
	 * @since 1.0.0
	 */
	public static function display_badges_content() {
		$user_id  = bp_displayed_user_id();
		$category = bp_current_action();

		if ( $category === 'badges' || $category === 'all' ) {
			$category = '';
		}

		require CROSSINGS_GAMIFICATION_PATH . 'public/templates/profile-badges.php';
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 1.0.0
	 */
	public static function enqueue_scripts() {
		if ( ! bp_is_user() ) {
			return;
		}

		// Enqueue Lottie player
		wp_enqueue_script(
			'lottie-player',
			'https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js',
			array(),
			null,
			true
		);

		// Enqueue our styles
		wp_enqueue_style(
			'cr-gamification-public',
			CROSSINGS_GAMIFICATION_URL . 'assets/css/public.css',
			array(),
			CROSSINGS_GAMIFICATION_VERSION
		);

		// Enqueue our scripts
		wp_enqueue_script(
			'cr-gamification-public',
			CROSSINGS_GAMIFICATION_URL . 'assets/js/gamification.js',
			array( 'jquery' ),
			CROSSINGS_GAMIFICATION_VERSION,
			true
		);

		wp_localize_script(
			'cr-gamification-public',
			'crGamification',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cr_gamification_public' ),
				'userId'  => bp_displayed_user_id(),
			)
		);
	}

	/**
	 * AJAX handler for setting featured badge.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_set_featured_badge() {
		check_ajax_referer( 'cr_gamification_public', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'crossings-gamification' ) ) );
		}

		$achievement_id = isset( $_POST['achievement_id'] ) ? absint( $_POST['achievement_id'] ) : 0;

		if ( ! $achievement_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid achievement ID', 'crossings-gamification' ) ) );
		}

		$result = CR_Gamification_User_Achievements::set_featured_badge( $user_id, $achievement_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Featured badge updated', 'crossings-gamification' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update featured badge', 'crossings-gamification' ) ) );
		}
	}

	/**
	 * Get featured badge HTML for member cards.
	 *
	 * @param int $user_id User ID.
	 * @return string HTML output.
	 */
	public static function get_featured_badge_html( $user_id ) {
		$badge = CR_Gamification_User_Achievements::get_featured_badge( $user_id );

		if ( ! $badge ) {
			return '';
		}

		ob_start();
		require CROSSINGS_GAMIFICATION_PATH . 'public/templates/badge-card.php';
		return ob_get_clean();
	}
}
