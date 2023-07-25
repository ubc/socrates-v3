<?php
/**
 * Class Socrates_Fetch_Feeds
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

use \andreskrey\Readability\Readability;
use \andreskrey\Readability\Configuration;
use \andreskrey\Readability\ParseException;

/**
 * Socrates_Fetch_Feeds Class.
 *
 * Fetches all of the feeds set within the Socrates Feed Settings area.
 */
class Socrates_Fetch_Feeds {

	/**
	 * Will be filled with the Feeds that the site admin has added to their
	 * socrates settings under Feed Settings.
	 */
	var $socratic_feeds = array();

	/**
	 * Will be filled with the fetched articles.
	 */
	var $articles = array();

	/**
	 * Will be filled with links that we're creating. Different from
	 * $articles in so far that these are guaranteed to be unique and
	 * not  in our database so far. And this list will be used to get
	 * details from the LLM
	 */
	var $links = array();

	/**
	 * Will be filled with the prompt. This will be a combination of the
	 * starting prompt from the settings, and the titles/excerpts.
	 */
	var $prompt = '';

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
	 * The data to output to the screen.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function output() {

		// Grab all of the articles from the listed feeds.
		foreach ( $this->socratic_feeds as $findex => $feed ) {
			$this->fetch_articles_from_feed( esc_url( $feed['url'] ), 2 );
		}

		// Check to see which of these articles we already have in our links
		foreach ( $this->articles as $aindex => $article ) {
			$this->check_for_and_remove_duplicate_articles( $article );
		}

		// Now get the excerpt/content for each of these new articles.
		foreach ( $this->articles as $aaindex => $unique_article ) {
			$this->fetch_article_content_and_excerpt( $unique_article['link'] );
		}

		// Now generate the prompt we'll send to the LLM
		$this->generate_prompt();

		// Now hit the LLM to get the category, and rating for each link.
		$this->use_llm_to_categorize_and_rate_links();

	}//end output()


	/**
	 * Fetch the most recent articles from the passed feed.
	 *
	 * @param  string $feed_url The URL of the feed to fetch.
	 * @param  int $article_count the number of articles to fetch from this feed.
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

		$articles = [];

		foreach ( $rss_items as $item ) {

			$title = esc_attr( $item->get_title() );
			$link  = esc_url( $item->get_permalink() );

			$this_article = array( 'title' => $title, 'link' => $link );
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
	 * Check whether the passed $article is already in our database of links.
	 *
	 * @param  array $article
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function check_for_and_remove_duplicate_articles( $article = array() ) {



	}//end check_for_and_remove_duplicate_articles()


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
				// 'content' => $readability->getContent()
			);

		} catch ( ParseException $e ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( sprintf( 'Error processing text: %s on %s', $e->getMessage(), $article['link'] ), true ), FILE_APPEND ); // phpcs:ignore;
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

		$prompt_text = $this->get_starting_prompt();

		foreach ( $this->links as $lid => $link_data ) {
			$prompt_text .= 'post: ' . $lid+1 . "\r\n";
			$prompt_text .= 'title: ' . $link_data['title'] . "\r\n";
			$prompt_text .= 'excerpt: ' . $link_data['excerpt'] . "\r\n";
			$prompt_text .= "\r\n";
		}

		$this->prompt = $prompt_text;

	}//end generate_prompt()


	/**
	 * Fetch and set the start of the prompt that is set in the settings.
	 *
	 * @return string the starting prompt set in the settings
	 * @since 3.0.1
	 */
	public function get_starting_prompt() {
		return wp_kses_post( get_option( 'starting_prompt' ) );
	}//end get_starting_prompt()


	/**
	 * Hit the chosen LLM
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function use_llm_to_categorize_and_rate_links() {

		require_once plugin_dir_path( __FILE__ ) . 'class-socrates-send-llm-request.php';

		$request = new Socrates_Send_LLM_Request();

		$request->set_prompt( $this->prompt );

		$request->send_request();

	}//end use_llm_to_categorize_and_rate_links()

}
