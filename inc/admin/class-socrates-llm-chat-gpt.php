<?php
/**
 * Class Socrates_LLM_Chat_Gpt.
 *
 * Must implement:
 *
 * get_client()
 * get_request()
 * make_request()
 * get_response_string()
 *
 * @todo Make this into an abstract class
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

/**
 * Socrates_LLM_Chat_Gpt Class.
 *
 * Sets up the client and comms method to communicate with ChatGPT.
 */
class Socrates_LLM_Chat_Gpt {

	/**
	 * Will be filled with the API key stored in the options.
	 */
	var $api_key = '';

	/**
	 * Initalize ourselves.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function __construct() {

		$this->set_api_key();
	}//end __construct()


	/**
	 * Fetches and sets the API key stored in the settings.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_api_key() {

		$this->api_key = esc_attr( get_option( 'socratic_chatgpt_api_key' ) );
	}//end set_api_key()

	/**
	 * This method is called within the Socrates_Send_LLM_Request class and must return
	 * the client which that class can use to them send a request.
	 *
	 * @return mixed
	 * @since 3.0.1
	 */
	public function get_client() {

		$client = \OpenAI::client( $this->api_key );

		return $client;
	}//end get_client()


	/**
	 * Formulate the request we're going to send.
	 *
	 * @param  array $request_params
	 *                   'messages' : the full set of messages sent to ChatGPT
	 *                   'model'  : the model we wish to use
	 *                   This is filtered, so other data may be available.
	 * @param  bool  $use_json_mode Optional. Whether to request JSON output mode. Defaults to false.
	 *
	 * @return mixed the fully-formed request we're going to make
	 * @since
	 */
	public function get_request( $request_params = array(), $use_json_mode = false ) {

		$request = array(
			'model'    => $request_params['model'],
			'messages' => $request_params['messages'],
		);

		// Add JSON mode parameter if requested.
		if ( $use_json_mode ) {
			$request['response_format'] = array( 'type' => 'json_object' );
		}

		return $request;
	}//end get_request()


	/**
	 * Make the actual request and then send back the data returned by
	 * the LLM
	 *
	 * @param  mixed $request
	 *
	 * @return mixed the data returned by the LLM
	 * @since 3.0.1
	 */
	public function make_request( $client, $request ) {

		file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'OpenAI Request' => $request ), true ), FILE_APPEND ); // phpcs:ignore

		try {

			$data = $client->chat()->create( $request );
			return $data;

		} catch ( \OpenAI\Exceptions\ErrorException $e ) {

			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'API Error' => $e->getMessage() ), true ), FILE_APPEND ); // phpcs:ignore

			return array(
				'error'   => true,
				'message' => $e->getMessage(),
			);

		} catch ( \Exception $e ) {

			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Unexpected Error' => $e->getMessage() ), true ), FILE_APPEND ); // phpcs:ignore

			return array(
				'error'   => true,
				'message' => 'An unexpected error occurred: ' . $e->getMessage(),
			);
		}
	}//end make_request()


	/**
	 * The response from the LLM contains lots of stuff we don't need.
	 * Just return the response string or the extracted parts (reasoning/response).
	 *
	 * @param object $llm_response_data The response object from the OpenAI client.
	 * @param bool   $use_json_mode Optional. Whether JSON mode was used for the request. Defaults to false.
	 *
	 * @return string|array|null If JSON mode, returns parsed array or null/error string on failure.
	 *                            If not JSON mode, returns array ['reasoning' => string|null, 'response' => string].
	 *                            Returns error string if the initial response indicates an error.
	 * @since 3.0.1 (Modified in 3.5.0 to handle reasoning)
	 */
	public function get_response_string( $llm_response_data, $use_json_mode = false ) {

		// Test if we have caught an error during the request itself.
		if ( is_array( $llm_response_data ) && isset( $llm_response_data['error'] ) && true === $llm_response_data['error'] ) {
			return 'Error: ' . $llm_response_data['message'];
		}

		// Check if the response structure is valid.
		if ( ! isset( $llm_response_data->choices[0]->message->content ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Invalid OpenAI Response Structure' => $llm_response_data ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: Invalid response structure from OpenAI.';
		}

		// Get the primary response content.
		$raw_content = $llm_response_data->choices[0]->message->content;

		// --- Reasoning Extraction Logic ---
		$reasoning     = null;
		$main_response = $raw_content;

		// Default regex pattern for reasoning tags.
		$default_pattern = '/^\s*<(think|scratchpad|rationale)>(.*?)<\/\1>\s*(.*)/si';

		/**
		 * Filters the regex pattern used to extract LLM reasoning tags.
		 *
		 * @since 3.5.0
		 * @param string $pattern Default regex pattern.
		 * @param object $llm_instance The instance of the current LLM class (Socrates_LLM_Chat_Gpt).
		 */
		$reasoning_pattern = apply_filters( 'socrates_reasoning_extraction_pattern', $default_pattern, $this );

		// Perform regex match if a pattern is provided.
		if ( ! empty( $reasoning_pattern ) && preg_match( $reasoning_pattern, $raw_content, $matches ) && count( $matches ) === 4 ) {
			$reasoning     = trim( $matches[2] ); // Captured reasoning content.
			$main_response = trim( $matches[3] ); // Captured main response content.
		}

		/**
		 * Filters the extracted reasoning and main response parts from raw LLM output.
		 *
		 * @since 3.5.0
		 * @param array  $extracted_parts { Array containing 'reasoning' and 'main_response' }
		 * @param string $raw_content The raw content string received from the LLM.
		 * @param bool   $use_json_mode Whether the request was made in JSON mode.
		 * @param object $llm_instance The instance of the current LLM class (Socrates_LLM_Chat_Gpt).
		 */
		$extracted_parts = apply_filters(
			'socrates_extract_reasoning_parts',
			array(
				'reasoning'     => $reasoning,
				'main_response' => $main_response,
			),
			$raw_content,
			$use_json_mode,
			$this
		);

		// Use potentially filtered parts.
		$reasoning     = isset( $extracted_parts['reasoning'] ) ? $extracted_parts['reasoning'] : null;
		$main_response = isset( $extracted_parts['main_response'] ) ? $extracted_parts['main_response'] : $raw_content;
		// --- End Reasoning Extraction ---

		// If JSON mode was used, decode and validate the main response part.
		if ( $use_json_mode ) {
			$decoded_json = json_decode( $main_response, true ); // Decode as associative array.

			// Check for JSON decoding errors.
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'ChatGPT JSON Decode Error' => json_last_error_msg(), 'Content Received' => $main_response ), true ), FILE_APPEND ); // phpcs:ignore
				return null; // Indicate failure.
			}

			// Basic validation: Ensure it's an array.
			if ( ! is_array( $decoded_json ) ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'ChatGPT JSON Validation Error' => 'Decoded JSON is not an array.', 'Content Received' => $main_response ), true ), FILE_APPEND ); // phpcs:ignore
				return null; // Indicate failure.
			}

			return $decoded_json; // Return the successfully decoded array.
		}

		// If not JSON mode, return the extracted parts.
		return array(
			'reasoning' => $reasoning,
			'response'  => $main_response,
		);
	}//end get_response_string()
}
