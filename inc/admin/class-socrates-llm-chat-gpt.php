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

// Used for testing if necessary.
// use OpenAI\Testing\ClientFake;
// use OpenAI\Responses\Chat\CreateResponse;

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

		// $client = new ClientFake([
		// 	CreateResponse::fake([
		// 		'choices' => [
		// 			[
		// 				'text' => 'awesome!',
		// 			],
		// 		],
		// 	]),
		// ]);

		// $client = new ClientFake([
		// 	new \OpenAI\Exceptions\ErrorException([
		// 		'message' => 'The server had an error processing your request. Sorry about that! You can retry your request,
		//                    or contact us through our help center at fake.fakesite.come if you keep seeing this error.
		//                    (Please include the request ID abcd1234efgh5678 in your email.)',
		// 		'type' => 'invalid_request_error',
		// 		'code' => null,
		// 	])
		// ]);

		return $client;

	}//end get_client()


	/**
	 * Formulate the request we're going to send.
	 *
	 * @param  array $request_params
	 * 				     'messages' : the full set of messages sent to ChatGPT
	 *                   'model'  : the model we wish to use
	 *                   This is filtered, so other data may be available.
	 *
	 * @return mixed the fully-formed request we're going to make
	 * @since
	 */
	public function get_request( $request_params = array() ) {

		$request = array(
			'model'    => $request_params['model'],
			'messages' => $request_params['messages'],
		);

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

		try {

			$data = $client->chat()->create( $request );
			return $data;

		} catch ( \OpenAI\Exceptions\ErrorException $e ) {

			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'API Error' => $e->getMessage() ), true ), FILE_APPEND ); // phpcs:ignore

			return [
				'error'   => true,
				'message' => $e->getMessage()
			];

		} catch ( \Exception $e ) {

			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Unexpected Error' => $e->getMessage() ), true ), FILE_APPEND ); // phpcs:ignore

			return [
				'error' => true,
				'message' => 'An unexpected error occurred: ' . $e->getMessage()
			];
		}

	}//end make_request()


	/**
	 * The response from the LLM contains lots of stuff we don't need.
	 * Just return the response string.
	 *
	 * @param  array $llm_response_data
	 *
	 * @return string Just the response string
	 * @since 3.0.1
	 */
	public function get_response_string( $llm_response_data ) {

		$response_string = false;

		// Test if we have caught an error.
		if ( is_array( $llm_response_data ) && isset( $llm_response_data['error'] ) && true === $llm_response_data['error'] ) {
			return 'Error: ' . $llm_response_data['message'];
		}

		foreach ( $llm_response_data->choices as $result ) {
			// Only want the last response, I THINK.
			if ( ! isset ( $result->finishReason ) || 'stop' !== $result->finishReason ) {
				continue;
			}

			$response_string = $result->message->content;
		}

		if ( false === $response_string ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'Response was false.' ), true ), FILE_APPEND ); // phpcs:ignore
			return 'Error: ' . __FILE__ . ' :: ' . __LINE__;
		}

		return $response_string;

	}//end get_response_string()

}
