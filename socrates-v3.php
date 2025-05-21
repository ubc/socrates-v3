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
 * Plugin URI:        https://github.com/ubc/socrates-v3/
 * Description:       v3 of the Socratic Method plugin which provides AI integration
 * Version:           3.0.1
 * Requires at least: 5.2
 * Requires PHP:      8.1
 * Author:            Rich Tape
 * Author URI:        https://ctlt.ubc.ca/
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

use DonatelloZa\RakePlus\RakePlus;

// Register a constant that we can use as the base bath for this plugin.
define( 'UBC_SELFSOCV3_DIR', plugin_dir_path( __FILE__ ) );

// Now register one to be usable as a web-addressable URL.
define( 'UBC_SELFSOCV3_URL', plugin_dir_url( __FILE__ ) );

require_once UBC_SELFSOCV3_DIR . 'inc/public/class-socrates-helpers.php';
require_once UBC_SELFSOCV3_DIR . 'inc/admin/class-socrates-cron.php';

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( '\UBC\CTLT\Socrates_Cron', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\UBC\CTLT\Socrates_Cron', 'deactivate' ) );

// Hook for updating cron schedule when option is changed.
add_action( 'update_option_socratic_link_collection_cadence', array( '\UBC\CTLT\Socrates_Helpers', 'socrates_update_feed_schedule' ), 10, 3 );

class SocratesV3 {


	var $socrates_participant_chat = null;

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

		// AJAX handler to fetch feeds.
		add_action( 'wp_ajax_fetch_feeds', array( $this, 'wp_ajax_fetch_feeds__fetch_feeds_loader' ) );

		// AJAX Handler to create NOTW Post.
		add_action( 'wp_ajax_create_notw_post', array( $this, 'wp_ajax_create_notw_post__create_notw_post' ) );

		// AJAX Handler for the RSS Feeds Finder (Tool Test).
		add_action( 'wp_ajax_find_rss_feeds', array( $this, 'wp_ajax_find_rss_feeds__find_rss_feeds' ) );

		// Register the block and participant class.
		add_action( 'after_setup_theme', array( $this, 'after_setup_theme__register_participant_chat_block' ) );

		// Enqueue our scripts and styles for the front-end chat.
		add_action( 'wp_enqueue_scripts', array( $this, 'socrates_enqueue_front_end_chat_scripts' ) );

		// Handle the AJAX request for the front-end chat.
		add_action( 'wp_ajax_handle_socrates_ajax', array( $this, 'socrates_handle_chat_ajax' ) );
		add_action( 'wp_ajax_nopriv_handle_socrates_ajax', array( $this, 'socrates_handle_chat_ajax' ) );

		// Handle the AJAX for deleting a chat.
		add_action( 'wp_ajax_socrates_delete_chat', array( $this, 'socrates_delete_chat' ) );
		add_action( 'wp_ajax_nopriv_socrates_delete_chat', array( $this, 'socrates_delete_chat' ) );

		// Log the models available to us from ChatGPT.
		add_action( 'admin_init', array( $this, 'ubc_ctlt_socrates_quick_check' ) );

		// Add custom cron schedule.
		add_filter( 'cron_schedules', array( '\UBC\CTLT\Socrates_Helpers', 'socrates_add_cron_schedules' ) );

		// Hook for updating cron schedule when option is changed.
		add_action( 'update_option_socratic_link_collection_cadence', array( '\UBC\CTLT\Socrates_Helpers', 'socrates_update_feed_schedule' ), 10, 3 );

		// Add the cron action hook.
		add_action( 'socrates_fetch_feeds', array( '\UBC\CTLT\Socrates_Helpers', 'socrates_cron_fetch_feeds' ) );
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

		// Include the admin list view file.
		require_once plugin_dir_path( __FILE__ ) . 'inc/admin/class-socrates-admin-list-view.php';

		// Initialize admin list view class.
		$socrates_admin_list_view = new \UBC\CTLT\Socrates_Admin_List_View();
	}//end init__admin_setup()


	/**
	 * Loads our class which handles the fetching of the RSS Feeds.
	 * Hooked into wp_ajax_fetch_feeds
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function wp_ajax_fetch_feeds__fetch_feeds_loader() {

		// Check for nonce security.
		if ( ! check_ajax_referer( 'socrates_nonce', 'nonce', false ) ) {
			echo 'Nonce check failed!';
			wp_die();
		}

		// Fetch the feeds and categorize them.
		$categorized_and_scored_links = \UBC\CTLT\Socrates_Helpers::fetch_feeds();

		// Now we need to use this to create bookmarks.
		$created_bookmarks = \UBC\CTLT\Socrates_Helpers::create_bookmarks( $categorized_and_scored_links );

		echo $created_bookmarks . ' link(s) fetched.';

		wp_die();
	}//end wp_ajax_fetch_feeds__fetch_feeds_loader()


	/**
	 * AJAX Handler for the NOTW post creation
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function wp_ajax_create_notw_post__create_notw_post() {

		// Check for nonce security.
		if ( ! check_ajax_referer( 'socrates_nonce', 'nonce', false ) ) {
			echo 'Nonce check failed!';
			wp_die();
		}

		$newly_created_post_id = \UBC\CTLT\Socrates_Helpers::create_notw_post_from_unpublished_links();

		wp_die();
	}//end wp_ajax_create_notw_post__create_notw_post()

	/**
	 * AJAX Handler for the RSS Feeds Finder (Tool Test).
	 *
	 * @return void
	 */
	public function wp_ajax_find_rss_feeds__find_rss_feeds() {

		// Check for nonce security.
		if ( ! check_ajax_referer( 'socrates_nonce', 'nonce', false ) ) {
			echo 'Nonce check failed!';
			wp_die();
		}

		$saved_feeds = get_option( 'socratic_feeds' );

		if ( empty( $saved_feeds ) || ! is_array( $saved_feeds ) ) {
			echo wp_kses_post( 'No feeds found.' );
			wp_die();
		}

		foreach ( $saved_feeds as $feed ) {
			$feed_url = esc_url_raw( $feed['url'] );

		}

		wp_die();
	}//end wp_ajax_find_rss_feeds__find_rss_feeds()

	/**
	 * Register the block and participant class.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function after_setup_theme__register_participant_chat_block() {

		require_once plugin_dir_path( __FILE__ ) . 'inc/public/class-socrates-participant-chat.php';

		$socrates_participant_chat = new \UBC\CTLT\Socrates_Participant_Chat();

		$this->socrates_participant_chat = $socrates_participant_chat;
	}//end after_setup_theme__register_participant_chat_block()


	/**
	 * Enqueue our scripts and styles.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function socrates_enqueue_front_end_chat_scripts() {

		wp_enqueue_script( 'socrates-ajax-script', plugin_dir_url( __FILE__ ) . 'inc/public/js/socrates-ajax.js', array( 'jquery' ), '1.0.0', true );

		wp_localize_script(
			'socrates-ajax-script',
			'socratesAjax',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'socrates_nonce' ),
				'thinkingText' => __( 'Thinking...', 'socrates-v3' ),
			)
		);
	}//end socrates_enqueue_front_end_chat_scripts()


	/**
	 * AJAX handler for the Conversation.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function socrates_handle_chat_ajax() {

		// Check nonce for security.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'socrates_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'E00: Nonce verification failed. Please reload the page and try again. You may need to sign in again, if applicable.' ) );
		}

		// Sanitize input.
		$reply = wp_kses_post( $_POST['reply'] );

		// If reply fails validation, or is now empty, return an error.
		if ( empty( $reply ) ) {
			wp_send_json_error( array( 'message' => 'E01. Reply failed validation or is empty. Please try again.' ) );
		}

		// ChatID is sent via the AJAX request.
		$chat_id = sanitize_text_field( $_POST['chatID'] );

		if ( ! $chat_id ) {
			wp_send_json_error( array( 'message' => 'E02. Chat ID is empty. Please reload and try again.' ) );
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'E03. Log in required. Please reload, sign in, and try again.' ) );
		}

		// Fetch the current messages from User Meta, it's OK if it's empty.
		$chats = get_user_meta( $user_id, 'socratic_chats', true );

		// Set up the empty state.
		if ( ! $chats ) {
			$chats             = array();
			$chats[ $chat_id ] = \UBC\CTLT\Socrates_Helpers::socrates_generate_starting_meta_state( $chat_id );
		}

		// If we're told this is a new chat, and it doesn't exist for this user, create it.
		$is_new_chat = ( array_key_exists( 'isNewChat', $_POST ) && absint( $_POST['isNewChat'] ) === 1 ) ? true : false;

		if ( $is_new_chat && ! isset( $chats[ $chat_id ] ) ) {
			$chats[ $chat_id ] = \UBC\CTLT\Socrates_Helpers::socrates_generate_starting_meta_state( $chat_id );
		}

		$this_chat = $chats[ $chat_id ];
		$messages  = $this_chat['messages'];

		// The reply will be added with a role of 'user' as that's who has done it.
		$current_reply = array(
			'role'    => 'user',
			'content' => $reply,
		);

		// Add the current reply to the existing messages.
		$messages[] = $current_reply;

		// Add this to the conversation; send to LLM, Add to User Meta.
		require_once plugin_dir_path( __FILE__ ) . 'inc/admin/class-socrates-send-llm-request.php';

		// Send the prompt to the LLM.
		$request = new Socrates_Send_LLM_Request();
		$request->set_prompt( $messages );
		$request->send_request();

		// This is the response from the LLM, now potentially an array with reasoning.
		$llm_response_data = $request->get_llm_response_data( false );

		// Check for errors returned by the LLM request/parsing process.
		if ( is_string( $llm_response_data ) && strpos( $llm_response_data, 'Error: ' ) === 0 ) {
			wp_send_json_error( array( 'message' => $llm_response_data ) );
		}

		// Extract reasoning and main response parts.
		$reasoning         = isset( $llm_response_data['reasoning'] ) ? $llm_response_data['reasoning'] : null;
		$main_response_raw = isset( $llm_response_data['response'] ) ? $llm_response_data['response'] : '';

		// Sanitize the response parts.
		$llm_response_string = wp_kses_post( $main_response_raw );
		$sanitized_reasoning = $reasoning ? wp_kses_post( $reasoning ) : null;

		// If the main response is empty after validation, return an error.
		if ( empty( $llm_response_string ) ) {
			wp_send_json_error( array( 'message' => 'E05. Response is empty after processing. Please reload and try again.' ) );
		}

		// If this is the user's 1st actual reply, we need to set it as the summary.
		// The 1st and 2nd messages are the initial prompt and the first reply.
		if ( count( $chats[ $chat_id ]['messages'] ) === 2 ) {
			$chats[ $chat_id ]['summary'] = \UBC\CTLT\Socrates_Helpers::limit_text( $reply, 5 );
		}

		// We need to use some methods from the wpdb class.
		global $wpdb;

		// Prepare the assistant's response for saving (only main content).
		$llm_response_to_save = array(
			'role'    => 'assistant',
			'content' => $llm_response_string,
		);

		$messages[] = $llm_response_to_save;

		$chats[ $chat_id ]['messages'] = $messages;

		// Get relevant links based on the main response string.
		$links_results  = \UBC\CTLT\Socrates_Helpers::get_links_for_response( $llm_response_string );
		$links_for_html = $links_results['links_for_html'];
		$link_ids       = $links_results['link_ids'];

		// Store the IDs of the links shown in this turn.
		$this_chat_links   = isset( $chats[ $chat_id ]['links_shown'] ) && is_array( $chats[ $chat_id ]['links_shown'] ) ? $chats[ $chat_id ]['links_shown'] : array();
		$this_chat_links[] = $link_ids;

		$chats[ $chat_id ]['links_shown'] = $this_chat_links;

		// Save the updated chat history (including user message, assistant message, links shown) to user meta.
		$user_meta_updated = update_user_meta( $user_id, 'socratic_chats', $chats );

		if ( ! $user_meta_updated ) {
			// Attempt to revert the messages array if saving failed.
			array_pop( $messages ); // Remove assistant message.
			array_pop( $messages ); // Remove user message.
			$chats[ $chat_id ]['messages'] = $messages;
			array_pop( $this_chat_links ); // Remove last link set.
			$chats[ $chat_id ]['links_shown'] = $this_chat_links;
			wp_send_json_error( array( 'message' => 'E06. Failed to save this conversation turn. Please reload and try again.' ) );
		}

		// Check the setting for showing reasoning.
		$show_reasoning = (bool) get_option( 'socratic_show_reasoning', false );
		// Sanitize reasoning if it exists
		$sanitized_reasoning = isset( $reasoning ) ? wp_kses_post( $reasoning ) : null;

		// Debug: Log what we're about to send in the JSON response
		file_put_contents(
			WP_CONTENT_DIR . '/debug.log',
			print_r(
				array(
					'Socrates JSON Response' => array(
						'message'        => $llm_response_string,
						'links'          => $links_for_html,
						'show_reasoning' => $show_reasoning,
						'reasoning'      => ( $show_reasoning && ! empty( $sanitized_reasoning ) ) ? $sanitized_reasoning : null,
					),
				),
				true
			),
			FILE_APPEND
		);

		// Send the successful response back to the front-end.
		wp_send_json_success(
			array(
				'message'   => $llm_response_string,
				'links'     => $links_for_html,
				'reasoning' => ( $show_reasoning && ! empty( $sanitized_reasoning ) ) ? $sanitized_reasoning : null,
			)
		);
	}//end socrates_handle_chat_ajax()


	/**
	 * Handle the AJAX request for deleting a chat.
	 * Checks a user is logged in. If not, returns a JSON error.
	 * Ensures the passed chat ID is valid and for the current user. If not, returns a JSON error.
	 * Marks the chat as deleted.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function socrates_delete_chat() {

		// Check nonce for security.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'socrates_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'E00: Nonce verification failed. Please reload the page and try deleting again. You may need to sign in again, if applicable.' ) );
		}

		// Sanitize the chatID using our custom helper method.
		$chat_id = \UBC\CTLT\Socrates_Helpers::socrates_sanitize_chat_id( $_POST['chatID'] );

		// If reply fails validation, or is now empty, return an error.
		if ( empty( $chat_id ) ) {
			wp_send_json_error( array( 'message' => 'E01. chatID failed validation or is empty. Please reload and try deleting again.' ) );
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'E03. Log in required. Please reload, sign in, and try deleting again.' ) );
		}

		// Fetch the current messages from User Meta, it's OK if it's empty.
		$chats = get_user_meta( $user_id, 'socratic_chats', true );

		// If the passed chat ID isn't in the user's meta, return an error.
		if ( ! isset( $chats[ $chat_id ] ) ) {
			wp_send_json_error( array( 'message' => 'E04. chatID not found for your user account. Cannot delete. Please try again.' ) );
		}

		// If the chat is already deleted, return an error.
		if ( $chats[ $chat_id ]['deleted'] ) {
			wp_send_json_error( array( 'message' => 'E05. chatID already deleted.' ) );
		}

		// Mark the chat as deleted which sets the deleted property to be the current datetime.
		$chats[ $chat_id ]['deleted'] = current_time( 'mysql' );

		// Save the updated user meta.
		$user_meta_updated = update_user_meta( $user_id, 'socratic_chats', $chats );

		if ( ! $user_meta_updated ) {
			wp_send_json_error( array( 'message' => 'E06. Failed to delete this conversation. Please reload and try again.' ) );
		}

		wp_send_json_success( array( 'message' => 'Chat deleted.' ) );
	}//end socrates_delete_chat()


	/**
	 * Checks for a specific GET parameter in the admin dashboard and runs a method if conditions are met.
	 *
	 * This function hooks into the 'admin_init' action, which fires after WordPress has finished loading
	 * but before any headers are sent.
	 *
	 * The function first checks if the current user has the 'manage_options' capability, ensuring that
	 * only administrators can execute the function.
	 *
	 * It then checks if the 'llm-quick-check' GET parameter is present. If the
	 * conditions are met, it calls a specific method from a class within the plugin.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/admin_init/
	 * @see https://developer.wordpress.org/reference/functions/current_user_can/
	 */
	public function ubc_ctlt_socrates_quick_check() {

		// Check for the current user's capabilities to ensure they have admin privileges.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Log a list of the available ChatGPT models. ?llm-quick-check=models.
		if ( isset( $_GET['llm-quick-check'] ) && sanitize_text_field( $_GET['llm-quick-check'] ) === 'models' ) {
			\UBC\CTLT\Socrates_Helpers::socrates_get_chatgpt_models();
		}

		// Log a list of the available ChatGPT models. ?llm-quick-check=audio.
		if ( isset( $_GET['llm-quick-check'] ) && sanitize_text_field( $_GET['llm-quick-check'] ) === 'audio' ) {
			\UBC\CTLT\Socrates_Helpers::socrates_get_audio_of_chat();
		}

		// Log an image from ChatGPT. ?llm-quick-check=image.
		if ( isset( $_GET['llm-quick-check'] ) && sanitize_text_field( $_GET['llm-quick-check'] ) === 'image' ) {

			$image_prompt = 'First, summarize this conversation between Socrates the greek philsopher and a law student.
			Then, with that description, generate a prompt for an image-generation GenerativeAI tool, designed to create an image
			which represents the conversation.
			The image style should reflect these descriptors (which you may also summarize):
			- Computer-generated imagery (CGI) with a vibrant, colorful aesthetic.
			- Animated characters featuring exaggerated expressions and proportions for enhanced expressiveness.
			- Smooth and rounded 3D character designs with a glossy finish.
			- Environments that are richly textured and detailed, often with a whimsical twist.
			- A family-friendly and approachable look that appeals to both children and adults.
			- Animation style that is often characterized by fluid motion and lively, dynamic sequences.
			- A visual tone that balances realism with cartoon-like stylization.
			The prompt should comply with OpenAIs safety system and be less than 1000 characters in length. The prompt must ensure that the
			image includes a repsentation of the Greek philosopher Socrates and the law student. The image should include represenations
			of the key elements of the conversation. The prompt itself shouldn\'t include the conversation, but it should describe the elements
			needed in the image which represent the conversation.
			There should be no words in the image. Think through this step-by-step. This is important to my career.
			```text
			Socrates: Name a digital world issue that interests you in 5 words or under.
			Student: Loot boxes in video games.
			Socrates: Thinking about loot boxes in video games, how do you think the current legal framework surrounding consumer protection and gambling applies to this issue? Consider aspects such as age restrictions, transparency, and the potential for addiction.
			Student: It\'s clear that current protections have failed. Children are exposed to gambling within video games. This needs to be legislated against.
			Socrates: If legislation were to be implemented to address the issue of loot boxes in video games, what specific regulations do you think should be in place to protect consumers, particularly children, from the potential harms associated with these mechanics? Consider factors such as age verification, disclosure requirements, and possible consequences for non-compliance.
			Student: Classifying loot boxes in video games as gambling will reduce the propensity for game developers to implement that mechanic in their games.
			```
			Do not output an introduction, prefix, preface, or conclusion. Only output the image prompt that you generate and nothing else.';
			\UBC\CTLT\Socrates_Helpers::socrates_get_image_from_prompt( $image_prompt );
		}
	}//end ubc_ctlt_socrates_quick_check()
}//end class

// Instantiate ourselves as early as possible.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\plugins_loaded__instantiate_socrates_v3' );

function plugins_loaded__instantiate_socrates_v3() {

	global $ctlt_socrates;
	$ctlt_socrates = new SocratesV3();

	do_action( 'socrates_v3_loaded' );
}//end plugins_loaded__instantiate_socrates_v3()
