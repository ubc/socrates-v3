<?php
/**
 * Class Socrates_Bookmark_Creator.
 *
 * Turns the usable data from the feeds we've fetched, which have been categorized
 * and scored by an LLM, into 'bookmarks'
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

/**
 * Socrates_Bookmark_Creator Class.
 */
class Socrates_Bookmark_Creator {

	/**
	 * Will be filled with the categorized and scored links. An array of arrays.
	 */
	var $links = array();

	/**
	 * Initalize ourselves with the passed link data. Expects an array of arrays, each sub array like this:
	 *
	 * array(
	 *      'title' => 'Mass extinction event 260 million years ago resulted from climate change, studies say',
	 *      'url' => 'https://arstechnica.com/?p=1956409',
	 *      'excerpt' => 'Ocean stagnation, ecosystem collapses, and volcano eruptions all played a role.',
	 *      'score' => 4, // NOTE: integer
	 *      'category' => 'Climate Change',
	 *      'confidence' => '70%',
	 * )
	 *
	 * @param  array $categorized_and_scored_links
	 * @since 3.0.1
	 */
	public function __construct( $categorized_and_scored_links = array() ) {

		// Make this a class property so we can use it more easily.
		$this->set_links( $categorized_and_scored_links );

		// Check for and remove dupes.
		$this->check_for_dupes();

		// Create the bookmarks.
		$this->create_bookmarks();
	}//end __construct()


	/**
	 * A setter for the categorized score and links.
	 *
	 * @param  array $categorized_and_scored_links
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_links( $categorized_and_scored_links ) {

		$this->links = $categorized_and_scored_links;
	}//end set_links()


	/**
	 * Check for, and remove, any duplicates. We look in our links table and see if we have
	 * any of the incoming links.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function check_for_dupes() {

		$links = $this->links;

		$dupes = array();

		foreach ( $links as $lid => $link_array ) {

			$dupe_check = get_bookmarks(
				array(
					'limit'          => 1,
					'search'         => $link_array['url'],
					'hide_invisible' => 0,
				)
			);

			if ( ! is_array( $dupe_check ) || empty( $dupe_check ) ) {
				continue;
			}

			// This is a dupe. Remove from array.
			$dupes[] = $lid;

		}

		// Remove all of the dupes from our cloned copy of the links
		foreach ( $dupes as $did => $dupe_key ) {
			unset( $links[ $dupe_key ] );
		}

		// Set our de-duped list of links as the links class property.
		$this->set_links( $links );
	}//end check_for_dupes()


	/**
	 * Create the bookmarks based on the de-duped list of links.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function create_bookmarks() {

		// Load the bookmark functions if they're not already available.
		if ( ! function_exists( 'wp_insert_link' ) ) {
			require_once ABSPATH . 'wp-admin/includes/bookmark.php';
		}

		// This is a de-duped list.
		$bookmarks_to_create = $this->links;

		foreach ( $bookmarks_to_create as $bid => $article ) {

			$this->create_bookmark_category_if_not_exists( $article['category'] );

			$category_id = $this->get_link_category_id( $article['category'] );

			$bookmark_data = array(
				'link_name'        => sanitize_text_field( wp_trim_words( $article['title'], 20 ) ),
				'link_url'         => esc_url( $article['url'] ),
				'link_description' => sanitize_text_field( wp_trim_words( $article['excerpt'], 25 ) ),
				'link_category'    => absint( $category_id ),
				'link_rating'      => absint( $article['score'] ),
				'link_visible'     => 'N', // Visibility is used to determine whether it's been in the NotW yet or not.
			);

			$inserted_link = \wp_insert_link( $bookmark_data, true );

		}
	}//end create_bookmarks()


	/**
	 * Create the passed bookmark category if it doesn't exist.
	 *
	 * @param  string $category
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function create_bookmark_category_if_not_exists( $category = '' ) {

		if ( \term_exists( $category, 'link_category' ) ) {
			return;
		}

		$inserted_term = \wp_insert_term( $category, 'link_category' );
	}//end create_bookmark_category_if_not_exists()


	/**
	 * Get the ID of the category for the passed link category.
	 *
	 * @param  string $category
	 *
	 * @return int
	 * @since
	 */
	public function get_link_category_id( $category = '' ) {

		$term = \get_term_by( 'name', $category, 'link_category' );

		$term_id = $term->term_id;

		return $term_id;
	}//end get_link_category_id()
}
