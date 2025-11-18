<?php
/**
 * Cache Manager for Redis integration.
 *
 * Handles all caching operations for badge counts, leaderboards, and user progress.
 * Falls back to WordPress transients if Redis is not available.
 *
 * @package    Crossings_Gamification
 * @subpackage Crossings_Gamification/includes
 * @since      1.0.0
 */

class CR_Gamification_Cache_Manager {

	/**
	 * Redis client instance.
	 *
	 * @var Redis|null
	 */
	private static $redis = null;

	/**
	 * Whether Redis is available.
	 *
	 * @var bool
	 */
	private static $redis_available = false;

	/**
	 * Default cache TTL in seconds.
	 *
	 * @var int
	 */
	private static $default_ttl = 3600;

	/**
	 * Initialize the cache manager.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		self::$default_ttl = (int) get_site_option( 'cr_gamification_cache_ttl', 3600 );

		// Only attempt Redis connection if enabled in settings
		if ( get_site_option( 'cr_gamification_redis_enabled', 1 ) ) {
			self::connect_redis();
		}
	}

	/**
	 * Connect to Redis server.
	 *
	 * @since 1.0.0
	 */
	private static function connect_redis() {
		if ( ! class_exists( 'Redis' ) ) {
			return;
		}

		try {
			self::$redis = new Redis();

			// Try to connect to Redis
			// Adjust host/port based on your Redis configuration
			$host = defined( 'WP_REDIS_HOST' ) ? WP_REDIS_HOST : '127.0.0.1';
			$port = defined( 'WP_REDIS_PORT' ) ? WP_REDIS_PORT : 6379;

			$connected = self::$redis->connect( $host, $port, 1 );

			if ( $connected ) {
				// Set password if defined
				if ( defined( 'WP_REDIS_PASSWORD' ) && WP_REDIS_PASSWORD ) {
					self::$redis->auth( WP_REDIS_PASSWORD );
				}

				// Set database if defined
				if ( defined( 'WP_REDIS_DATABASE' ) ) {
					self::$redis->select( WP_REDIS_DATABASE );
				}

				self::$redis_available = true;
			}
		} catch ( Exception $e ) {
			self::$redis_available = false;
			error_log( 'CR Gamification: Redis connection failed - ' . $e->getMessage() );
		}
	}

	/**
	 * Get cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached value or false if not found.
	 */
	public static function get( $key ) {
		$prefixed_key = self::prefix_key( $key );

		if ( self::$redis_available ) {
			try {
				$value = self::$redis->get( $prefixed_key );
				return $value !== false ? maybe_unserialize( $value ) : false;
			} catch ( Exception $e ) {
				error_log( 'CR Gamification: Redis get failed - ' . $e->getMessage() );
			}
		}

		// Fallback to transients
		return get_site_transient( $prefixed_key );
	}

	/**
	 * Set cached value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl Time to live in seconds (default: 1 hour).
	 * @return bool True on success, false on failure.
	 */
	public static function set( $key, $value, $ttl = null ) {
		if ( is_null( $ttl ) ) {
			$ttl = self::$default_ttl;
		}

		$prefixed_key = self::prefix_key( $key );
		$serialized   = maybe_serialize( $value );

		if ( self::$redis_available ) {
			try {
				return self::$redis->setex( $prefixed_key, $ttl, $serialized );
			} catch ( Exception $e ) {
				error_log( 'CR Gamification: Redis set failed - ' . $e->getMessage() );
			}
		}

		// Fallback to transients
		return set_site_transient( $prefixed_key, $value, $ttl );
	}

	/**
	 * Delete cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $key ) {
		$prefixed_key = self::prefix_key( $key );

		if ( self::$redis_available ) {
			try {
				return (bool) self::$redis->del( $prefixed_key );
			} catch ( Exception $e ) {
				error_log( 'CR Gamification: Redis delete failed - ' . $e->getMessage() );
			}
		}

		// Fallback to transients
		return delete_site_transient( $prefixed_key );
	}

	/**
	 * Delete multiple cached values by pattern.
	 *
	 * @param string $pattern Pattern to match keys (e.g., 'user_badges:*').
	 * @return int Number of keys deleted.
	 */
	public static function delete_pattern( $pattern ) {
		$count        = 0;
		$prefixed_key = self::prefix_key( $pattern );

		if ( self::$redis_available ) {
			try {
				$keys = self::$redis->keys( $prefixed_key );
				if ( ! empty( $keys ) ) {
					$count = self::$redis->del( $keys );
				}
			} catch ( Exception $e ) {
				error_log( 'CR Gamification: Redis delete pattern failed - ' . $e->getMessage() );
			}
		}

		return $count;
	}

	/**
	 * Increment a counter in cache.
	 *
	 * @param string $key Cache key.
	 * @param int    $increment Amount to increment by (default: 1).
	 * @return int|false New value or false on failure.
	 */
	public static function increment( $key, $increment = 1 ) {
		$prefixed_key = self::prefix_key( $key );

		if ( self::$redis_available ) {
			try {
				return self::$redis->incrBy( $prefixed_key, $increment );
			} catch ( Exception $e ) {
				error_log( 'CR Gamification: Redis increment failed - ' . $e->getMessage() );
			}
		}

		// Fallback to manual increment with transients
		$value = self::get( $key );
		$value = $value ? (int) $value : 0;
		$value += $increment;
		self::set( $key, $value );
		return $value;
	}

	/**
	 * Get user badge count from cache.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $network_id Network ID.
	 * @param string $category Optional category filter.
	 * @return int|false Badge count or false if not cached.
	 */
	public static function get_user_badge_count( $user_id, $network_id, $category = '' ) {
		$key = "user_badges:{$user_id}:{$network_id}";
		if ( $category ) {
			$key .= ":{$category}";
		}
		return self::get( $key );
	}

	/**
	 * Set user badge count in cache.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $network_id Network ID.
	 * @param int    $count Badge count.
	 * @param string $category Optional category filter.
	 * @return bool True on success, false on failure.
	 */
	public static function set_user_badge_count( $user_id, $network_id, $count, $category = '' ) {
		$key = "user_badges:{$user_id}:{$network_id}";
		if ( $category ) {
			$key .= ":{$category}";
		}
		return self::set( $key, $count );
	}

	/**
	 * Invalidate user badge cache.
	 *
	 * @param int $user_id User ID.
	 * @param int $network_id Network ID.
	 * @return int Number of keys deleted.
	 */
	public static function invalidate_user_badges( $user_id, $network_id ) {
		return self::delete_pattern( "user_badges:{$user_id}:{$network_id}:*" );
	}

	/**
	 * Get leaderboard from cache.
	 *
	 * @param string $category Category name.
	 * @param int    $network_id Network ID.
	 * @param int    $limit Number of users to retrieve.
	 * @return array|false Leaderboard data or false if not cached.
	 */
	public static function get_leaderboard( $category, $network_id, $limit = 10 ) {
		$key = "leaderboard:{$category}:{$network_id}:{$limit}";
		return self::get( $key );
	}

	/**
	 * Set leaderboard in cache.
	 *
	 * @param string $category Category name.
	 * @param int    $network_id Network ID.
	 * @param array  $data Leaderboard data.
	 * @param int    $limit Number of users.
	 * @return bool True on success, false on failure.
	 */
	public static function set_leaderboard( $category, $network_id, $data, $limit = 10 ) {
		$key = "leaderboard:{$category}:{$network_id}:{$limit}";
		return self::set( $key, $data, 300 ); // 5 minutes TTL for leaderboards
	}

	/**
	 * Get recently unlocked badges from cache.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Number of badges to retrieve.
	 * @return array|false Recent badges or false if not cached.
	 */
	public static function get_recent_unlocks( $user_id, $limit = 5 ) {
		$key = "recent_unlocks:{$user_id}:{$limit}";
		return self::get( $key );
	}

	/**
	 * Set recently unlocked badges in cache.
	 *
	 * @param int   $user_id User ID.
	 * @param array $badges Recently unlocked badges.
	 * @param int   $limit Number of badges.
	 * @return bool True on success, false on failure.
	 */
	public static function set_recent_unlocks( $user_id, $badges, $limit = 5 ) {
		$key = "recent_unlocks:{$user_id}:{$limit}";
		return self::set( $key, $badges, 600 ); // 10 minutes TTL
	}

	/**
	 * Prefix cache key with plugin identifier.
	 *
	 * @param string $key Original key.
	 * @return string Prefixed key.
	 */
	private static function prefix_key( $key ) {
		return 'cr_gam_' . $key;
	}

	/**
	 * Check if Redis is available.
	 *
	 * @return bool True if Redis is available, false otherwise.
	 */
	public static function is_redis_available() {
		return self::$redis_available;
	}

	/**
	 * Flush all gamification caches.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function flush_all() {
		if ( self::$redis_available ) {
			try {
				$keys = self::$redis->keys( 'cr_gam_*' );
				if ( ! empty( $keys ) ) {
					self::$redis->del( $keys );
				}
				return true;
			} catch ( Exception $e ) {
				error_log( 'CR Gamification: Redis flush failed - ' . $e->getMessage() );
			}
		}

		// For transients, we can't easily flush all, so return true
		return true;
	}
}
