<?php
/**
 * Class Socrates_Notw_Post_Creator
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

/**
 * Socrates_Notw_Post_Creator Class.
 *
 * Based on the bookmarks added by the Bookmark Creator class that have visibility 'N' we create a post
 * that is separated into the categories listed in the settings.
 *
 * Additionally we use the 'Include "other" category links in News of the Week posts' option to determine
 * if we are only to add the bookmarks from the chosen categories.
 */
class Socrates_Notw_Post_Creator {

	/**
	 * Will be filled with the links that we are to add to the NotW post we
	 * are creating. An array of arrays, each inner array has a top level
	 * key of the category of the link, and values of the links within, each
	 * with the URL and the title.
	 */
	var $links = array();

	/**
	 * Will be filled with the title of this week's post.
	 */
	var $title = '';

	/**
	 * Will be filled with the content of this week's post.
	 */
	var $content = '';

	/**
	 * Will be filled with the excerpt to be used.
	 */
	var $excerpt = '';

	/**
	 * Will be filled with the category ID for the news of the week category.
	 */
	var $category = 0;

	/**
	 * Will be filled with the status we'll use for the post.
	 */
	var $post_status = '';

	/**
	 * Will be filled with the post type.
	 */
	var $post_type = '';

	/**
	 * Will be filled with the post ID of the newly created NotW Post
	 */
	var $newly_created_post_id = false;

	/**
	 * Initialize ourselves.
	 *
	 * @return int The post ID of the newly created NotW Post
	 * @since 3.0.1
	 */
	public function __construct() {

		$this->gather_links();

		$this->generate_post();

		$this->create_post();

		$this->change_link_visibility();

		$this->send_email_to_admin();

		return $this->newly_created_post_id;
	}//end __construct()


	/**
	 * Determine which links we need to include in this new post.
	 *  - Bookmarks that have 'N' for visibility
	 *  - Links from relevnt categories (i.e. if we're including the "Other" category or not)
	 *
	 * @return void
	 * @since
	 */
	public function gather_links() {

		$bookmark_category_ids = $this->get_notw_bookmark_category_ids();

		$get_bookmarks_params = array(
			'hide_invisible' => 0,
			'category'       => $bookmark_category_ids,
		);

		$all_bookmarks_in_categories = get_bookmarks( $get_bookmarks_params );

		if ( empty( $all_bookmarks_in_categories ) || ! is_array( $all_bookmarks_in_categories ) ) {
			return;
		}

		// Now we need to remove the links that have 'Y' for link_visible
		$usable_bookmarks = array();

		foreach ( $all_bookmarks_in_categories as $lid => $bookmark_object ) {

			if ( 'Y' === $bookmark_object->link_visible ) {
				continue;
			}

			$usable_bookmarks[ $bookmark_object->term_id ][] = array(
				'link_id'          => $bookmark_object->link_id,
				'link_url'         => $bookmark_object->link_url,
				'link_name'        => $bookmark_object->link_name,
				'term_id'          => $bookmark_object->term_id,
				'link_description' => $bookmark_object->link_description,
			);
		}

		// This is now a multi-dimensional array. Each inner array is the link category ID for the links
		// contained within.

		// If we are using the Other category, we need to ensure that's last in the array.
		$other_category_term = get_term_by( 'name', 'Other', 'link_category' );

		$other_cat_id = $other_category_term->term_id;

		// Most efficient way to do this is to unset it, and then add it back.
		if ( isset( $usable_bookmarks[ $other_cat_id ] ) ) {
			$other_bookmarks = $usable_bookmarks[ $other_cat_id ];
			unset( $usable_bookmarks[ $other_cat_id ] );
			$usable_bookmarks[ $other_cat_id ] = $other_bookmarks;
		}

		$this->links = $usable_bookmarks;
	}//end gather_links()


	/**
	 * Get a comma separated list of category IDs (which can be used in get_bookmarks)
	 * taking into account whether we are including the other category or not based
	 * on the settings.
	 *
	 * @return string a comma separated list of category IDs
	 * @since 3.0.1
	 */
	public function get_notw_bookmark_category_ids() {

		// Are we including the 'Other' category?
		$include_other_cat = get_option( 'socratic_include_other_category_links' );

		$get_terms_params = array(
			'taxonomy'   => 'link_category',
			'hide_empty' => false,
		);

		if ( ! $include_other_cat || empty( $include_other_cat ) || 'no' === $include_other_cat ) {
			// We're not including the other category, so lets exclude that from the get_terms call.
			// We need to know the ID of the "Other" link_category
			$other_category_term = get_term_by( 'name', 'Other', 'link_category' );
			// Nested Ifs. Oh no. If we have an 'Other' link category, get the ID and then exlude that
			// from the params
			if ( $other_category_term ) {
				$get_terms_params['exclude'] = $other_category_term->term_id;
			}
		}

		// Fetch all of the terms we need.
		$needed_link_cats = get_terms( $get_terms_params );

		// Double check we have some cats otherwise an empty string
		if ( ! $needed_link_cats || ! is_array( $needed_link_cats ) || empty( $needed_link_cats ) ) {
			return '';
		}

		// Loop over each term, add IDs to an array and then convert that into a comma separated string
		$needed_cats_array = array();

		foreach ( $needed_link_cats as $tid => $term_object ) {
			$needed_cats_array[] = $term_object->term_id;
		}

		return implode( ',', $needed_cats_array );
	}//end get_notw_bookmark_category_ids()


	/**
	 * Based on $this->links generate the content of the news of the week post.
	 * This is a post which
	 *
	 * - has a title of "News of the week <date>", // @todo make this a setting
	 * - is in a 'notw' category // @todo make this a setting
	 * - outputs each of the bookmarks under their category headings as a list
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function generate_post() {

		$this->title = $this->get_title();

		$this->content = $this->get_content();

		$this->excerpt = $this->generate_excerpt();

		$this->category = $this->get_notw_post_category();

		$this->post_status = $this->get_notw_post_status();

		$this->post_type = $this->get_post_type();
	}//end generate_post()


	/**
	 * The title of the NotW post.
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function get_title() {

		return 'News of the week for the week of ' . $this->get_week_date_string();
	}//end get_title()


	/**
	 * A string of the date from the start of last week. i.e.
	 * 9th July 2023
	 *
	 * @return string the date string
	 * @since 3.0.1
	 */
	public function get_week_date_string() {
		return date( 'jS \of F Y', strtotime( 'sunday last week' ) );
	}//end get_week_date_string()


	/**
	 * Uses the links to generate the content. Outputs the links under their respective category.
	 * Each link uses the title as the link text and the link as the URL.
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function get_content() {

		$links = $this->links;

		$content = '';

		foreach ( $links as $link_category_id => $link_array ) {
			$content .= $this->get_content_for_link_category( $link_category_id );
		}

		return $content;
	}//end get_content()


	/**
	 * For the passed link category ID, generate the content for this category of links.
	 *
	 * @param  int $link_category_id
	 *
	 * @return string the content we'll use for this link category.
	 * @since 3.0.1
	 */
	public function get_content_for_link_category( $link_category_id ) {

		$link_cat = absint( $link_category_id );

		$heading = $this->get_category_name_from_id( $link_cat );

		$link_list_content = $this->get_link_list_content_for_category( $link_cat );

		$content = '<!-- wp:heading -->';

		$content .= '<h2 class="wp-block-heading">' . $heading . '</h2>';

		$content .= '<!-- /wp:heading -->';
		$content .= $link_list_content;

		return $content;
	}//end get_content_for_link_category()


	/**
	 * Generate the excerpt for the post.
	 *
	 * @return string the excerpt
	 * @since 3.0.1
	 */
	public function generate_excerpt() {

		return 'Links for the week: ' . $this->get_week_date_string();
	}//end generate_excerpt()


	/**
	 * Get the name of the passed category.
	 *
	 * @param  int $link_category_id
	 *
	 * @return string the name of the category.
	 * @since 3.0.1
	 */
	public function get_category_name_from_id( $link_category_id ) {

		$term = get_term_by( 'id', $link_category_id, 'link_category' );

		if ( ! $term ) {
			return 'Unknown Category';
		}

		return $term->name;
	}//end get_category_name_from_id()


	/**
	 * generate the content for each list of links for the passed category ID.
	 *
	 * @param  int $link_category_id The ID of the category
	 *
	 * @return string the content
	 * @since 3.0.1
	 */
	public function get_link_list_content_for_category( $link_category_id ) {

		$links = $this->links[ $link_category_id ];

		// If we are showing the desciption for each link.
		// @TODO: Make this a setting.
		$show_link_desciption = true;

		// Start fresh.
		$content  = '<!-- wp:list -->';
		$content .= '<ul>';

		foreach ( $links as $lid => $link_array ) {

			$content .= '<!-- wp:list-item -->';
			$content .= '<li>';
			$content .= "<a href='" . esc_url( $link_array['link_url'] ) . "'>" . esc_html( $link_array['link_name'] ) . '</a>';

			if ( $show_link_desciption ) {
				$content .= '<br />' . esc_html( $link_array['link_description'] );
			}

			$content .= '</li>';
			$content .= '<!-- /wp:list-item -->';

		}

		$content .= '</ul>';
		$content .= '<!-- /wp:list -->';

		return wp_kses_post( $content );
	}//end get_link_list_content_for_category()

	/**
	 * Fetch the category ID of the News of the Week category.
	 *
	 * @return int
	 * @since 3.0.1
	 */
	public function get_notw_post_category() {

		$notw_post_category = get_option( 'socratic_notw_post_category' );

		if ( ! $notw_post_category || empty( $notw_post_category ) ) {
			return 1; // Uncategorized.
		}

		return absint( $notw_post_category );
	}//end get_notw_post_category()

	/**
	 * Undocumented function
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function get_notw_post_status() {

		return 'draft';
	}//end get_notw_post_status()


	/**
	 * We've generated the post and created class properties. Now we need to create the post
	 * in the database and set it to the correct category and status.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function create_post() {

		// Set up how we're creating the post.
		$insert_post_args = array(
			'post_author'   => $this->get_post_author_id(),
			'post_content'  => $this->content,
			'post_title'    => $this->title,
			'post_excerpt'  => $this->excerpt,
			'post_status'   => $this->post_status,
			'post_type'     => $this->post_type,
			'post_category' => array( $this->category ),
		);

		/**
		 * Filters the arguments used when creating the news of the week post.
		 *
		 * Allows plugin authors to adjust the details of the created post.
		 *
		 * @since 3.0.1
		 * @hooked None by default.
		 */
		$insert_post_args = apply_filters( 'socrates_3_notw_insert_post_args', $insert_post_args );

		// Create the post.
		$new_post_id = wp_insert_post( $insert_post_args );

		if ( ! $new_post_id ) {
			return;
		}

		$this->newly_created_post_id = $new_post_id;
	}//end create_post()


	/**
	 * Returns the ID of the author of the post. By default this will be the user ID of
	 * the person who is the site's main admin.
	 *
	 * @return int
	 * @since 3.0.1
	 */
	public function get_post_author_id() {

		$admin_email = get_option( 'admin_email' );

		$admin_user = get_user_by( 'email', sanitize_email( $admin_email ) );

		if ( empty( $admin_user ) ) {
			return 0;
		}

		return $admin_user->ID;
	}//end get_post_author_id()


	/**
	 * Gets the type of post to make for the NotW post.
	 *
	 * @return string the post type
	 * @since 3.0.1
	 */
	public function get_post_type() {
		return 'post';
	}//end get_post_type()

	/**
	 * Now that the post has been created which contains all of the links that hadn't been
	 * posted yet, we need to signal that those new links have been posted. We do that by
	 * changing the visibility to 'Y' for each link.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function change_link_visibility() {

		// Load the bookmark functions if they're not already available.
		if ( ! function_exists( 'wp_update_link' ) ) {
			require_once ABSPATH . 'wp-admin/includes/bookmark.php';
		}

		$links_to_adjust = $this->links;

		foreach ( $links_to_adjust as $lcid => $links_cat_array ) {

			if ( ! is_array( $links_cat_array ) || empty( $links_cat_array ) ) {
				continue;
			}

			foreach ( $links_cat_array as $lid => $link ) {
				$update_link_args = array(
					'link_id'      => $link['link_id'],
					'link_visible' => 'Y',
				);

				wp_update_link( $update_link_args );
			}
		}
	}//end change_link_visibility()


	/**
	 * The post has been created, the links have been adjusted, and now we need to send
	 * the email to the site admin with a link so that they can view, adjust if necessary,
	 * and publish the post.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function send_email_to_admin() {

		$newly_created_post_id = $this->newly_created_post_id;

		if ( false === $newly_created_post_id ) {
			return;
		}

		$email_subject = $this->get_email_subject();
		$email_body    = $this->get_email_body();
		$email_to      = $this->get_email_to();

		wp_mail( $email_to, $email_subject, $email_body );
	}//end send_email_to_admin()


	/**
	 * Returns the subject used in the email sent to the person who receives the notifcation
	 * about the news of the week post being created.
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function get_email_subject() {
		return '[Action Required] : Review newly created post for "News of the week for the week of ' . $this->get_week_date_string() . '"';
	}//end get_email_subject()



	/**
	 * Undocumented function
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function get_email_body() {

		$status = $this->post_status;

		$post_edit_link = get_edit_post_link( $this->newly_created_post_id, '' );

		$content = "Hi,\r\n\r\na new $status post has been created for news of the week. Please review it, you can do so at:\r\n\r\n";

		$content .= $post_edit_link;

		$content .= "\r\n\r\nThanks,\r\n\r\nSocratesAI";

		return $content;
	}//end get_email_body()



	/**
	 * Undocumented function
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function get_email_to() {
		return get_option( 'admin_email' );
	}//end get_email_to()
}
