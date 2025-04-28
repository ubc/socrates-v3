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
	 * @param bool  $use_json_mode  Whether to request JSON output (currently ignored for request format, handled in response).
	 * @return array The formatted request array.
	 */
	public function get_request( $request_params, $use_json_mode = false ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$request = array(
			'model'    => $this->model,
			'messages' => $request_params['messages'],
			'stream'   => false, // Explicitly disable streaming for now.
			'options'  => array(
				'num_ctx'     => $this->num_ctx,
				'temperature' => $this->temperature,
			),
		);

		// Note: 'format' => 'json' is deferred to Task 5.
		// if ( $use_json_mode ) {
		// $request['format'] = 'json';
		// }

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
	 * @param mixed $llm_response_data The raw response object (likely ArdaGnsrn\Ollama\Responses\ChatResponse) from make_request.
	 * @param bool  $use_json_mode     Whether to expect and parse JSON.
	 * @return string|array|null The response string, parsed JSON array, or null/error string on failure.
	 */
	public function get_response_string( $llm_response_data, $use_json_mode = false ) {
		// Check if we received an error structure from make_request.
		if ( is_array( $llm_response_data ) && isset( $llm_response_data['error'] ) && true === $llm_response_data['error'] ) {
			return 'Error: ' . $llm_response_data['message'];
		}

		// Validate the response object structure from ardagnsrn/ollama-php.
		// We expect a ChatResponse object with a public message property containing a ChatMessageResponse object.
		if ( ! is_object( $llm_response_data ) || ! property_exists( $llm_response_data, 'message' ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Invalid Ollama Response Object (Missing message property)' => $llm_response_data ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: Invalid response structure from Ollama client (message property missing).';
		}

		$message_object = $llm_response_data->message; // Access the message property (ChatMessageResponse object).

		// Check if the message object is valid and has the content property.
		if ( ! is_object( $message_object ) || ! property_exists( $message_object, 'content' ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama Response Missing Content property' => $message_object ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: Response message content property not found.';
		}

		$content = $message_object->content; // Access the content property.

		if ( $use_json_mode ) {
			$decoded_json = json_decode( $content, true ); // Decode as associative array.

			// Check for JSON decoding errors.
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama JSON Decode Error' => json_last_error_msg(), 'Content Received' => $content ), true ), FILE_APPEND ); // phpcs:ignore
				// Return null or an error string to indicate JSON failure.
				return null;
			}

			// Basic validation: Ensure it's an array (as we expect a list of objects for NOTW).
			if ( ! is_array( $decoded_json ) ) {
				file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Ollama JSON Validation Error' => 'Decoded JSON is not an array.', 'Content Received' => $content ), true ), FILE_APPEND ); // phpcs:ignore
				// Return null or an error string to indicate structure validation failure.
				return null;
			}

			// Optional: Add more specific validation here later if needed.

			return $decoded_json; // Return the successfully decoded array.

		} else {
			// Return the plain text content.
			return $content;
		}
	}
}
