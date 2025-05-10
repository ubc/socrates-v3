<?php
/**
 * Class Socrates_LLM_Ollama.
 *
 * Handles communication with a local or remote Ollama instance using the ardagnsrn/ollama-php library.
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

// Use the specific Ollama client and exception classes.
use ArdaGnsrn\Ollama\Ollama;
use ArdaGnsrn\Ollama\Exceptions\ErrorException as OllamaErrorException;

/**
 * Socrates_LLM_Ollama Class.
 *
 * Sets up the client and communication methods for Ollama using the dedicated library.
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
	 * Ollama context length (num_ctx).
	 *
	 * @var int
	 */
	private $num_ctx = 8192;

	/**
	 * Ollama temperature setting.
	 *
	 * @var float
	 */
	private $temperature = 0.1;


	/**
	 * Initialize the Ollama handler.
	 *
	 * Fetches necessary options from WordPress settings.
	 */
	public function __construct() {
		$this->api_key     = esc_attr( get_option( 'socratic_ollama_api_key', '' ) );
		$this->model       = esc_attr( get_option( 'socratic_ollama_model', '' ) );
		$this->server_url  = esc_url_raw( get_option( 'socratic_ollama_server_url', 'http://localhost:11434' ) );
		$this->num_ctx     = absint( get_option( 'socratic_ollama_num_ctx', 8192 ) ); // Use absint for sanitization.
		$this->temperature = floatval( get_option( 'socratic_ollama_temperature', 0.1 ) ); // Use floatval for sanitization.
	}

	/**
	 * Configures and returns the Ollama client.
	 *
	 * @return \ArdaGnsrn\Ollama\Ollama The configured client instance.
	 */
	public function get_client() {
		// Instantiate the client with the server URL.
		$client = Ollama::client( $this->server_url );

		// Add authorization header if an API key is provided.
		// Removed: The ardagnsrn/ollama-php library does not support withHeaders() directly.
		// Authentication needs to be handled by the Ollama server config if required.
		// if ( ! empty( $this->api_key ) ) {
		// $client = $client->withHeaders(
		// array(
		// 'Authorization' => 'Bearer ' . $this->api_key,
		// )
		// );
		// }

		return $client;
	}

	/**
	 * Formats the request payload for the Ollama API (using ardagnsrn/ollama-php format).
	 *
	 * @param array $request_params Associative array containing 'messages'.
	 * @param bool  $use_json_mode  Whether to request JSON output.
	 * @return array The formatted request array.
	 */
	public function get_request( $request_params, $use_json_mode = false ) {
		$request = array(
			'model'    => $this->model,
			'messages' => $request_params['messages'],
			'stream'   => false, // Explicitly disable streaming for now.
			'options'  => array(
				'num_ctx'     => $this->num_ctx,
				'temperature' => $this->temperature,
			),
		);

		// Add JSON format parameter if requested.
		if ( $use_json_mode ) {
			$request['format'] = 'json';
			// JSON mode requires streaming to be off, which we already ensure above.
		}

		return $request;
	}

	/**
	 * Sends the request to the Ollama API via the client.
	 *
	 * @param \ArdaGnsrn\Ollama\Ollama $client  The configured client instance.
	 * @param array                    $request The request payload.
	 * @return mixed The response object from the API or an error array.
	 */
	public function make_request( $client, $request ) {
		// Log the request being sent (optional, for debugging).
		// file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama Request' => $request ), true ), FILE_APPEND );

		try {
			// Use the chat()->create method from the ardagnsrn/ollama-php library.
			$response = $client->chat()->create( $request );

			// Log the successful response (optional).
			// file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama Response' => $response->toArray() ), true ), FILE_APPEND );
			return $response;
		} catch ( OllamaErrorException $e ) {
			// Log specific Ollama library errors.
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama API Error' => $e->getMessage(), 'Request' => $request ), true ), FILE_APPEND ); // phpcs:ignore
			return array(
				'error'   => true,
				'message' => 'Ollama API Error: ' . $e->getMessage(),
			);
		} catch ( \Exception $e ) {
			// Log general errors (e.g., connection issues).
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama General Error' => $e->getMessage(), 'Request' => $request ), true ), FILE_APPEND ); // phpcs:ignore
			return array(
				'error'   => true,
				'message' => 'Ollama Connection/General Error: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Extracts the relevant content from the Ollama API response.
	 *
	 * @param mixed $llm_response_data The raw response object (likely ArdaGnsrn\Ollama\Responses\ChatResponse) or error array from make_request.
	 * @param bool  $use_json_mode     Whether to expect and parse JSON.
	 * @return string|array|null If JSON mode, returns parsed array or null/error string on failure.
	 *                            If not JSON mode, returns array ['reasoning' => string|null, 'response' => string].
	 *                            Returns error string if the initial response indicates an error.
	 * @since 3.5.0 (Handles reasoning extraction)
	 */
	public function get_response_string( $llm_response_data, $use_json_mode = false ) {
		// Check if we received an error structure from make_request.
		if ( is_array( $llm_response_data ) && isset( $llm_response_data['error'] ) && true === $llm_response_data['error'] ) {
			return 'Error: ' . $llm_response_data['message'];
		}

		// Validate the response object structure from ardagnsrn/ollama-php.
		if ( ! is_object( $llm_response_data ) || ! property_exists( $llm_response_data, 'message' ) || ! is_object( $llm_response_data->message ) || ! property_exists( $llm_response_data->message, 'content' ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Invalid Ollama Response Object Structure' => $llm_response_data ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: Invalid response structure from Ollama client.';
		}

		// Get the primary response content.
		$raw_content = $llm_response_data->message->content;

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
		 * @param object $llm_instance The instance of the current LLM class (Socrates_LLM_Ollama).
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
		 * @param object $llm_instance The instance of the current LLM class (Socrates_LLM_Ollama).
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
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama JSON Decode Error' => json_last_error_msg(), 'Content Received' => $main_response ), true ), FILE_APPEND ); // phpcs:ignore
				return null; // Indicate failure.
			}

			// Basic validation: Ensure it's an array (as we expect a list of objects for NOTW).
			if ( ! is_array( $decoded_json ) ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama JSON Validation Error' => 'Decoded JSON is not an array.', 'Content Received' => $main_response ), true ), FILE_APPEND ); // phpcs:ignore
				return null; // Indicate structure validation failure.
			}

			// Optional: Add more specific validation here later if needed.

			return $decoded_json; // Return the successfully decoded array.

		}

		// If not JSON mode, return the extracted parts.
		return array(
			'reasoning' => $reasoning,
			'response'  => $main_response,
		);
	}
}
