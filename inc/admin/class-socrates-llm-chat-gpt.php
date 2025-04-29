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
	 * Just return the response string.
	 *
	 * @param  array $llm_response_data
	 * @param  bool  $use_json_mode Optional. Whether JSON mode was used for the request. Defaults to false.
	 *
	 * @return string|array|null The response string, parsed JSON array, or null/error string on failure.
	 * @since 3.0.1
	 */
	public function get_response_string( $llm_response_data, $use_json_mode = false ) {

		$response_string = false;

		// Test if we have caught an error.
		if ( is_array( $llm_response_data ) && isset( $llm_response_data['error'] ) && true === $llm_response_data['error'] ) {
			return 'Error: ' . $llm_response_data['message'];
		}

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

		// If JSON mode was used, decode and validate.
		if ( $use_json_mode ) {
			$decoded_json = json_decode( $response_string, true ); // Decode as associative array.

			// Check for JSON decoding errors.
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'ChatGPT JSON Decode Error' => json_last_error_msg(), 'Content Received' => $response_string ), true ), FILE_APPEND ); // phpcs:ignore
				return null; // Indicate failure.
			}

			// Basic validation: Ensure it's an array.
			if ( ! is_array( $decoded_json ) ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'ChatGPT JSON Validation Error' => 'Decoded JSON is not an array.', 'Content Received' => $response_string ), true ), FILE_APPEND ); // phpcs:ignore
				return null; // Indicate failure.
			}

			return $decoded_json; // Return the successfully decoded array.
		}

		return $response_string;
	}//end get_response_string()
}
