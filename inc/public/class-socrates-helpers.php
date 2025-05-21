<?php
/**
 * Class Socrates_Helpers. Included within the main plugin file.
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

use DonatelloZa\RakePlus\RakePlus;

/**
 * Socrates_Helpers Class.
 *
 * Helper methods for Socrates.
 */
class Socrates_Helpers {

	/**
	 * Gets the links shown for a specific prompt
	 *
	 * @param  [integer] $prompt_id
	 * @param  [string]  $chat_id
	 *
	 * @return array
	 * @since 3.0.1
	 */
	public static function socrates_get_links_shown_for_specific_prompt( $prompt_id, $chat_id ) {

		// Sanitize
		$chat_id   = self::socrates_sanitize_chat_id( $chat_id );
		$prompt_id = absint( $prompt_id );

		// in the 'links_shown' array, the zeroth item is shown with the second prompt shown to the user which is item 3 (zero-based) in 'messages'
		// The 1st item is shown with the third prompt shown to the user which is item 5 in 'messages'
		// The 2nd item is shown with the fourth prompt shown to the user which is item 7 in 'messages'
		$user_meta = get_user_meta( get_current_user_id(), 'socratic_chats', true );

		// If this is the first time a user has used this, their user meta for this will be empty.
		if ( empty( $user_meta ) ) {
			return array();
		}

		// Get this chat.
		$this_chat = array_key_exists( $chat_id, $user_meta ) ? $user_meta[ $chat_id ] : array();

		// If we're looking at one of the first prompts, or if it's an odd-numbered prompt, just return an empty array as no links were shown.
		// @todo: If we're not hiding the first reply, then this will need to change.
		if ( $prompt_id < 3 || $prompt_id % 2 == 0 ) {
			return array();
		}

		// To go from prompt_id to link_id. 3 => (( 3 + 1 ) / 2) - 2 = 0. And 7 => (( 7 + 1 ) / 2) - 2 = 2. etc.
		$links_shown_id = ( ( $prompt_id + 1 ) / 2 ) - 2;

		// If we don't have a 'links_shown' array, or this specific $links_shown_id, return an empty array.
		// This might occur if for some reason no links were shown to the user based on what the LLM replied.
		if ( ! isset( $this_chat['links_shown'] ) || ! isset( $this_chat['links_shown'][ $links_shown_id ] ) ) {
			return array();
		}

		$links = $this_chat['links_shown'][ $links_shown_id ];

		if ( empty( $links ) ) {
			return array();
		}

		// Now we fetch these links from the db. We need to convert the array of IDs into a CSV of IDs.
		$link_ids_as_csv = implode( ',', $links );

		// Sanitize this as a string of integers and commas.
		$link_ids_as_csv = self::sanitize_integer_list( $link_ids_as_csv );

		$bookmarks = get_bookmarks(
			array(
				'include'        => $link_ids_as_csv,
				'hide_invisible' => 0,
			)
		);

		if ( empty( $bookmarks ) ) {
			return array();
		}

		$usable_links = array();

		foreach ( $bookmarks as $bid => $bookmark ) {
			// Get the ID, URL, and Name for each bookmark.
			$usable_links[] = array(
				'id'    => $bookmark->link_id,
				'url'   => $bookmark->link_url,
				'title' => $bookmark->link_name,
			);
		}

		return $usable_links;
	}//end socrates_get_links_shown_for_specific_prompt()

	/**
	 * Sanitize a string to include only integers and commas.
	 *
	 * This function uses a regular expression to remove any character
	 * that is not a digit or a comma. It's useful for sanitizing strings
	 * that are meant to represent lists of integers, such as IDs or quantities.
	 * It adheres to security best practices by ensuring that the resulting string
	 * does not contain any harmful characters.
	 *
	 * @param string $input The string to be sanitized.
	 * @return string Sanitized string containing only integers and commas.
	 *
	 * @example
	 * // Correct usage:
	 * $sanitized_string = \UBC\CTLT\Socrates_Helpers::sanitize_integer_list("24,235,456765,56756");
	 *
	 * // Incorrect usage:
	 * $sanitized_string = sanitize_integer_list("fw323,2342,regerg,35345,!");
	 * // This will return "323,2342,35345,".
	 */
	public static function sanitize_integer_list( $input ) {

		// Explanation:
		// The pattern '/[^0-9,]+/' breaks down as follows:
		// - The '^' inside the brackets negates the character class, matching anything not in the list.
		// - '0-9' includes all digits.
		// - ',' includes the comma.
		// - '+' quantifier matches one or more of the preceding pattern.
		// This means replace everything that's not a digit or a comma with an empty string.

		// Perform the sanitization using preg_replace.
		$sanitized = preg_replace( '/[^0-9,]+/', '', $input );

		// Return the sanitized string.
		return $sanitized;
	}//end sanitize_integer_list()


	/**
	 * Builds the markup for the passed links.
	 *
	 * @param  array $links
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public static function get_socrates_links_output( $links = array() ) {

		if ( empty( $links ) ) {
			return '';
		}

		// Ensure this variable is available to the template. And cast it as an array.
		$links = (array) $links;

		ob_start();
		include UBC_SELFSOCV3_DIR . 'inc/public/parts/public/chat-links.php';
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}//end get_socrates_links_output()

	/**
	 * Generates combinations of the given words array such that the length of each combination
	 * is either the full length of the array, length of the array minus one, or length of the array minus two.
	 *
	 * @param array $words The words to generate combinations for.
	 * @return array The generated combinations.
	 */
	public static function socrates_generate_combinations( $words = array() ) {

		$results   = array();
		$wordCount = count( $words );

		// If there are 1 or 2 words only, simply return permutations of them.
		if ( $wordCount <= 2 ) {
			foreach ( self::socrates_generate_permutations( $words ) as $combination ) {
				$results[] = implode( ' ', $combination );
			}
			return $results;
		}

		// Get all combinations where all words are used.
		foreach ( self::socrates_generate_permutations( $words ) as $combination ) {
			$results[] = implode( ' ', $combination );
		}

		// Get all combinations where all the words minus one are used.
		foreach ( $words as $word ) {
			$subset = array_diff( $words, array( $word ) );
			foreach ( self::socrates_generate_permutations( $subset ) as $combination ) {
				$results[] = implode( ' ', $combination );
			}
		}

		// Get all combinations where all the words minus two are used.
		foreach ( $words as $firstWord ) {
			foreach ( array_diff( $words, array( $firstWord ) ) as $secondWord ) {
				$subset = array_diff( $words, array( $firstWord, $secondWord ) );
				foreach ( self::socrates_generate_permutations( $subset ) as $combination ) {
					$results[] = implode( ' ', $combination );
				}
			}
		}

		return $results;
	}//end socrates_generate_combinations()

	/**
	 * Generates the links for the given response from the LLM if and only if we're showing links.
	 *
	 * Usage: $links = \UBC\CTLT\Socrates_Helpers::get_links_for_response( $llm_response_string );
	 *
	 * @param  string $llm_response_string The response from the LLM for which we're generating links.
	 *
	 * @return array
	 * @since 3.0.1
	 */
	public static function get_links_for_response( $llm_response_string ) {

		// If we're not using links - determined by the socratic_hide_links_in_reply option, return an empty array.
		if ( 1 === absint( get_option( 'socratic_hide_links_in_reply' ) ) ) {
			return array(
				'links_for_html' => array(),
				'link_ids'       => array(),
			);
		}

		// We use Rake to get the keywords of the LLM response. This helps us get somewhat appropriate links.
		// @TODO: Allow these to always be random? Set an option.
		$rake          = RakePlus::create( $llm_response_string, 'en_US' );
		$phrase_scores = $rake->sortByScore( 'desc' )->scores();

		// And we only want the top phrase (it's the first array key)
		$keywords = array_key_first( $phrase_scores );

		// Now, for searching, we need to wrap each of the keywords with a % character.
		// Currently it's a string. So convert to an array.
		$keywords_as_array = explode( ' ', $keywords );

		// Limit this to no more than 4 words. Otherwise the search query becomes bonkers.
		$max_four_keywords = array_slice( $keywords_as_array, 0, 4 );

		// We need to use some methods from the wpdb class.
		global $wpdb;

		// Now wrap in %
		$wildcard_keywords = array_map(
			function ( $keyword ) use ( $wpdb ) {
				return '%' . $wpdb->esc_like( $keyword ) . '%';
			},
			$max_four_keywords
		);

		// What's really fun is that if you use a search such as %game% %development% %companies% you
		// won't get results for "companies who do game development" because the keywords aren't in
		// the 'correct' order. So when we build our query we need to look for the keywords in all the
		// possible orders. This isn't awful because there aren't going to be too many keywords, but
		// it will make for a messy query.

		// So, first, we need to build all the possible orders of the keywords. So, if we have
		// array( %word1%, %word2%, %word3% ) then we need to build an array of arrays that looks
		// something like array( array(1,2,3), array(2,1,3), array(2,3,1), array(1,3,2), array(3,1,2), array(3,2,1) ).

		// But that might not be enough as that would require all the keywords to be present. So let's
		// make it a little more likely a result will be found. Let's reduce the number of keywords by
		// one and fetch all results with either ALL the keywords in any order, or all of them minus one
		// in any order.
		$keyword_combos = self::socrates_generate_combinations( $wildcard_keywords );

		// With each of the keyword combos, we need to build a query. The keyword combos each need to be
		// looked for in the link name or description. So that needs to look like a bunch of
		// OR `link_description` LIKE '%a% %b% %c%'
		// OR `link_description` LIKE '%b% %a% %c%'
		// OR `link_description` LIKE '%c% %b% %a%' ... etc (and then equivalents for `link_name`)

		// This is how the query starts
		$query_string = "SELECT * FROM {$wpdb->prefix}links WHERE (`link_description` LIKE '$keyword_combos[0]' OR `link_name` LIKE '$keyword_combos[0]'";

		// Now append the keyword combos to the query
		foreach ( $keyword_combos as $kid => $keyword_combo ) {

			// We've already done the first in the outset (to make our lives easier with the strings
			// starting with ' OR'), so skip it.
			if ( 0 === $kid ) {
				continue;
			}

			$query_string .= " OR `link_description` LIKE '$keyword_combo' OR `link_name` LIKE '$keyword_combo'";

		}

		// Now add the last pieces.
		$query_string .= ") AND `link_visible` = 'Y'";
		$query_string .= ' ORDER BY `link_id` ASC LIMIT 3;';

		$result = $wpdb->get_results( $query_string );

		$links_for_html = array();
		$link_ids       = array();

		// If we have some search results for the links, we show them to the user.
		if ( $result && is_array( $result ) && count( $result ) > 0 ) {
			foreach ( $result as $rid => $link_post_object ) {
				$links_for_html[] = array(
					'url'   => $link_post_object->link_url,
					'title' => $link_post_object->link_name,
				);
				$link_ids[]       = $link_post_object->link_id;
			}
		}

		$response_links                   = array();
		$response_links['links_for_html'] = $links_for_html;
		$response_links['link_ids']       = $link_ids;

		return $response_links;
	}//end get_links_for_response()


	/**
	 * Generates all permutations of the given array.
	 *
	 * @param array $items The items to generate permutations for.
	 * @param array $perms Used internally for recursion. Represents the current permutation being generated.
	 * @return array The generated permutations.
	 */
	public static function socrates_generate_permutations( $items, $perms = array() ) {

		// If $items is empty, we've reached a terminal state in our recursive generation.
		// This means we have a complete permutation, so we return it.
		if ( empty( $items ) ) {
			return array( $perms );
		}

		$result = array();

		// We'll iterate over the items, selecting each one as a starting point for a permutation.
		// By iterating in reverse, we ensure that the order in which items were initially provided is respected.
		for ( $i = count( $items ) - 1; $i >= 0; --$i ) {

			// We'll work with copies of the arrays to ensure that modifications in this loop iteration
			// don't affect the original data or other loop iterations.
			$new_items = $items;
			$new_perms = $perms;

			// The current item is removed from new_items and prepended to new_perms.
			// This is the next item in our permutation.
			list( $foo ) = array_splice( $new_items, $i, 1 );
			array_unshift( $new_perms, $foo );

			// By calling this function recursively with one less item,
			// we continue building the permutation for the remaining items.
			$result = array_merge( $result, self::socrates_generate_permutations( $new_items, $new_perms ) );
		}

		// All the permutations formed using each item as a starting point are aggregated and returned.
		return $result;
	}//end socrates_generate_permutations()


	/**
	 * Generates a unique chat ID based on the current time, the user ID, and a random number.
	 *
	 * Usage: $new_chat_id = \UBC\CTLT\Socrates_Helpers::socrates_generate_chat_id();
	 *
	 * @return string a unique chat ID
	 * @since 3.0.1
	 */
	public static function socrates_generate_chat_id() {

		$chat_id = md5( time() . get_current_user_id() . rand() );

		// Escape this chat ID.
		$chat_id = esc_attr( $chat_id );

		return $chat_id;
	}//end socrates_generate_chat_id()


	/**
	 * Generates a starting meta state.
	 *
	 * @return array
	 * @since 3.0.1
	 */
	public static function socrates_generate_starting_meta_state() {

		$initial_prompt = get_option( 'socratic_socratic_starting_prompt' );

		// @TODO: Get this generated based on the initial prompt and/or a setting in the dashboard.
		$default_initial_reply = 'Question 1: Name a digital world issue that interests you in 5 words or under.';
		$initial_reply         = get_option( 'socratic_socratic_initial_reply', $default_initial_reply );

		$default_chat = array(
			'messages'        => array(
				array(
					'role'    => 'user',
					'content' => $initial_prompt,
				),
				array(
					'role'    => 'assistant',
					'content' => $initial_reply,
				),
			),
			'summary'         => '',
			'start_date_time' => current_time( 'mysql' ),
			'links_shown'     => array(),
			'deleted'         => false,
		);

		return $default_chat;
	}//end socrates_generate_starting_meta_state()

	/**
	 * Determines if two strings are functionally equal by ignoring line breaks, whitespace, and other non-alphanumeric characters.
	 *
	 * This function helps to check if two strings have the same content when non-significant
	 * whitespace (like line breaks, spaces, and tabs) are ignored. This can be useful when
	 * comparing strings that might be formatted differently but have the same effective content.
	 *
	 * Usage: $are_strings_functionally_equal = \UBC\CTLT\Socrates_Helpers::are_strings_functionally_equal( $str1, $str2 );
	 *
	 * @since 3.0.1
	 *
	 * @param string $str1 The first string to compare.
	 * @param string $str2 The second string to compare.
	 * @return bool True if the strings are functionally equal, false otherwise.
	 */
	public static function are_strings_functionally_equal( $str1, $str2 ) {
		// Removes all whitespace including line breaks.
		$clean_str1 = preg_replace( '/\s+/', '', $str1 );
		$clean_str2 = preg_replace( '/\s+/', '', $str2 );

		// Compare the cleaned versions of both strings.
		return $clean_str1 === $clean_str2;
	}//end are_strings_functionally_equal()

	/**
	 * Limit text to a certain number of words. Will truncate the text and append '...' to the end.
	 *
	 * @param  [type] $text
	 * @param  [type] $limit
	 *
	 * Usage: $limited_text = \UBC\CTLT\Socrates_Helpers::limit_text( $text, 5 );
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public static function limit_text( $text, $limit ) {

		if ( str_word_count( $text, 0 ) > $limit ) {
			$words = str_word_count( $text, 2 );
			$pos   = array_keys( $words );
			$text  = substr( $text, 0, $pos[ $limit ] ) . '...';
		}

		return $text;
	}//end limit_text()


	/**
	 * Fetches the 'chat_id' parameter from the URL.
	 *
	 * This function retrieves the 'chat_id' parameter from the current window's URL,
	 * ensuring that it contains only letters and/or numbers. If the parameter either
	 * doesn't exist or doesn't match the expected format, the function will return false.
	 *
	 * Usage: $chat_id = \UBC\CTLT\Socrates_Helpers::socrates_get_chat_id_from_url();
	 *
	 * @return string|false The sanitized chat_id or false if not valid or not set.
	 */
	public static function socrates_get_chat_id_from_url() {

		// If 'chat_id' is not set, return false.
		if ( ! isset( $_GET['chat_id'] ) ) {
			return false;
		}

		$chat_id = $_GET['chat_id'];

		// sanitize the chat_id
		$chat_id = self::socrates_sanitize_chat_id( $chat_id );

		return $chat_id;
	}//end socrates_get_chat_id_from_url()


	/**
	 * Sanitizes the 'chat_id'
	 *
	 * Usage: $chat_id = \UBC\CTLT\Socrates_Helpers::socrates_sanitize_chat_id( $chat_id );
	 *
	 * @param string $chat_id
	 * @return string|false The sanitized chat_id or false if not valid or not set.
	 * @since 3.0.1
	 */
	public static function socrates_sanitize_chat_id( $chat_id ) {

		// If 'chat_id' doesn't match the expected format, return false.
		if ( ! preg_match( '/^[a-zA-Z0-9]+$/', $chat_id ) ) {
			return false;
		}

		return $chat_id;
	}//end socrates_sanitize_chat_id()


	/**
	 * Retrieve and log the available models from ChatGPT using the API key stored in options.
	 * Called if llm-quick-check=models is in the URL
	 *
	 * @return array The available models
	 * @since 3.0.1
	 */
	public static function socrates_get_chatgpt_models() {

		$api_key = get_option( 'socratic_chatgpt_api_key' );

		$client = \OpenAI::client( $api_key );

		$response = $client->models()->list();
		$response = $response->toArray()['data'];

		file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( $response, true ), FILE_APPEND ); // phpcs:ignore
	}//end socrates_get_chatgpt_models()

	/**
	 * Retrieve an image from ChatGPT using the API key stored in options.
	 * Called if llm-quick-check=image is in the URL
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public static function socrates_get_image_from_prompt( $image_prompt = '' ) {

		$image_prompt = sanitize_text_field( $image_prompt );

		// First, hit regular ChatGPT to get the image prompt. Then pass that to the image resource.

		$api_key      = get_option( 'socratic_chatgpt_api_key' );
		$model_to_use = get_option( 'socratic_chatgpt_model' );

		$client = \OpenAI::client( $api_key );

		$result = $client->chat()->create(
			array(
				'model'    => $model_to_use,
				'messages' => array(
					array(
						'role'    => 'user',
						'content' => $image_prompt,
					),
				),
			)
		);

		$generated_image_prompt = self::get_response_string_external( $result );

		$client = \OpenAI::client( $api_key );

		$response = $client->images()->create(
			array(
				'model'           => 'dall-e-3',
				'prompt'          => $generated_image_prompt,
				'n'               => 1,
				'size'            => '1024x1024',
				'response_format' => 'url',
				'quality'         => 'hd',
			)
		);

		$response = $response->toArray()['data'];

		wp_die( '<img src="' . esc_url( $response[0]['url'] ) . '" alt="' . sanitize_text_field( $generated_image_prompt ) . '" />' );
	}//end socrates_get_image_from_prompt()


	/**
	 * Retrieve an audio file from ChatGPT using the API key stored in options.
	 * Called if llm-quick-check=audio is in the URL
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public static function socrates_get_audio_of_chat() {

		$api_key = get_option( 'socratic_chatgpt_api_key' );

		$client = \OpenAI::client( $api_key );

		$speech_params = array(
			'model' => 'tts-1-hd',
			'input' => 'Question 2: Thinking about loot boxes in video games, how do you think the current legal framework surrounding consumer protection and gambling applies to this issue? Consider aspects such as age restrictions, transparency, and the potential for addiction.',
			'voice' => 'shimmer', // alloy, echo, fable, onyx, nova, shimmer
		);

		$response = $client->audio()->speech( $speech_params );

		// Check if the API call was successful
		if ( isset( $response['error'] ) ) {
			wp_die( $response['error'] );
		}

		// The response is just the MP3 content.
		$mp3_content = $response;

		// Specify the path and filename for the audio file
		$upload_dir      = wp_upload_dir();
		$upload_path     = $upload_dir['basedir'];
		$audio_file_path = trailingslashit( $upload_path ) . $speech_params['voice'] . '-audio-' . time() . '.mp3';

		// Save the binary data to a file
		file_put_contents( $audio_file_path, $mp3_content );

		// At this point, you have the audio file saved on your server
		// You can provide a link to download it or embed it in a webpage
		wp_die( 'Audio file created at: ' . $audio_file_path );
	}//end socrates_get_audio_of_chat()


	/**
	 * A way to access the resopnse string from ChatGPT statically.
	 *
	 * Usage: $response = \UBC\CTLT\Socrates_Helpers::get_response_string_external( $response );
	 *
	 * @param  [type] $llm_response_data
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public static function get_response_string_external( $llm_response_data ) {

		$response_string = false;

		foreach ( $llm_response_data->choices as $result ) {
			// Only want the last response, I THINK.
			if ( ! isset( $result->finishReason ) || 'stop' !== $result->finishReason ) {
				continue;
			}

			$response_string = $result->message->content;
		}

		if ( false === $response_string ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Response was false.' ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: ' . __FILE__ . ' :: ' . __LINE__;
		}

		return $response_string;
	}//end get_response_string_external()


	/**
	 * Get the socratic chats for the passed User ID (or the current user if none is passed).
	 *
	 * Usage: $socratic_chats = \UBC\CTLT\Socrates_Helpers::get_socratic_chats_for_user( $user_id );
	 *
	 * @param  int $user_id
	 *
	 * @return array $socratic_chats An array of socratic chats for this user. Empty array if none exist.
	 * @since 3.0.1
	 */
	public static function get_socratic_chats_for_user( $user_id = 0 ) {

		// If no user ID is passed, use the current user
		if ( ! $user_id || absint( $user_id ) === 0 ) {
			$user_id = get_current_user_id();
		}

		// Sanitize
		$user_id = absint( $user_id );

		// Get user meta for our socratic chats key. This is where the data is stored.
		$user_meta = get_user_meta( $user_id, 'socratic_chats', true );

		// If we don't have this key in user meta, send back an empty array.
		$socratic_chats = ( ! empty( $user_meta ) ) ? $user_meta : array();

		// If this is a non-admin, filter out the deleted chats.
		if ( ! user_can( $user_id, 'manage_options' ) ) {
			foreach ( $socratic_chats as $key => $chat ) {
				if ( false !== $chat['deleted'] ) {
					unset( $socratic_chats[ $key ] );
				}
			}
		}

		return $socratic_chats;
	}//end get_socratic_chats_for_user()

	/**
	 * Determine if this is a new chat or not.
	 *
	 * New chat if:
	 * - User doesn't have any socratic chats
	 * - If the URL has new-socratic-chat=1
	 *
	 * Usage: $new_chat = \UBC\CTLT\Socrates_Helpers::is_this_a_new_chat();
	 *
	 * @return bool
	 * @since 3.0.1
	 */
	public static function is_this_a_new_chat() {

		// No chats? New chat.
		$socratic_chats = self::get_socratic_chats_for_user();

		if ( empty( $socratic_chats ) ) {
			return true;
		}

		// Specifically requesting a new chat? New chat.
		if ( isset( $_GET['new-socratic-chat'] ) && 1 === absint( $_GET['new-socratic-chat'] ) ) {
			return true;
		}

		// Not a new chat. (This will therefore go on to load the user's most recent chat)
		return false;
	}//end is_this_a_new_chat()


	/**
	 * Determine the ID of the chat to load.
	 *
	 * Logic:
	 * - If there's a chat ID in the URL, use that. (this is checked for user validity elsewhere)
	 * - If there's no chat ID in the URL, use the most recent chat for the current user.
	 * - If this is a new chat, we generate a new one using Socrates_Helpers::socrates_generate_chat_id and return that.
	 *
	 * Usage: $chat_id_to_load = \UBC\CTLT\Socrates_Helpers::get_chat_id_to_load();
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public static function get_chat_id_to_load() {

		// If this is a new chat, generate a new chat ID and return that.
		if ( self::is_this_a_new_chat() ) {
			return self::socrates_generate_chat_id();
		}

		// If there's a chat ID in the URL, use that. (this is checked for user validity elsewhere)
		$chat_id_from_url = self::socrates_get_chat_id_from_url();

		if ( $chat_id_from_url ) {
			return $chat_id_from_url;
		}

		// If there's no chat ID in the URL, use the most recent chat for the current user.
		$socratic_chats = self::get_socratic_chats_for_user();

		if ( ! empty( $socratic_chats ) ) {
			return array_key_last( $socratic_chats );
		}
	}//end get_chat_id_to_load()


	/**
	 * Determine if the passed user can view the passed chat.
	 *
	 * Admins can always view all chats.
	 * User can view their own chats.
	 *
	 * Usage: $can_user_view_chat = \UBC\CTLT\Socrates_Helpers::can_user_view_chat( $user_id, $chat_id );
	 *
	 * @param  int    $user_id
	 * @param  string $chat_id
	 *
	 * @return bool
	 * @since 3.0.1
	 */
	public static function can_user_view_chat( $user_id = 0, $chat_id = '' ) {

		// If no user ID is passed, use the current user
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Sanitize
		$user_id = absint( $user_id );
		$chat_id = self::socrates_sanitize_chat_id( $chat_id );

		// Admins should be able to see all chats.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Get user meta for our socratic chats key. This is where the data is stored.
		$socratic_chats = get_user_meta( $user_id, 'socratic_chats', true );

		// If we don't have this key in user meta, return false.
		if ( empty( $socratic_chats ) || ! array_key_exists( $chat_id, $socratic_chats ) ) {
			return false;
		}

		// If this is a deleted chat, return false.
		if ( array_key_exists( 'deleted', $socratic_chats[ $chat_id ] ) && false !== $socratic_chats[ $chat_id ]['deleted'] ) {
			return false;
		}

		// Chat ID is valid, and is in the passed user ID's meta, so return true.
		return true;
	}//end can_user_view_chat()


	/**
	 * A generic helper to output an error message based on the passed error code.
	 *
	 * Usage: \UBC\CTLT\Socrates_Helpers::show_error_message( $message_code );
	 *
	 * @param  string $message_code
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public static function show_error_message( $message_code = '' ) {

		// Sanitize
		$message_code = sanitize_text_field( $message_code );

		$known_error_codes = array(
			'user_cant_view_chat',
			'must_log_in',
		);

		$default_mesage = '<p>An unknown error occurred : ' . esc_html( $message_code ) . '</p>';

		if ( ! in_array( $message_code, $known_error_codes, true ) ) {
			return wp_kses_post( $default_mesage );
		}

		switch ( $message_code ) {

			case 'user_cant_view_chat':
				$url     = strtok( $_SERVER['REQUEST_URI'], '?' ) . '?new-socratic-chat=1';
				$message = "<p>The requested chat is not available for your user account. <a href='" . $url . "'>Start a new chat</a>.</p>";

				return wp_kses_post( $message );

			case 'must_log_in':
				$redirect_url            = strtok( $_SERVER['REQUEST_URI'], '?' );
				$login_url_with_redirect = wp_login_url( $redirect_url );
				$message                 = "<p>You must be signed in to use this tool. <a href='" . esc_url( $login_url_with_redirect ) . "'>Sign In</a>.</p>";

				return wp_kses_post( $message );

			default:
				return $default_mesage;
		}
	}//end show_error_message()


	/**
	 * A standalone static method to fetch feeds from the RSS URLs in the
	 * settings.
	 *
	 * Usage: $feeds = \UBC\CTLT\Socrates_Helpers::fetch_feeds();
	 *
	 * @return array
	 * @since 3.0.1
	 */
	public static function fetch_feeds() {

		// Include the feed fetcher class file.
		require_once UBC_SELFSOCV3_DIR . 'inc/admin/class-socrates-fetch-feeds.php';

		// Initialize feed fetcher class.
		$socrates_feeds = new \UBC\CTLT\Socrates_Fetch_Feeds();

		return $socrates_feeds->output();
	}//end fetch_feeds()

	/**
	 * A standalone static method to create the bookmarks from the passed categorized and scored links.
	 *
	 * Usage: $created_bookmarks = \UBC\CTLT\Socrates_Helpers::create_bookmarks( $categorized_and_scored_links );
	 *
	 * @param array $categorized_and_scored_links
	 * @since 3.0.1
	 * @return int Number of bookmarks created.
	 */
	public static function create_bookmarks( $categorized_and_scored_links ) {

		// Include the bookmark creator class file.
		require_once UBC_SELFSOCV3_DIR . 'inc/admin/class-socrates-bookmark-creator.php';

		// Initialize bookmark creator class.
		$socrates_bookmark_creator = new \UBC\CTLT\Socrates_Bookmark_Creator( $categorized_and_scored_links );

		return count( $categorized_and_scored_links );
	}//end create_bookmarks()


	/**
	 * A standalone static method to create the NotW post based on the unpublished links.
	 *
	 * Usage: $newly_created_post_id = \UBC\CTLT\Socrates_Helpers::create_notw_post_from_unpublished_links();
	 *
	 * @since 3.0.1
	 * @return void
	 */
	public static function create_notw_post_from_unpublished_links() {

		// Include the NotW post creator class file.
		require_once UBC_SELFSOCV3_DIR . 'inc/admin/class-socrates-notw-post-creator.php';

		// Initialize NotW post creator class.
		$newly_created_post_id = new \UBC\CTLT\Socrates_Notw_Post_Creator();

		return $newly_created_post_id;
	}//end create_notw_post_from_unpublished_links()

	public static function socrates_add_cron_schedules( $schedules ) {
		$schedules['every_other_day'] = array(
			'interval' => 172800, // 2 days in seconds
			'display'  => __( 'Every Other Day' ),
		);
		return $schedules;
	}

	public static function socrates_activate() {
		self::socrates_schedule_feed_fetch();
	}

	public static function socrates_deactivate() {
		wp_clear_scheduled_hook( 'socrates_fetch_feeds' );
	}

	public static function socrates_schedule_feed_fetch() {
		$cadence = get_option( 'socratic_link_collection_cadence', 'Daily at Midnight' );

		wp_clear_scheduled_hook( 'socrates_fetch_feeds' );

		switch ( $cadence ) {
			case 'Daily at Midnight':
				wp_schedule_event( strtotime( 'today 23:59:59' ), 'daily', 'socrates_fetch_feeds' );
				break;
			case 'Every other day at Midnight':
				wp_schedule_event( strtotime( 'today 23:59:59' ), 'every_other_day', 'socrates_fetch_feeds' );
				break;
			case 'Every Sunday at Midnight':
				wp_schedule_event( strtotime( 'next Sunday 23:59:59' ), 'weekly', 'socrates_fetch_feeds' );
				break;
		}
	}

	public static function socrates_update_feed_schedule( $old_value, $new_value, $option ) {
		self::socrates_schedule_feed_fetch();
	}

	public static function socrates_cron_fetch_feeds() {

		try {
			$feeds = self::fetch_feeds();

			$created_bookmarks = self::create_bookmarks( $feeds );

			update_option( 'socratic_last_feed_fetch', current_time( 'mysql' ) );
		} catch ( Exception $e ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Socrates feed fetch error: ' . $e->getMessage() ), true ), FILE_APPEND ); // phpcs:ignore
		}
	}
}//end class
