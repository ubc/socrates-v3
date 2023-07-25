<?php
/**
 * Class Socrates_LLM_Chat_Gpt
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
	 * 				     'prompt' : the full prompt we'll send to the LLM
	 *                   'model'  : the model we wish to use
	 *                   This is filtered, so other data may be available.
	 *
	 * @return mixed the fully-formed request we're going to make
	 * @since
	 */
	public function get_request( $request_params = array() ) {

		$request = array(
			'model' => $request_params['model'],
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $request_params['prompt'],
				)
			),
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

		file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( 'about to make request', $request ), true ), FILE_APPEND ); // phpcs:ignore

		$data = $client->chat()->create( $request );

		return $data;

	}//end make_request()

}
