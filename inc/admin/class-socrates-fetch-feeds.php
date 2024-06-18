<?php
/**
 * Class Socrates_Fetch_Feeds
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;
use andreskrey\Readability\ParseException;

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
	var $max_articles_to_fetch_per_feed = 3;

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
			$this->fetch_article_content_and_excerpt( $unique_article['link'] );
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
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Feed Fetch Error: ', $feed->get_error_message() ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: ' . __LINE__;
		}

		$max_items = $feed->get_item_quantity( absint( $article_count ) );
		$rss_items = $feed->get_items( 0, $max_items );

		$articles = array();

		foreach ( $rss_items as $item ) {

			$title = esc_attr( $item->get_title() );
			$link  = esc_url( $item->get_permalink() );

			$this_article = array(
				'title' => $title,
				'link'  => $link,
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
	 *
	 * @return void
	 * @since
	 */
	public function fetch_article_content_and_excerpt( $unique_article_url ) {

		// Escape to stay safe.
		$unique_article_url = esc_url( $unique_article_url );

		// Fetch the full page's DOM.
		$html = file_get_contents( $unique_article_url );

		// Use readability to parse it.
		$readability = new Readability( new Configuration() );

		// Start empty.
		$new_link_data = false;

		try {

			$readability->parse( $html );

			$new_link_data = array(
				'url'     => $unique_article_url,
				'title'   => $readability->getTitle(),
				'excerpt' => $readability->getExcerpt(),
			);

		} catch ( ParseException $e ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( sprintf( 'Error processing text: %s on %s', $e->getMessage(), $unique_article_url ), true ), FILE_APPEND ); // phpcs:ignore;
		}

		if ( false === $new_link_data ) {
			return;
		}

		// We know we have a new link. Add this to our class property.
		$this->update_link_list( $new_link_data );
	}//end fetch_article_content_and_excerpt()


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

		// Add what is in the options as the starting prompt.
		$prompt_text = $this->get_starting_prompt();

		foreach ( $this->links as $lid => $link_data ) {
			$prompt_text .= 'post: ' . $lid + 1 . "\r\n";
			$prompt_text .= 'title: ' . $link_data['title'] . "\r\n";
			$prompt_text .= 'excerpt: ' . $link_data['excerpt'] . "\r\n";
			$prompt_text .= "\r\n";
		}

		// Now add the categories set in the options.
		$categories_string = $this->get_content_categories_as_string();

		if ( ! empty( $categories_string ) ) {
			$prompt_text .= 'Categories to use: ' . $categories_string;
		}

		// this isn't a conversation, so it's just one 'message' we send, but still have
		// to formulate it as part of a conversation.
		$messages = array(
			array(
				'role'    => 'user',
				'content' => $prompt_text,
			),
		);

		// $this->prompt = $prompt_text;
		$this->prompt = $messages;
	}//end generate_prompt()


	/**
	 * Fetch and set the start of the prompt that is set in the settings.
	 *
	 * @return string the starting prompt set in the settings
	 * @since 3.0.1
	 */
	public function get_starting_prompt() {
		return wp_kses_post( get_option( 'socratic_notw_starting_prompt' ) );
	}//end get_starting_prompt()


	/**
	 * Hit the chosen LLM
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function use_llm_to_categorize_and_rate_links() {

		require_once plugin_dir_path( __FILE__ ) . 'class-socrates-send-llm-request.php';

		// Send the prompt to the LLM
		$request = new Socrates_Send_LLM_Request();
		$request->set_prompt( $this->prompt );
		$request->send_request();

		// This is the response from the LLM.
		$data = $request->get_llm_response_data();

		/*
		This should look like the following:
		Post 1 :: Score 2/10 :: Confidence 80% :: Climate Change
		Post 2 :: Score 7/10 :: Confidence 90% :: Legal
		... etc
		*/
		$this->llm_response = $data;
	}//end use_llm_to_categorize_and_rate_links()


	/**
	 * We need to compare the links we have gathered, with the response from the LLM.
	 * If the score is above the minimum score threshold, we create a bookmark.
	 *
	 * @return array
	 * @since 3.0.1
	 */
	public function get_raw_data_for_bookmarks() {

		// Ensure we have a response, bail otherwise.
		if ( false === $this->llm_response ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( __FILE__, __LINE__, 'No LLM Response' ), true ), FILE_APPEND ); // phpcs:ignore
			return;
		}

		// Convert the LLM response into a usable array.
		$llm_response_as_array = $this->convert_llm_response_to_array();

		// Score must be higher than this for it to make it into the bookmarks.
		$threshold_score = $this->get_threshold_score();

		$usable_data = array();

		// Now combine the LLM response, with our links. Only add if the link meets the threshold.
		foreach ( $this->links as $lid => $link ) {

			$this_links_score = $this->convert_score_out_of_10_to_integer( $llm_response_as_array[ $lid ]['score'] );

			if ( $this_links_score < $threshold_score ) {
				continue;
			}

			// Link score meets threshold, so add it to the array of bookmarks we should make.
			$usable_data[] = array(
				'title'      => $link['title'],
				'url'        => $link['url'],
				'excerpt'    => $link['excerpt'],
				'score'      => $this_links_score,
				'category'   => $llm_response_as_array[ $lid ]['category'],
				'confidence' => $llm_response_as_array[ $lid ]['confidence'],
			);

		}

		return $usable_data;
	}//end get_raw_data_for_bookmarks()


	/**
	 * Convert the llm response string to a usable array which we're then able
	 * to compared against the links we fetched.
	 *
	 * Converts:
	 * Post 1 :: Score 2/10 :: Confidence 80% :: Category None
	 * Post 2 :: Score 2/10 :: Confidence 70% :: Category Legal
	 *
	 * Into:
	 * array(
	 *     0 => array(
	 *         'post_id' => 1,
	 *         'score' => 2/10,
	 *         'confidence' => 80%,
	 *         'category' => 'none'
	 *     ),
	 *     1 => array(
	 *         'post_id' => 2,
	 *         'score' => 2/10,
	 *         'confidence' => 70%,
	 *         'category' => 'Legal'
	 *     ),
	 * )
	 *
	 * Note the ID of the items is the array isn't the same as the post_id because the LLM spits out
	 * posts starting at 1, not 0.
	 *
	 * @return array each item is a line of the response, with each subarray containing the score, confidence, and category
	 * @since
	 */
	public function convert_llm_response_to_array() {

		$lines_of_response = preg_split( "/\r\n|\n|\r/", $this->llm_response );

		$usable_array = array();

		foreach ( $lines_of_response as $lineid => $line_text ) {

			// Ensure this is a scored line, i.e. no other details.
			// Use a regex to match lines starting with 'Post ' or 'post ' followed by an ID.
			if ( ! preg_match( '/^post\s+\d+\s*::/i', $line_text ) ) {
				continue;
			}

			// Split by the :: delimiter and create usable variables for each of the parts.
			list( $post_id, $score, $confidence, $category ) = explode( ' :: ', $line_text );

			// trim the unnecessary fat.
			$post_id    = trim( preg_replace( '/^post\s+/i', '', $post_id ) );
			$score      = trim( preg_replace( '/^score\s+/i', '', $score ) );
			$confidence = trim( preg_replace( '/^confidence\s+/i', '', $confidence ) );
			$category   = trim( preg_replace( '/^category\s+/i', '', $category ) );

			$article_number = absint( $post_id ) - 1;

			$usable_array[ $article_number ] = array(
				'post_id'    => $post_id,
				'score'      => $score,
				'confidence' => $confidence,
				'category'   => $category,
			);

		}

		return $usable_array;
	}//end convert_llm_response_to_array()


	/**
	 * Convert a score of something like 5/10 or 9/10 (i.e. x/10) to an integer
	 * so that we can then set a threshold and compare.
	 *
	 * @param  string $score the score to convert to an int
	 *
	 * @return int
	 * @since 3.0.1
	 */
	public function convert_score_out_of_10_to_integer( $score ) {

		$score = trim( $score ); // 5/10

		list( $rating, $denominator ) = explode( '/', $score ); // $rating = 5; $denominator = 10;

		$rating = absint( $rating ); // 5

		return $rating;
	}//end convert_score_out_of_10_to_integer()


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

		return implode( ', ', $this->get_content_categories() );
	}//end get_content_categories_as_string()
}
