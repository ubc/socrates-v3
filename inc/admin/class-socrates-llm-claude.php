<?php

/**
 * Class Socrates_LLM_Claude.
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
 * Socrates_LLM_Claude Class.
 *
 * Sets up the client and comms method to communicate with Anthopic's Claude.
 */
class Socrates_LLM_Claude {

	/**
	 * Will be filled with the API key stored in the options.
	 */
	var $api_key = '';

	/**
	 * Will be filled with the model stored in the options.
	 */
	var $model = '';

	/**
	 * Will be filled with the model version stored in the options.
	 */
	var $model_version = '';

	/**
	 * Initalize ourselves.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function __construct() {

		$this->set_api_key();
		$this->set_model();
		$this->set_model_version();
	}//end __construct()


	/**
	 * Fetches and sets the API key stored in the settings.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_api_key() {

		$this->api_key = esc_attr( get_option( 'socratic_anthropic_api_key' ) );
	}//end set_api_key()


	/**
	 * Fetches the model saved in the settings and stores to class property.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_model() {

		$this->model = esc_html( get_option( 'socratic_anthropic_model', 'claude-2' ) );
	}//end set_model()


	/**
	 * Fetches the model version saved in the settings and stores to class property.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_model_version() {

		$this->model_version = esc_html( get_option( 'socratic_anthropic_model_version', '2023-06-01' ) );
	}//end set_model_version()


	/**
	 * This method is called within the Socrates_Send_LLM_Request class and must return
	 * the client which that class can use to them send a request.
	 *
	 * @return mixed
	 * @since 3.0.1
	 */
	public function get_client() {

		$client = new \Alle_AI\Anthropic\AnthropicAPI( $this->api_key, $this->model_version );

		return $client;
	}//end get_client()


	/**
	 * Formulate the request we're going to send.
	 *
	 * @param  array $request_params
	 *                   'messages' : the full set of messages sent to the LLM
	 *                   'model'  : the model we wish to use
	 *                   This is filtered, so other data may be available.
	 *
	 * @param  bool  $use_json_mode Optional. Whether JSON mode is intended (affects response parsing). Defaults to false.
	 * @return mixed the fully-formed request we're going to make
	 * @since
	 */
	public function get_request( $request_params = array(), $use_json_mode = false ) {

		/*
		 * Anthropic's API requires a very specific request format.
		 * The request format is as follows:
		 * $request_to_send = array(
			'prompt' => '\n\nHuman: '.$prompt.'\n\nAssistant:', // Be sure to format prompt appropriately
			'model' => 'claude-2',
			'max_tokens_to_sample' => 300,
			'stop_sequences' => array("\n\nHuman:")
			);
		 *
		 */

		// We need to convert the messages array to the Anthropic format.
		$prompt = $this->generate_prompt_from_messages( $request_params['messages'] );

		$model = $this->model;

		// Currently hardcoded.
		// @todo: Make these settable somehow? Maybe?
		$max_tokens_to_sample = 4000;
		$stop_sequences       = array( "\n\nHuman:" );

		$request = array(
			'prompt'               => $prompt,
			'model'                => $model,
			'max_tokens_to_sample' => $max_tokens_to_sample,
			'stop_sequences'       => $stop_sequences,
		);

		return $request;
	}//end get_request()


	/**
	 * Converts the messages array to the Anthropic format. The messages array contains sub-arrays
	 * which contain the prompt and who wrote it (whether it's role: user or role: assistant). We need
	 * to convert that into a single string where the part the user wrote is prefixed with "\n\nHuman:" and
	 * the part the assistant wrote is prefixed with "\n\nAssistant:" and the prompt must end with
	 * "\n\nAssistant:" so that the LLM knows when to respond.
	 *
	 * @param  array $messages
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function generate_prompt_from_messages( $messages = array() ) {

		// Start with a blank prompt which we'll append to.
		$prompt = '';

		// Convert the messages array to the Anthropic format.
		foreach ( $messages as $message ) {

			$prefix = ( $message['role'] === 'assistant' ) ? "\n\nAssistant:" : "\n\nHuman:";

			$prompt .= $prefix . $message['content'];

		}

		// Add the end of the prompt.
		$prompt .= "\n\nAssistant:";

		return $prompt;
	}//end generate_prompt_from_messages()


	/**
	 * Make the actual request and then send back the data returned by
	 * the LLM
	 *
	 * @param  mixed $client
	 * @param  array $request
	 *          'model'    : the model we wish to use
	 *          'messages' : the full set of messages to send to the LLM
	 *
	 * @return mixed the data returned by the LLM
	 * @since 3.0.1
	 */
	public function make_request( $client, $request ) {

		$data = $client->generateText( $request );

		return $data;
	}//end make_request()


	/**
	 * The response from the LLM contains lots of stuff we don't need.
	 * Returns the response string, parsed JSON array, or extracted parts (reasoning/response).
	 *
	 * @param array|object $llm_response_data The response data from the Anthropic client.
	 * @param bool         $use_json_mode Optional. Whether JSON mode was used for the request. Defaults to false.
	 *
	 * @return string|array|null If JSON mode, returns parsed array or null/error string on failure.
	 *                            If not JSON mode, returns array ['reasoning' => string|null, 'response' => string].
	 *                            Returns error string if the initial response indicates an error.
	 * @since 3.0.0 (Modified in 3.5.0 to handle reasoning)
	 */
	public function get_response_string( $llm_response_data, $use_json_mode = false ) {

		// Test if we have caught an error during the request itself.
		if ( is_array( $llm_response_data ) && isset( $llm_response_data['error'] ) && is_array( $llm_response_data['error'] ) ) {
			// Assuming the error structure includes a message key.
			$error_message = isset( $llm_response_data['error']['message'] ) ? $llm_response_data['error']['message'] : 'Unknown Anthropic API error.';
			return 'Error: ' . $error_message;
		}

		// If the completion isn't part of the response, return an error.
		if ( ! isset( $llm_response_data['completion'] ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Invalid Claude Response Structure' => $llm_response_data ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: No completion found in Anthropic response.';
		}

		// Get the primary response content.
		$raw_content = $llm_response_data['completion'];

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
		 * @param object $llm_instance The instance of the current LLM class (Socrates_LLM_Claude).
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
		 * @param object $llm_instance The instance of the current LLM class (Socrates_LLM_Claude).
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
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Claude JSON Decode Error' => json_last_error_msg(), 'Content Received' => $main_response ), true ), FILE_APPEND ); // phpcs:ignore
				return null; // Indicate failure.
			}

			// Basic validation: Ensure it's an array.
			if ( ! is_array( $decoded_json ) ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Claude JSON Validation Error' => 'Decoded JSON is not an array.', 'Content Received' => $main_response ), true ), FILE_APPEND ); // phpcs:ignore
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
}//end class
