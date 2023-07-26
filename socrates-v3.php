<?php

/**
 * Socrates v3
 *
 * @package           SocratesV3
 * @author            Rich Tape
 * @copyright         Rich Tape, Jon Festinger
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Socrates v3
 * Plugin URI:        #
 * Description:       v3 of the Socratic Method plugin which provides AI integration
 * Version:           3.0.1
 * Requires at least: 5.2
 * Requires PHP:      8.1
 * Author:            Rich Tape
 * Author URI:        #
 * Text Domain:       socrates-v3
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace UBC\CTLT;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';


class SocratesV3 {

	/**
	 * Initialize ourselves.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function __construct() {

		// We need to enable the link manager.
		add_filter( 'pre_option_link_manager_enabled', '__return_true' );

		// Initialize everything for the admin.
		add_action( 'init', array( $this, 'init__admin_setup' ), 1 );

		// AJAX handler to fetch feeds
		add_action( 'wp_ajax_fetch_feeds', array( $this, 'wp_ajax_fetch_feeds__fetch_feeds_loader' ) );

	}//end __construct()


	/**
	 * Set up our requirements within the admin area.
	 *
	 * @return void
	 * @since
	 */
	public function init__admin_setup() {

		if ( ! is_admin() ) {
			return;
		}

		// Include the settings class file.
		require_once plugin_dir_path( __FILE__ ) . 'inc/admin/class-socrates-settings.php';

		// Initialize settings class.
		$socrates_settings = new \UBC\CTLT\Socrates_Settings();

	}//end init__admin_setup()


	/**
	 * Loads our class which handles the fetching of the RSS Feeds
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function wp_ajax_fetch_feeds__fetch_feeds_loader() {

		// Check for nonce security
		if ( ! check_ajax_referer( 'socrates_nonce', 'nonce', false ) ) {
			echo 'Nonce check failed!';
			wp_die();
		}

		// Include the feed fetcher class file.
		require_once plugin_dir_path( __FILE__ ) . 'inc/admin/class-socrates-fetch-feeds.php';

		// Initialize feed fetcher class.
		$socrates_feeds = new \UBC\CTLT\Socrates_Fetch_Feeds();

		$categorized_and_scored_links = $socrates_feeds->output();

		// Now we need to use this to create bookmarks.
		require_once plugin_dir_path( __FILE__ ) . 'inc/admin/class-socrates-bookmark-creator.php';

		$bookmark_class = new Socrates_Bookmark_Creator( $categorized_and_scored_links );

		wp_die();

	}//end wp_ajax_fetch_feeds__fetch_feeds_loader()

}

global $ctlt_socrates;
$ctlt_socrates = new SocratesV3();
