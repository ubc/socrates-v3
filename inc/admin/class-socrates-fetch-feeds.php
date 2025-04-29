<?php
/**
 * Class Socrates_Fetch_Feeds
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

use Readability\Readability;
use Readability\Configuration;
use Readability\ParseException;

/**
 * Socrates_Fetch_Feeds Class.
 *
 * Fetches all of the feeds set within the Socrates Feed Settings area.
 */
class Socrates_Fetch_Feeds {

	/**
	 * Will be filled with the Feeds that the site admin has added to their
	 * socrates settings under Feed Settings.
	 *
	 * @var array
	 */
	var $socratic_feeds = array();

	/**
	 * Will be filled with the fetched articles.
	 *
	 * @var array
	 */
	var $articles = array();

	/**
	 * Will be filled with links that we're creating. Different from
	 * $articles in so far that these are guaranteed to be unique and
	 * not  in our database so far. And this list will be used to get
	 * details from the LLM.
	 *
	 * @var array
	 */
	var $links = array();

	/**
	 * Will be filled with the prompt. This will be a combination of the
	 * starting prompt from the settings, and the titles/excerpts.
	 *
	 * @var string
	 */
	var $prompt = '';

	/**
	 * Will be filled with the raw response from the LLM
	 *
	 * @var bool|string
	 */
	var $llm_response = false;

	/**
	 * The maximum number of articles to fetch per feed.
	 *
	 * @var int
	 */
	var $max_articles_to_fetch_per_feed = 5;

	/**
	 * Construct the plugin object.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function __construct() {
		$this->set_feeds();
	}//end __construct()


	/**
	 * Fetch the feeds saved in options and put them into the
	 * class property $socratic_feeds.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_feeds() {

		$socratic_feeds = get_option( 'socratic_feeds' );

		if ( ! is_array( $socratic_feeds ) || empty( $socratic_feeds ) ) {
			$this->socratic_feeds = array();
		}

		$this->socratic_feeds = $socratic_feeds;
	}//end set_feeds()


	/**
	 * Main method which fetches the feeds, categorizes them using an llm,
	 *
	 * @return array
	 * @since 3.0.1
	 */
	public function output() {

		// Grab all of the articles from the listed feeds.
		foreach ( $this->socratic_feeds as $findex => $feed ) {
			$this->fetch_articles_from_feed( esc_url( $feed['url'] ), $this->max_articles_to_fetch_per_feed );
		}

		// Now get the excerpt/content for each of these new articles.
		foreach ( $this->articles as $aaindex => $unique_article ) {
			$this->fetch_article_content_and_excerpt( $unique_article['link'], $unique_article['guid'] );
		}

		// Now generate the prompt we'll send to the LLM.
		$this->generate_prompt();

		// Now hit the LLM to get the category, and rating for each link.
		$this->use_llm_to_categorize_and_rate_links();

		// Now we get the usable data and return it.
		return $this->get_raw_data_for_bookmarks();
	}//end output()


	/**
	 * Fetch the most recent articles from the passed feed.
	 *
	 * @param  string $feed_url The URL of the feed to fetch.
	 * @param  int    $article_count the number of articles to fetch from this feed.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function fetch_articles_from_feed( $feed_url = '', $article_count = 2 ) {

		$feed = fetch_feed( esc_url( $feed_url ) );

		if ( is_wp_error( $feed ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Feed Fetch Error: ', $feed->get_error_message(), $feed_url ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: ' . __LINE__;
		}

		$max_items = $feed->get_item_quantity( absint( $article_count ) );
		$rss_items = $feed->get_items( 0, $max_items );

		$articles = array();

		foreach ( $rss_items as $item ) {

			$title = esc_attr( $item->get_title() );
			$link  = esc_url( $item->get_permalink() );
			$guid  = esc_url( $item->get_id() );

			$this_article = array(
				'title' => $title,
				'link'  => $link,
				'guid'  => $guid,
			);
			$this->update_articles_list( $this_article );

		}
	}//end fetch_articles_from_feed()


	/**
	 * A method to add an article to the list of articles.
	 *
	 * @return void
	 * @since
	 */
	public function update_articles_list( $article_to_add = array() ) {

		$current_articles = $this->articles;

		$current_articles[] = $article_to_add;

		$this->articles = $current_articles;
	}//end update_articles_list()

	/**
	 * The passed $unique_article is not in our database of links. So, before
	 * we add it, we'll go fetch the excerpt and content for it.
	 *
	 * @param  string $unique_article_url The URL of the article to grab.
	 * @param  string $unique_article_guid The GUID of the article to use as a fallback.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function fetch_article_content_and_excerpt( $unique_article_url, $unique_article_guid = '' ) {
		// Set our consistent request options.
		$request_options = array(
			'timeout'   => 5,
			'sslverify' => false,
		);

		// Escape to stay safe.
		$unique_article_url = esc_url( $unique_article_url );

		// Try to fetch the full page's DOM using wp_remote_get.
		$response = wp_remote_get( $unique_article_url, $request_options );

		// If the first URL fails and we have a GUID, try that instead.
		if ( is_wp_error( $response ) && ! empty( $unique_article_guid ) ) {
			$unique_article_guid = esc_url( $unique_article_guid );
			$response            = wp_remote_get( $unique_article_guid, $request_options );
			if ( ! is_wp_error( $response ) ) {
				$unique_article_url = $unique_article_guid;
			}
		}

		// One last try with modified URL if it contains &#038;.
		if ( is_wp_error( $response ) && strpos( $unique_article_url, '&#038;' ) !== false ) {
			$modified_url = str_replace( '&#038;', '&', $unique_article_url );
			$response     = wp_remote_get( $modified_url, $request_options );
			if ( ! is_wp_error( $response ) ) {
				$unique_article_url = $modified_url;
			}
		}

		// If all attempts failed, log the error and return.
		if ( is_wp_error( $response ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Error fetching article content from: ' . $unique_article_url, 'Error: ' . $response->get_error_message() ), true ), FILE_APPEND ); // phpcs:ignore
			return;
		}

		// Get the response code.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Non-200 response code when fetching: ' . $unique_article_url, 'Response code: ' . $response_code ), true ), FILE_APPEND ); // phpcs:ignore
			return;
		}

		// Get the body content.
		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Empty response body when fetching: ' . $unique_article_url ), true ), FILE_APPEND ); // phpcs:ignore
			return;
		}

		// Use readability to parse it.
		$readability = new Readability( $html, $unique_article_url, 'libxml', false );

		// Start empty.
		$new_link_data = false;

		$result = $readability->init();

		if ( $result ) {
			$new_link_data = array(
				'url'     => $unique_article_url,
				'title'   => $readability->getTitle()->textContent,
				'excerpt' => $readability->getContent()->textContent,
			);
		} else {
			// Try alternative parsing method if first attempt fails.
			$doc                     = new \DOMDocument();
			$doc->substituteEntities = false;
			$content                 = mb_convert_encoding( $html, 'html-entities', 'utf-8' );

			$doc->loadHTML( $content );
			$html = $doc->saveHTML();

			$readability = new Readability( $html, $unique_article_url, 'libxml', false );
			$result      = $readability->init();

			if ( $result ) {
				$new_link_data = array(
					'url'     => $unique_article_url,
					'title'   => $readability->getTitle()->textContent,
					'excerpt' => $readability->getContent()->textContent,
				);
			} else {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Failed to parse content using Readability for: ' . $unique_article_url ), true ), FILE_APPEND ); // phpcs:ignore
			}
		}

		if ( false === $new_link_data ) {
			return;
		}

		// We know we have a new link. Add this to our class property.
		$this->update_link_list( $new_link_data );
	}

	/**
	 * A method to add a link to the list of links.
	 *
	 * @return void
	 * @since
	 */
	public function update_link_list( $link_to_add = array() ) {

		$current_links = $this->links;

		$current_links[] = $link_to_add;

		$this->links = $current_links;
	}//end update_link_list()

	/**
	 * Generate our prompt which is a combination of the starting prompt
	 * from the options and the list of links we're going to categorize and
	 * score.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function generate_prompt() {

		// Fetch the new guided settings.
		$focus_description = wp_kses_post( get_option( 'socratic_notw_focus_description' ) );
		$emphasis_aspect   = sanitize_text_field( get_option( 'socratic_notw_rating_emphasis_aspect' ) );
		$categories        = $this->get_content_categories();

		// Build the base prompt text using the new settings, asking for JSON output.
		$prompt_text  = "You will be provided with a list of blog posts (<blog_posts>...</blog_posts>) containing titles and excerpts.\n";
		$prompt_text .= 'Your task is to analyze each post and rate its relevance to the main subject area: ' . ( ! empty( $focus_description ) ? $focus_description : '[Main Subject Area not set]' ) . ".\n\n";

		if ( ! empty( $emphasis_aspect ) ) {
			$prompt_text .= 'When rating, pay specific attention to: ' . $emphasis_aspect . ".\n\n";
		} else {
			$prompt_text .= "\n";
		}

		$prompt_text .= "Assign a score from 1 (least relevant) to 10 (most relevant) and a confidence percentage (0-100) for your rating.\n";
		$prompt_text .= "Finally, categorize each post using ONLY ONE of the following categories:\n";

		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$prompt_text .= '- ' . $category . "\n";
			}
		} else {
			$prompt_text .= "[No categories set]\n";
		}
		$prompt_text .= "- Other (Use this if no other category fits)\n\n";

		$prompt_text .= "Your response MUST be a single valid JSON object.\n";
		$prompt_text .= "This JSON object must contain a single key named \"results\".\n";
		$prompt_text .= "The value of the \"results\" key MUST be a JSON array.\n";
		$prompt_text .= "Each element in the \"results\" array MUST be a JSON object corresponding to one of the analyzed blog posts.\n";
		$prompt_text .= "Each post object within the \"results\" array MUST have the following structure and keys: \n";
		$prompt_text .= "`{\"post_id\": number, \"score\": number, \"confidence\": number, \"category\": \"string\"}`\n\n";

		$prompt_text .= "Example Input Posts:\n";
		$prompt_text .= "post: 1\n";
		$prompt_text .= "title: AI Wins Art Prize\n";
		$prompt_text .= "excerpt: An AI generated image won first place... implications for copyright...\n";
		$prompt_text .= "post: 2\n";
		$prompt_text .= "title: New Mario Game Announced\n";
		$prompt_text .= "excerpt: Nintendo revealed the next installment... no legal issues mentioned...\n\n";

		$prompt_text .= "Example JSON Object Output (containing a \"results\" array with objects for the two example posts):\n";
		$prompt_text .= "`{\n";
		$prompt_text .= "  \"results\": [\n";
		$prompt_text .= "    {\"post_id\": 1, \"score\": 8, \"confidence\": 95, \"category\": \"Copyright\"},\n";
		$prompt_text .= "    {\"post_id\": 2, \"score\": 2, \"confidence\": 60, \"category\": \"Other\"}\n";
		$prompt_text .= "  ]\n";
		$prompt_text .= "}`\n\n";

		$prompt_text .= "Ensure the final output is ONLY the single JSON object (starting with `{` and ending with `}`), with no introductory text, explanations, or markdown formatting around the JSON itself.\n";

		// And now add the blog posts, wrapped in <blog_posts> tags.
		$prompt_text .= "\n";
		$prompt_text .= "Here are the blog posts:\n\n";
		$prompt_text .= "<blog_posts>\n";

		foreach ( $this->links as $lid => $link_data ) {
			$prompt_text .= 'post: ' . ( $lid + 1 ) . "\n"; // Use (lid + 1) for human-readable post numbers starting from 1.
			$prompt_text .= 'title: ' . wp_trim_words( $link_data['title'], 20 ) . "\n";
			$prompt_text .= 'excerpt: ' . wp_trim_words( $link_data['excerpt'], 50 ) . "\n";
			$prompt_text .= "\n";
		}

		$prompt_text .= '</blog_posts>';

		// This isn't a conversation, so it's just one 'message' we send, but still have
		// to formulate it as part of a conversation.
		$messages = array(
			array(
				'role'    => 'user',
				'content' => $prompt_text,
			),
		);

		$this->prompt = $messages;
	}//end generate_prompt()

	/**
	 * Hit the chosen LLM
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function use_llm_to_categorize_and_rate_links() {

		require_once plugin_dir_path( __FILE__ ) . 'class-socrates-send-llm-request.php';

		// Send the prompt to the LLM.
		$request = new Socrates_Send_LLM_Request();

		// Log the prompt being sent to the LLM for debugging purposes.
		file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'LLM Prompt' => $this->prompt ), true ), FILE_APPEND );

		$request->set_prompt( $this->prompt );
		$request->send_request( true ); // Request JSON mode.

		// This is the response from the LLM.
		// Get the potentially parsed response (array if JSON is successful, string/null otherwise).
		$parsed_llm_data = $request->get_llm_response_data( true );

		// Log the response from the LLM for debugging purposes.
		file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'LLM Parsed Response' => $parsed_llm_data ), true ), FILE_APPEND );

		// Store the parsed data (could be array, string on error, or null).
		$this->llm_response = $parsed_llm_data;
	}//end use_llm_to_categorize_and_rate_links()

	/**
	 * We need to compare the links we have gathered, with the response from the LLM.
	 * If the score is above the minimum score threshold, we create a bookmark.
	 *
	 * @return array
	 * @since 3.0.1
	 */
	public function get_raw_data_for_bookmarks() {

		// Check if the LLM response is a valid array (successful JSON parse).
		if ( ! is_array( $this->llm_response ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( __FILE__, __LINE__, 'LLM Response is not a valid array. Cannot process bookmarks.', 'Response' => $this->llm_response ), true ), FILE_APPEND ); // phpcs:ignore
			return array(); // Return empty array on error or non-array response.
		}

		// Check if the array is nested under a 'results' key (common with ChatGPT JSON mode).
		if ( isset( $this->llm_response['results'] ) && is_array( $this->llm_response['results'] ) ) {
			$parsed_llm_data = $this->llm_response['results'];
		} else {
			// Assume the response is the array directly.
			$parsed_llm_data = $this->llm_response;
		}

		// Score must be higher than this for it to make it into the bookmarks.
		$threshold_score = $this->get_threshold_score();

		$usable_data = array();

		// Now combine the LLM response, with our links. Only add if the link meets the threshold.
		// The LLM response array ($parsed_llm_data) should have keys corresponding to the post_id - 1.
		// Iterate through the parsed data directly.
		foreach ( $parsed_llm_data as $link_data ) {

			// Validate the structure of the current item.
			if ( ! isset( $link_data['post_id'], $link_data['score'], $link_data['category'], $link_data['confidence'] ) ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( __FILE__, __LINE__, 'Skipping invalid link data item', 'Item' => $link_data ), true ), FILE_APPEND ); // phpcs:ignore
				continue;
			}

			$post_id          = absint( $link_data['post_id'] );
			$this_links_score = absint( $link_data['score'] ); // Score should be an integer directly from JSON.
			$category         = sanitize_text_field( $link_data['category'] );
			$confidence       = absint( $link_data['confidence'] ); // Confidence should be an integer.

			// Find the corresponding original link data using post_id (adjusting for 0-based index).
			$original_link_index = $post_id - 1;
			if ( ! isset( $this->links[ $original_link_index ] ) ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( __FILE__, __LINE__, 'Could not find original link data for post_id', 'post_id' => $post_id ), true ), FILE_APPEND ); // phpcs:ignore
				continue;
			}
			$link = $this->links[ $original_link_index ];

			if ( $this_links_score < $threshold_score ) {
				continue;
			}

			// Link score meets threshold, so add it to the array of bookmarks we should make.
			$usable_data[] = array(
				'title'      => $link['title'],
				'url'        => $link['url'],
				'excerpt'    => $link['excerpt'],
				'score'      => $this_links_score,
				'category'   => $category,
				'confidence' => $confidence,
			);

		}

		return $usable_data;
	}//end get_raw_data_for_bookmarks()

	/**
	 * The minimum threshold score
	 *
	 * @return int
	 * @since 3.0.1
	 */
	public function get_threshold_score() {
		return absint( get_option( 'socratic_minimum_threshold_score' ) );
	}//end get_threshold_score()

	/**
	 * Fetches the categories stored in the settings
	 *
	 * @return array
	 * @since 3.0.1
	 */
	public function get_content_categories() {

		$categories = get_option( 'socratic_categories' );

		if ( ! $categories || ! is_array( $categories ) || empty( $categories ) ) {
			return array();
		}

		return $categories;
	}//end get_content_categories()

	/**
	 * Turns the array of categories stored in the settings into a
	 * string that can be used as part of the prompt.
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function get_content_categories_as_string() {

		// Initialize an empty string to store the formatted categories.
		$formatted_string = '';

		// Iterate through each category in the array.
		foreach ( $this->get_content_categories() as $category ) {
			// Append the formatted category name to the string with a new line.
			$formatted_string .= '- ' . $category . PHP_EOL;
		}

		// Return the formatted string.
		return $formatted_string;
	}//end get_content_categories_as_string()
}
