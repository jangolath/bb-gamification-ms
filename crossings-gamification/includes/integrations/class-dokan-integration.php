<?php
/**
 * Dokan/WooCommerce Integration.
 *
 * Handles integration with Dokan and WooCommerce for tracking commerce achievements:
 * - Product purchases
 * - Vendor-specific purchase tracking (Bronze, Silver, Gold tiers)
 * - First purchase badge
 * - Cross-site purchase event queuing
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes/integrations
 * @since      1.0.0
 */

class CR_Gamification_Dokan_Integration {

	/**
	 * Initialize the integration.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Order completion tracking
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_completed' ), 10, 2 );
	}

	/**
	 * Handle order completion.
	 *
	 * Processes the order for gamification achievements and vendor-specific tracking.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order Order object.
	 */
	public static function on_order_completed( $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_customer_id();

		if ( ! $user_id ) {
			return;
		}

		// Get order total
		$order_total = (float) $order->get_total();

		// Get order items to track vendors
		$items    = $order->get_items();
		$vendors  = array();

		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();

			// Get vendor if Dokan is active
			if ( function_exists( 'dokan_get_vendor_by_product' ) ) {
				$vendor = dokan_get_vendor_by_product( $product_id );

				if ( $vendor && $vendor->get_id() ) {
					$vendor_id = $vendor->get_id();

					if ( ! isset( $vendors[ $vendor_id ] ) ) {
						$vendors[ $vendor_id ] = array(
							'vendor_id'   => $vendor_id,
							'vendor_name' => $vendor->get_shop_name(),
							'total'       => 0,
							'products'    => array(),
						);
					}

					$vendors[ $vendor_id ]['total'] += (float) $item->get_total();
					$vendors[ $vendor_id ]['products'][] = $product_id;
				}
			}
		}

		// Queue general product purchase event
		CR_Gamification_Event_Bus::queue_event(
			$user_id,
			'product_purchased',
			array(
				'order_id'    => $order_id,
				'order_total' => $order_total,
				'site_id'     => get_current_blog_id(),
			)
		);

		// Process vendor-specific purchases
		foreach ( $vendors as $vendor_data ) {
			self::process_vendor_purchase( $user_id, $vendor_data['vendor_id'], $vendor_data['total'], $order_id );

			// Queue vendor-specific event
			CR_Gamification_Event_Bus::queue_event(
				$user_id,
				'vendor_purchase',
				array(
					'vendor_id'    => $vendor_data['vendor_id'],
					'vendor_name'  => $vendor_data['vendor_name'],
					'amount'       => $vendor_data['total'],
					'order_id'     => $order_id,
					'site_id'      => get_current_blog_id(),
				)
			);
		}

		/**
		 * Fires after order completion is processed for gamification.
		 *
		 * @since 1.0.0
		 *
		 * @param int      $user_id User ID.
		 * @param int      $order_id Order ID.
		 * @param WC_Order $order Order object.
		 * @param array    $vendors Vendor data.
		 */
		do_action( 'cr_order_completed', $user_id, $order_id, $order, $vendors );
	}

	/**
	 * Process vendor-specific purchase for tiered badges.
	 *
	 * @param int   $user_id User ID.
	 * @param int   $vendor_id Vendor ID.
	 * @param float $amount Purchase amount.
	 * @param int   $order_id Order ID.
	 */
	private static function process_vendor_purchase( $user_id, $vendor_id, $amount, $order_id ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_vendor_progress';

		// Update purchase count
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (user_id, vendor_id, metric_type, current_value)
				VALUES (%d, %d, 'purchase_count', 1)
				ON DUPLICATE KEY UPDATE current_value = current_value + 1",
				$user_id,
				$vendor_id
			)
		);

		// Update total spent
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (user_id, vendor_id, metric_type, current_value)
				VALUES (%d, %d, 'total_spent', %f)
				ON DUPLICATE KEY UPDATE current_value = current_value + %f",
				$user_id,
				$vendor_id,
				$amount,
				$amount
			)
		);

		// Update orders over X (example: orders over $100)
		if ( $amount >= 100 ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (user_id, vendor_id, metric_type, current_value)
					VALUES (%d, %d, 'orders_over_x', 1)
					ON DUPLICATE KEY UPDATE current_value = current_value + 1",
					$user_id,
					$vendor_id
				)
			);
		}
	}

	/**
	 * Get user's vendor progress.
	 *
	 * @param int $user_id User ID.
	 * @param int $vendor_id Vendor ID.
	 * @return array Vendor progress metrics.
	 */
	public static function get_vendor_progress( $user_id, $vendor_id ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_vendor_progress';

		$metrics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT metric_type, current_value
				FROM {$table}
				WHERE user_id = %d AND vendor_id = %d",
				$user_id,
				$vendor_id
			),
			OBJECT_K
		);

		$progress = array(
			'purchase_count' => 0,
			'total_spent'    => 0,
			'orders_over_x'  => 0,
		);

		foreach ( $metrics as $metric_type => $data ) {
			$progress[ $metric_type ] = (float) $data->current_value;
		}

		return $progress;
	}

	/**
	 * Get all vendors user has purchased from.
	 *
	 * @param int $user_id User ID.
	 * @return array Vendors with purchase data.
	 */
	public static function get_user_vendors( $user_id ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'cr_vendor_progress';

		$vendor_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT vendor_id
				FROM {$table}
				WHERE user_id = %d",
				$user_id
			)
		);

		if ( empty( $vendor_ids ) ) {
			return array();
		}

		$vendors = array();

		foreach ( $vendor_ids as $vendor_id ) {
			$vendor_data = array(
				'vendor_id' => $vendor_id,
				'progress'  => self::get_vendor_progress( $user_id, $vendor_id ),
			);

			// Get vendor info if Dokan is active
			if ( function_exists( 'dokan' ) ) {
				$vendor = dokan()->vendor->get( $vendor_id );

				if ( $vendor ) {
					$vendor_data['name'] = $vendor->get_shop_name();
					$vendor_data['url']  = $vendor->get_shop_url();
				}
			}

			$vendors[] = $vendor_data;
		}

		return $vendors;
	}

	/**
	 * Get user's total purchase count.
	 *
	 * @param int $user_id User ID.
	 * @return int Total purchases.
	 */
	public static function get_total_purchases( $user_id ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return 0;
		}

		// Get completed orders count
		$customer = new WC_Customer( $user_id );

		return (int) $customer->get_order_count();
	}

	/**
	 * Get all Dokan vendors for dropdown.
	 *
	 * @return array Vendors as ID => Name pairs.
	 */
	public static function get_vendors_list() {
		if ( ! function_exists( 'dokan_get_sellers' ) ) {
			return array();
		}

		$vendors_data = dokan_get_sellers(
			array(
				'number' => -1,
			)
		);

		$vendors = array();

		if ( ! empty( $vendors_data['users'] ) ) {
			foreach ( $vendors_data['users'] as $vendor ) {
				if ( function_exists( 'dokan' ) ) {
					$vendor_obj = dokan()->vendor->get( $vendor->ID );

					if ( $vendor_obj ) {
						$vendors[ $vendor->ID ] = $vendor_obj->get_shop_name();
					}
				}
			}
		}

		return $vendors;
	}

	/**
	 * Get product categories for dropdown.
	 *
	 * @return array Product categories as ID => Name pairs.
	 */
	public static function get_product_categories() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		$options = array();

		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$options[ $category->term_id ] = $category->name;
			}
		}

		return $options;
	}

	/**
	 * Check if user has made first purchase.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if has made purchase, false otherwise.
	 */
	public static function has_made_purchase( $user_id ) {
		return self::get_total_purchases( $user_id ) > 0;
	}
}
