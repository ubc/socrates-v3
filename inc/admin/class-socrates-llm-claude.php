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
	 *
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
	 * 				     'messages' : the full set of messages sent to the LLM
	 *                   'model'  : the model we wish to use
	 *                   This is filtered, so other data may be available.
	 *
	 * @return mixed the fully-formed request we're going to make
	 * @since
	 */
	public function get_request( $request_params = array() ) {

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

		$model  = $this->model;

		// Currently hardcoded.
		// @todo: Make these settable somehow? Maybe?
		$max_tokens_to_sample = 4000;
		$stop_sequences       = array( "\n\nHuman:" );

		$request = array(
			'prompt' => $prompt,
			'model' => $model,
			'max_tokens_to_sample' => $max_tokens_to_sample,
			'stop_sequences' => $stop_sequences,
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
	 * 			'model'    : the model we wish to use
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
	 * Just return the response string.
	 *
	 * @param  array $llm_response_data
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function get_response_string( $llm_response_data ) {

		// Test if we have caught an error.
		if ( is_array( $llm_response_data ) && isset( $llm_response_data['error'] ) && is_array( $llm_response_data['error'] ) ) {
			return 'Error: ' . $llm_response_data['error']['message'];
		}

		// If the completion isn't part of the response, return an error.
		if ( ! isset( $llm_response_data['completion'] ) ) {
			return 'Error: No error message received, but no completion in response. ';
		}

		// Isn't an error, so let's grab the 'completion'.
		$response_string = $llm_response_data['completion'];

		return $response_string;

	}//end get_response_string()


}//end class
