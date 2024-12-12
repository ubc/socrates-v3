<?php
namespace UBC\CTLT;

class Socrates_Cron {
	/**
	 * Validate and sanitize the day name
	 *
	 * @param string $day The day name to validate.
	 * @return string Valid day name, defaults to 'Sunday' if invalid
	 */
	private static function validate_day_name( $day ) {
		$valid_days = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
		return in_array( $day, $valid_days ) ? $day : 'Sunday';
	}

	/**
	 * Schedule the NOTW post creation
	 */
	public static function schedule_notw_post_creation() {
		$day = self::validate_day_name( get_option( 'socratic_notw_day', 'Sunday' ) );

		// Clear any existing schedule.
		wp_clear_scheduled_hook( 'socrates_create_notw_post' );

		// Schedule for midnight on the next occurrence of the specified day.
		$timestamp = strtotime( "next $day midnight" );
		wp_schedule_event( $timestamp, 'weekly', 'socrates_create_notw_post' );
	}

	/**
	 * Handle the creation of NOTW post via cron
	 */
	public static function handle_notw_post_creation() {
		try {
			// Create the NOTW post using existing functionality.
			$newly_created_post_id = Socrates_Helpers::create_notw_post_from_unpublished_links();

		} catch ( Exception $e ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Error in NOTW post creation: ' . $e->getMessage() ), true ), FILE_APPEND ); // phpcs:ignore
		}
	}

	/**
	 * Handle manual feed fetch (existing functionality)
	 */
	public static function handle_manual_feed_fetch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'socrates_manual_feed_fetch' );
		Socrates_Helpers::socrates_cron_fetch_feeds();
		wp_redirect( add_query_arg( 'feed-fetched', 'true', wp_get_referer() ) );
		exit;
	}

	/**
	 * Plugin activation handler
	 */
	public static function activate() {
		self::schedule_notw_post_creation();
		Socrates_Helpers::socrates_schedule_feed_fetch(); // existing functionality
	}

	/**
	 * Plugin deactivation handler
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'socrates_create_notw_post' );
		wp_clear_scheduled_hook( 'socrates_fetch_feeds' ); // existing functionality
	}

	/**
	 * Handle changes to the NOTW day setting.
	 */
	public static function update_notw_schedule( $old_value, $new_value, $option ) {
		if ( $old_value !== $new_value ) {
			self::schedule_notw_post_creation();
		}
	}
}

// Register hooks
add_action( 'admin_post_socrates_manual_feed_fetch', array( 'UBC\CTLT\Socrates_Cron', 'handle_manual_feed_fetch' ) );
add_action( 'socrates_create_notw_post', array( 'UBC\CTLT\Socrates_Cron', 'handle_notw_post_creation' ) );
add_action( 'update_option_socratic_notw_day', array( 'UBC\CTLT\Socrates_Cron', 'update_notw_schedule' ), 10, 3 );
