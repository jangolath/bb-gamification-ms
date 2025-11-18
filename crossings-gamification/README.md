# Crossings Gamification Plugin

A comprehensive gamification and achievement system for WordPress multisite networks with BuddyBoss integration.

## Version 1.0.0

## Overview

The Crossings Gamification Plugin tracks user achievements across social interactions, learning, commerce, and events, awarding badges and displaying them on user profiles.

## Features

### Core Functionality
- **Network-wide badge system** with customizable achievements
- **Cross-site event tracking** via event queue system
- **Redis caching** for optimal performance
- **Async processing** for badge calculations
- **BuddyBoss profile integration** with dedicated badges tab
- **Activity feed integration** for badge unlock announcements

### Badge Types (V1)

#### Social (BuddyBoss)
- Friends: 5, 10, 25, 50, 100, 250, 500, 750, 1000
- Followers: 5, 10, 25, 50, 100, 250, 500, 750, 1000
- Groups joined: 1, 5, 10, 25, 50
- Groups created: 1, 5, 10
- Activity posts: 1, 5, 10, 25, 50, 100

#### Learning (TutorLMS)
- Courses completed: 1, 5, 10, 25
- Individual course completion awards (auto-generated)

#### Commerce (Dokan/WooCommerce)
- First purchase badge
- Vendor-specific badges (Bronze/Silver/Gold tiers)
- Per-vendor purchase tracking

#### Events (The Events Calendar)
- Event attendance: 1, 5, 10, 25
- Individual event attendance awards (auto-generated)
- Annual event multi-year attendance: 2, 3, 5, 10 years

## Requirements

- WordPress Multisite 5.8+
- PHP 7.4+
- **Required Plugins:**
  - BuddyBoss Platform (or BuddyPress)
- **Optional Plugins:**
  - TutorLMS (for learning badges)
  - Dokan (for vendor-specific commerce badges)
  - WooCommerce (for commerce badges)
  - The Events Calendar + Event Tickets (for event badges)
- **Recommended:**
  - Redis server (for caching)
  - Bunny.net CDN account (for badge media hosting)

## Installation

1. Upload the `crossings-gamification` folder to `/wp-content/plugins/`
2. **Network Activate** the plugin from Network Admin → Plugins
3. Configure settings at Network Admin → Gamification → Settings
4. Create and manage badges at Network Admin → Gamification → Badges

## Database Schema

The plugin creates the following network-wide tables:
- `cr_achievements` - Badge/award definitions
- `cr_user_achievements` - User progress tracking
- `cr_achievement_history` - Achievement timeline
- `cr_event_queue` - Cross-site event queue
- `cr_vendor_progress` - Vendor-specific metrics
- `cr_content_metrics` - Content engagement tracking
- `cr_user_awards` - Course/event/product awards

## Configuration

### Network Admin Interface

Access at **Network Admin → Gamification**:

1. **Dashboard** - Statistics and system status
2. **Badges** - Create and manage badges
3. **Awards** - View auto-generated awards
4. **Users** - View progress, manual grant/revoke
5. **Events & Triggers** - Monitor event queue
6. **Settings** - Configure plugin options

### Creating Badges

1. Navigate to **Network Admin → Gamification → Badges → Add New**
2. Configure:
   - **Name** and **Description**
   - **Category** (social, learning, commerce, events, etc.)
   - **Trigger Event** (from registered events)
   - **Threshold** (number required to unlock)
   - **Media URL** (Bunny.net CDN URL for SVG/Lottie/image)
   - **Colors** (primary and secondary hex colors)

### Badge Media

Upload badge media (SVG, Lottie animations, images) to your Bunny.net CDN and paste the URLs into the badge configuration.

**Supported formats:**
- SVG images
- Lottie animations (JSON)
- PNG/JPG images

## Integration Details

### BuddyBoss
Tracks:
- Friend connections (`friends_friendship_accepted`)
- Follower gains (`bp_start_following`)
- Group membership (`groups_join_group`)
- Group creation (`groups_create_group`)
- Activity posts (`bp_activity_posted_update`)

### TutorLMS
Tracks:
- Course completions (`tutor_course_complete_after`)
- Auto-creates individual course completion awards

### Dokan/WooCommerce
Tracks:
- Product purchases (`woocommerce_order_status_completed`)
- Vendor-specific purchases with tiered badges
- Per-vendor metrics: purchase count, total spent, orders over X

### The Events Calendar
Tracks:
- Event attendance (`event_tickets_woocommerce_ticket_created`)
- Annual event attendance for recurring events
- Multi-year consecutive attendance badges

## Performance Optimization

### Redis Caching
Configure Redis connection in `wp-config.php`:
```php
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_PASSWORD', 'your-password'); // Optional
define('WP_REDIS_DATABASE', 0); // Optional
```

Cache keys:
- `cr_user_badges:{user_id}:{network_id}` - User badge counts
- `cr_leaderboard:{category}:{network_id}` - Leaderboards
- `cr_recent_unlocks:{user_id}` - Recently unlocked badges

### Event Queue Processing

Events are queued and processed via WP-Cron:
- **Cron job**: `cr_process_event_queue` (runs every minute)
- **Batch size**: Configurable in settings (default: 50 events)

For better performance, configure server-side cron in `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

Then add to your server cron:
```bash
*/1 * * * * wget -q -O - https://your-site.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

## Hooks and Filters

### Actions

```php
// Fires when an achievement is unlocked
do_action('cr_achievement_unlocked', $user_id, $achievement_id, $action_type);

// Fires after achievement unlock is processed
do_action('cr_after_achievement_unlocked', $user_id, $achievement);

// Fires when an award is added
do_action('cr_award_added', $award_id, $user_id, $award_type);

// Allow custom event registration
do_action('cr_gamification_register_events');
```

### Filters

```php
// Filter achievement criteria matching
apply_filters('cr_achievement_matches_criteria', $matches, $achievement, $event_data);

// Filter custom threshold checks
apply_filters('cr_achievement_custom_threshold_check', $should_unlock, $user_id, $achievement, $event_data);

// Custom recalculation logic
apply_filters('cr_recalculate_user_achievements', $unlocked, $user_id, $trigger_type);
```

## Extensibility

### Registering Custom Events

```php
add_action('cr_gamification_register_events', function() {
    CR_Gamification_Event_Registry::register([
        'key' => 'custom_event',
        'label' => 'Custom Event',
        'category' => 'custom',
        'hook' => 'my_custom_hook',
        'supports_threshold' => true,
        'description' => 'Custom event description',
    ]);
});
```

### Triggering Events

```php
// Queue an event for processing
CR_Gamification_Event_Bus::queue_event(
    $user_id,
    'custom_event',
    ['custom_data' => 'value']
);

// Or trigger immediately
CR_Gamification_Event_Bus::trigger_immediate(
    $user_id,
    'custom_event',
    ['custom_data' => 'value']
);
```

## Development

### File Structure
```
crossings-gamification/
├── crossings-gamification.php (main file)
├── uninstall.php
├── includes/
│   ├── class-activator.php
│   ├── class-cache-manager.php
│   ├── class-event-registry.php
│   ├── class-event-bus.php
│   ├── class-achievement-engine.php
│   ├── class-user-achievements.php
│   └── integrations/
├── admin/
│   ├── class-admin-controller.php
│   ├── class-network-admin-pages.php
│   ├── class-badge-manager.php
│   └── views/
├── public/
│   ├── class-profile-display.php
│   ├── class-activity-feed.php
│   └── templates/
└── assets/
    ├── css/
    └── js/
```

## Support

For issues and feature requests, contact the development team.

## License

GPL v2 or later

## Credits

Developed for Crossings community platform
