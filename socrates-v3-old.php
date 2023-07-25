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
 * Version:           0.0.1
 * Requires at least: 5.2
 * Requires PHP:      8.1
 * Author:            Rich Tape
 * Author URI:        #
 * Text Domain:       socrates-v3
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

require 'vendor/autoload.php';

use \andreskrey\Readability\Readability;
use \andreskrey\Readability\Configuration;
use \andreskrey\Readability\ParseException;

add_filter( 'pre_option_link_manager_enabled', '__return_true' );

define( 'SOCRATES_OPENAI_API_KEY', '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' );
define( 'SOCRATES_OPEN_AI_CHATGPT_3_MODEL_NAME', 'gpt-3.5-turbo-0613' );
// define( 'SOCRATES_OPEN_AI_CHATGPT_3_MODEL_NAME', 'gpt-4' );
// define( 'SOCRATES_OPEN_AI_CHATGPT_3_MODEL_NAME', 'gpt-4-32k' );

function socrates_3_get_articles() {

	$rss_feeds = array(
		'https://feeds.arstechnica.com/arstechnica/index',
		'https://www.theverge.com/rss/index.xml',
		'https://gizmodo.com/rss',
		'https://lifehacker.com/rss',
	);

	if ( ! $rss_feeds || ! is_array( $rss_feeds ) || count( $rss_feeds ) < 1 ) {
		return 'Error: ' . __LINE__;
	}

	$articles  = array();

	foreach ( $rss_feeds as $rid => $rss_feed_url ) {

		$feed = fetch_feed( $rss_feed_url );

		if ( is_wp_error( $feed ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Feed Fetch Error: ', $feed->get_error_message() ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: ' . __LINE__;
		}

		$maxitems  = $feed->get_item_quantity( 2 );
		$rss_items = $feed->get_items( 0, $maxitems );

		foreach ( $rss_items as $item ) {

			$title = esc_attr( $item->get_title() );
			$link  = esc_url( $item->get_permalink() );
			$desc  = esc_attr( strip_tags( html_entity_decode( $item->get_description(), ENT_QUOTES, get_option( 'blog_charset' ) ) ) );

			$articles[] = array( 'title' => $title, 'link' => $link );

		}

	}

	return $articles;

}

function socrates_3_get_link_data() {

	$new_link_data = array();

	$articles = socrates_3_get_articles();

	foreach ( $articles as $aid => $article ) {

		$html = file_get_contents( $article['link'] );

		$readability = new Readability( new Configuration() );

		try {

			$readability->parse( $html );

			$excerpt = $readability->getExcerpt();
			$content = $readability->getContent();

			$new_link_data[] = array(
				'url'     => $article['link'],
				'title'   => $article['title'],
				'excerpt' => $excerpt,
			);

			// $bookmark_data = array(
			// 	'link_name'        => $article['title'],
			// 	'link_url'         => $article['link'],
			// 	'link_description' => $excerpt,
			// 	'link_notes'       => $content,
			// 	'link_category'    => '',
			// 	'link_rating'      => '',
			// );

			// wp_insert_link( $bookmark_data );

		} catch ( ParseException $e ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( sprintf( 'Error processing text: %s on %s', $e->getMessage(), $article['link'] ), true ), FILE_APPEND ); // phpcs:ignore;
		}

	}

	return $new_link_data;

}

function socrates_get_prompt() {

	$prompt_text = "Here are titles and excerpts for some blog posts. Rate each on a scale of 1-10 with how likely they are to discuss topics loosely connected to the video game industry, i-gaming, technology, digital media, and especially legal topics associated with any of those, with 1 being the least likely and 10 being most likely. Also give a confidence score for each of your ratings as a percentage, the more confident you are in giving the correct rating, the higher the percentage. Finally, for each, provide a category descriptor, i.e. if the post discusses video games, you should categorize the post as 'video games'. Your response should be in the format: \r\n\r\nPost W :: Score X/10 :: Confidence Y% :: Z\r\n\n\nwhere W is the number of the post, X is your score out of 10, Y is your confidence percentage, and Z is the singular category that best describes the post. Do not write anything other than this for each post. Start each on a new line.\r\n\r\n";

	$new_link_data = socrates_3_get_link_data();

	foreach ( $new_link_data as $lid => $link_data ) {

		$prompt_text .= 'post: ' . $lid+1 . "\r\n";
		$prompt_text .= 'title: ' . $link_data['title'] . "\r\n";
		$prompt_text .= 'excerpt: ' . $link_data['excerpt'] . "\r\n";
		$prompt_text .= "\r\n";

	}

	file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( $prompt_text ), true ), FILE_APPEND ); // phpcs:ignore

	return $prompt_text;

}

function socrates_3_rss_test() {

	// $articles = socrates_3_get_articles();

	// if ( ! $articles || ! is_array( $articles ) || count( $articles ) < 1 ) {
	// 	file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'No articles to parse' ), true ), FILE_APPEND ); // phpcs:ignore
	// 	return;
	// }

	$new_link_data = socrates_3_get_link_data();

	if ( count( $new_link_data ) < 1 ) {
		file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'No New Link Data' ), true ), FILE_APPEND ); // phpcs:ignore;
		return 'Error: ' . __LINE__;
	}

	$prompt_text = socrates_get_prompt();

	$open_ai_api_key              = constant( 'SOCRATES_OPENAI_API_KEY' );
	$open_ai_chatgpt_model_to_use = constant( 'SOCRATES_OPEN_AI_CHATGPT_3_MODEL_NAME' );

	$client = OpenAI::client( $open_ai_api_key );

	$api_params = array(
	    'model' => $open_ai_chatgpt_model_to_use,
	    'messages' => array(
	        array(
	            'role'    => 'user',
	            'content' => $prompt_text,
	        )
	    ),
	);

	$data = $client->chat()->create( $api_params );

	file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( $data, true ), FILE_APPEND ); // phpcs:ignore

	$link_scores = false;

	foreach ( $data->choices as $result ) {
		// Only want the last response, I THINK.
		if ( ! isset ( $result->finishReason ) || 'stop' !== $result->finishReason ) {
			continue;
		}

		$link_scores = $result->message->content;
	}

	if ( false === $link_scores ) {
		file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Link scores are false.' ), true ), FILE_APPEND ); // phpcs:ignore
		return 'Error: ' . __LINE__;
	}

	return $link_scores;

}

add_action( 'admin_menu', 'socrates_admin_menu' );

function socrates_admin_menu() {

	// Only allow administrators to access this menu item
	if ( current_user_can( 'manage_options' ) ) {
		add_options_page(
			'Socratic Method Version 3 Settings', // Page Title
			'Socrates', // Menu Title
			'manage_options', // Capability
			'socrates', // Menu Slug
			'socrates_settings_page' // Function that handles the settings page
		);
	}

}

add_action('admin_init', 'socrates_register_socrates_settings');

/**
 * Register settings
 */
function socrates_register_socrates_settings() {
    // Prompt settings
    register_setting('socrates_prompt_settings', 'starting_prompt', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));

    // Feed settings
    register_setting('socrates_feed_settings', 'feed_url', array(
        'sanitize_callback' => 'esc_url_raw'
    ));
}

function socrates_settings_page() {

	// Set the active tab
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'prompt_settings';

    ?>
    <h1>Socratic Method Version 3 Settings</h1>

    <!-- Display the tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=socrates&tab=prompt_settings" class="nav-tab <?php echo $active_tab == 'prompt_settings' ? 'nav-tab-active' : ''; ?>">Prompt Settings</a>
        <a href="?page=socrates&tab=feed_settings" class="nav-tab <?php echo $active_tab == 'feed_settings' ? 'nav-tab-active' : ''; ?>">Feed Settings</a>
    </h2>

    <?php if ($active_tab == 'prompt_settings'): ?>
        <!-- Prompt Settings tab content -->
        <form method="post" action="options.php">
            <?php
            settings_fields('socrates_prompt_settings');
            do_settings_sections('socrates_prompt_settings');
            ?>
            <label for="starting-prompt">Starting Prompt</label>
            <textarea id="starting-prompt" name="starting_prompt" rows="20" cols="50"><?php echo get_option('starting_prompt'); ?></textarea>
			<button id="fetch-data" type="button" class="button">Fetch Data</button>
			<div id="data-container"></div> <!-- This div will contain the fetched data -->
			<div id="spinner" style="display: none;">Some spinner</div>
            <?php submit_button(); ?>
        </form>
    <?php elseif ($active_tab == 'feed_settings'): ?>
        <!-- Feed Settings tab content -->
        <form method="post" action="options.php">
            <?php
            settings_fields('socrates_feed_settings');
            do_settings_sections('socrates_feed_settings');
            ?>
            <label for="feed-url">Feed URL</label>
            <input type="text" id="feed-url" name="feed_url" value="<?php echo get_option('feed_url'); ?>">
            <?php submit_button(); ?>
        </form>
    <?php endif; ?>
    <?php

}

add_action( 'admin_enqueue_scripts', 'socrates_enqueue_admin_scripts' );

function socrates_enqueue_admin_scripts() {
	wp_enqueue_script(
        'socrates-admin',
        plugins_url( '/socrates-admin.js', __FILE__ ),
        array(),
        '1.0',
        true
    );
    // Create nonce
    wp_localize_script( 'socrates-admin', 'socrates_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'socrates_nonce' ),
    ));
}

add_action( 'wp_ajax_fetch_feeds', 'socrates_fetch_feeds' );

function socrates_fetch_feeds() {

	// Check for nonce security
    if (!check_ajax_referer('socrates_nonce', 'nonce', false)) {
        echo 'Nonce check failed!';
        wp_die();
    }

	// Get the starting prompt from the AJAX request
    $starting_prompt = isset( $_POST[ 'starting_prompt' ] ) ? wp_kses_post( $_POST[ 'starting_prompt' ] ) : '';

    $response = socrates_3_rss_test();

	// Now split this response into lines so we can add details.
	$lines_of_response = preg_split( "/\r\n|\n|\r/", $response );

	$output = '';

	$articles = socrates_3_get_articles();

	foreach ( $lines_of_response as $lineid => $line_text ) {

		// Ensure this is a scored line, i.e. no other details.
		if ( substr( $line_text, 0, 5 ) !== "Post " ) {
			continue;
		}

		// Split by the :: delimiter and create usable variables for each of the parts.
		list( $post_id, $score, $confidence, $category ) = explode( ' :: ', $line_text );

		// trim the unnecessary fat
		$post_id    = trim( str_replace( 'Post ', '', $post_id ) );
		$score      = trim( str_replace( 'Score ', '', $score ) );
		$confidence = trim( str_replace( 'Confidence ', '', $confidence ) );
		$category   = trim( $category );

		$article_number = absint( $post_id ) - 1;

		$article = $articles[ $article_number ];

		$output .= '<ul>';
		$output .= '<li>Article: ' . esc_html( $article['title'] ) . '</li>';
		$output .= '<li>Score: ' . esc_html( $score ) . ' (' . absint( $confidence ) . '%)</li>';
		$output .= '<li>Catgory: ' . esc_html( $category ) . '</li>';
		$output .= '</ul>';

	}

	$output .= '<p>' . $starting_prompt . '</p>';

    echo wp_kses_post( $output );
    wp_die();
}
