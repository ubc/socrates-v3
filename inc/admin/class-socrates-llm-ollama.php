<?php
/**
 * Class Socrates_LLM_Ollama.
 *
 * Handles communication with a local or remote Ollama instance using the OpenAI-compatible API endpoint.
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

/**
 * Socrates_LLM_Ollama Class.
 *
 * Sets up the client and communication methods for Ollama.
 */
class Socrates_LLM_Ollama {

	/**
	 * Ollama API Key (optional).
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * Ollama model name.
	 *
	 * @var string
	 */
	private $model = '';

	/**
	 * Ollama server base URL.
	 *
	 * @var string
	 */
	private $server_url = '';

	/**
	 * Initialize the Ollama handler.
	 *
	 * Fetches necessary options from WordPress settings.
	 */
	public function __construct() {
		$this->api_key    = esc_attr( get_option( 'socratic_ollama_api_key', '' ) );
		$this->model      = esc_attr( get_option( 'socratic_ollama_model', '' ) );
		$this->server_url = esc_url_raw( get_option( 'socratic_ollama_server_url', 'http://localhost:11434' ) );
	}

	/**
	 * Configures and returns the OpenAI client pointed at the Ollama server.
	 *
	 * @return \OpenAI\Client The configured client instance.
	 */
	public function get_client() {
		$base_uri = $this->server_url;

		// Ensure the base URI ends with /v1 as expected by the OpenAI PHP client library.
		if ( substr( $base_uri, -3 ) !== '/v1' ) {
			$base_uri = rtrim( $base_uri, '/' ) . '/v1';
		}

		$client = \OpenAI::factory()
			->withApiKey( $this->api_key ) // Pass the key, even if empty.
			->withBaseUri( $base_uri )
			->make();

		return $client;
	}

	/**
	 * Formats the request payload for the Ollama API (OpenAI format).
	 *
	 * @param array $request_params Associative array containing 'messages'.
	 * @param bool  $use_json_mode  Whether to request JSON output.
	 * @return array The formatted request array.
	 */
	public function get_request( $request_params, $use_json_mode = false ) {
		$request = array(
			'model'    => $this->model,
			'messages' => $request_params['messages'],
		);

		if ( $use_json_mode ) {
			$request['response_format'] = array( 'type' => 'json_object' );
		}

		return $request;
	}

	/**
	 * Sends the request to the Ollama API via the client.
	 *
	 * @param \OpenAI\Client $client  The configured client instance.
	 * @param array          $request The request payload.
	 * @return mixed The response object from the API or an error array.
	 */
	public function make_request( $client, $request ) {
		// Log the request being sent (optional, for debugging).
		// file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama Request' => $request ), true ), FILE_APPEND );

		try {
			$response = $client->chat()->create( $request );
			// Log the successful response (optional).
			// file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama Response' => $response->toArray() ), true ), FILE_APPEND );
			return $response;
		} catch ( \OpenAI\Exceptions\ErrorException $e ) {
			// Log specific API errors.
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama API Error' => $e->getMessage() ), true ), FILE_APPEND ); // phpcs:ignore
			return array(
				'error'   => true,
				'message' => 'Ollama API Error: ' . $e->getMessage(),
			);
		} catch ( \Exception $e ) {
			// Log general errors (e.g., connection issues).
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama General Error' => $e->getMessage() ), true ), FILE_APPEND ); // phpcs:ignore
			return array(
				'error'   => true,
				'message' => 'Ollama Connection/General Error: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Extracts the relevant content from the Ollama API response.
	 *
	 * @param mixed $llm_response_data The raw response from make_request.
	 * @param bool  $use_json_mode     Whether to expect and parse JSON.
	 * @return string|array|null The response string, parsed JSON array, or null/error string on failure.
	 */
	public function get_response_string( $llm_response_data, $use_json_mode = false ) {
		// Check if we received an error structure from make_request.
		if ( is_array( $llm_response_data ) && isset( $llm_response_data['error'] ) && true === $llm_response_data['error'] ) {
			return 'Error: ' . $llm_response_data['message'];
		}

		// Ensure we have a valid response object structure before proceeding.
		if ( ! isset( $llm_response_data->choices[0]->message->content ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Invalid Ollama Response' => $llm_response_data ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: Invalid response structure from Ollama.';
		}

		$content = $llm_response_data->choices[0]->message->content;

		if ( $use_json_mode ) {
			$decoded_json = json_decode( $content, true ); // Decode as associative array.

			// Check for JSON decoding errors.
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama JSON Decode Error' => json_last_error_msg(), 'Content' => $content ), true ), FILE_APPEND ); // phpcs:ignore
				return null; // Indicate failure to decode.
			}

			// Basic validation: Ensure it's an array (as we expect a list of objects).
			if ( ! is_array( $decoded_json ) ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama JSON Validation Error' => 'Decoded JSON is not an array.', 'Content' => $content ), true ), FILE_APPEND ); // phpcs:ignore
				return null; // Indicate structure validation failure.
			}

			// Optional: Add more specific validation for the expected structure [{"post_id": W, "score": X, ...}].
			// This could involve checking if the first element exists and has the required keys.

			return $decoded_json; // Return the successfully decoded and validated array.

		} else {
			// Return the plain text content.
			return $content;
		}
	}
}
