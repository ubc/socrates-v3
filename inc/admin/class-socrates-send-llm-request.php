<?php
/**
 * Class Socrates_Send_LLM_Request
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

/**
 * Socrates_Send_LLM_Request Class.
 *
 * Determines which LLM to use (based on options) and then sends, parses, and handles, the LLM Request.
 */
class Socrates_Send_LLM_Request {

	/**
	 * Will be filled with the tool chosen in the settings.
	 *
	 * @var string
	 * @since 3.0.1
	 */
	var $tool = '';

	/**
	 * This will be filled with the model chosen in the settings.
	 */
	var $model = '';

	/**
	 * Will be filled with the client from the model chosen.
	 */
	var $client = false;

	/**
	 * Will be filled with the instance of the class we're using for this reuqest.
	 */
	var $client_class = false;

	/**
	 * Will be filled by the code calling this class. Filled with the prompt we're
	 * sending.
	 */
	var $prompt = '';


	/**
	 * Will be filled with the request to actually make to the LLM
	 */
	var $request;


	/**
	 * Will be filled with whatever data the LLM returns.
	 */
	var $llm_response_data;

	/**
	 * Initalize ourselves
	 *
	 * @since 3.0.1
	 * @return void
	 */
	public function __construct() {

		// Fetch the model saved in settings, and store it for us within this class.
		$this->set_model();

		// Set the client class we need to use, to get and set the client.
		$this->set_client_class();

		// Based on the model chosen, determine which client we'll use.
		$this->set_client();

		// set_prompt() is called from the code requesting something from an LLM.

		// send_request() is then called from the code requesting something from an LLM
	}//end __construct()


	/**
	 * Fetches the model saved in the settings and stores to class property.
	 * This first looks for which tool is being used, and then gets the model for that tool.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_model() {

		$tool = esc_html( get_option( 'socratic_generative_ai_tool' ) );

		$this->tool = $tool;

		// This plugin supports two LLMs at this time. But add-ons can provide their own.
		$model_name = apply_filters( 'socrates_v3_llm_model_name', false, $tool );

		// If $model_name is not false, we use whatever the external plugin suggests is the model name.
		if ( false !== $model_name ) {
			$this->model = esc_html( get_option( $model_name ) );
			return;
		}

		// Otherwise, it must be one of the two supported LLMs.
		switch ( $tool ) {

			case 'claude':
				$this->model = esc_html( get_option( 'socratic_anthropic_model' ) );
				break;

			case 'ollama':
				$this->model = esc_html( get_option( 'socratic_ollama_model' ) );
				break;

			case 'chatgpt':
				$this->model = esc_html( get_option( 'socratic_chatgpt_model' ) );
				break;

			default:
				$this->model = false;
				break;

		}
	}//end set_model()


	/**
	 * Determines which client to use based on the model that has been set.
	 * Sets to class property.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_client() {

		$client = $this->client_class->get_client();

		$this->client = $client;
	}//end set_client()


	/**
	 * Determines and sets the class for the LLM request
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_client_class() {

		// Determine the class to load based on the selected tool.
		$class_to_load = '';
		switch ( $this->tool ) {
			case 'chatgpt':
				$class_to_load = '\UBC\CTLT\Socrates_LLM_Chat_Gpt';
				break;
			case 'claude':
				$class_to_load = '\UBC\CTLT\Socrates_LLM_Claude';
				break;
			case 'ollama':
				$class_to_load = '\UBC\CTLT\Socrates_LLM_Ollama';
				break;
			default:
				// Allow extensions to add their own tool/class mapping.
				$class_to_load = apply_filters( 'socrates_v3_custom_llm_class', '', $this->tool );
				if ( empty( $class_to_load ) ) {
					// Log the error for debugging.
					file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( __FILE__, __LINE__, 'Unsupported tool', $this->tool ), true ), FILE_APPEND ); // phpcs:ignore
					wp_die( 'Invalid or unsupported LLM tool selected: ' . esc_html( $this->tool ) );
				}
		}

		$file_to_load_for_chosen_llm_class = array(
			'\UBC\CTLT\Socrates_LLM_Chat_Gpt' => plugin_dir_path( __FILE__ ) . 'class-socrates-llm-chat-gpt.php',
			'\UBC\CTLT\Socrates_LLM_Claude'   => plugin_dir_path( __FILE__ ) . 'class-socrates-llm-claude.php',
			'\UBC\CTLT\Socrates_LLM_Ollama'   => plugin_dir_path( __FILE__ ) . 'class-socrates-llm-ollama.php',
		);

		/**
		 * Filters the file to load for the chosen LLM Class.
		 *
		 * This filter allows add-on developers to ensure the correct file is loaded for the chosen LLM Class.
		 *
		 * @since 3.0.1
		 *
		 * @hooked None by default.
		 */
		$file_to_load_for_chosen_llm_class = apply_filters( 'socrates_v3_file_to_load_for_chosen_llm_class', $file_to_load_for_chosen_llm_class );

		// Check if the determined class has a file path defined.
		if ( ! array_key_exists( $class_to_load, $file_to_load_for_chosen_llm_class ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( __FILE__, __LINE__, 'file not defined for class', $class_to_load, $file_to_load_for_chosen_llm_class ), true ), FILE_APPEND ); // phpcs:ignore
			wp_die( 'No file path defined for the chosen LLM class: ' . esc_html( $class_to_load ) );
		}

		$file_path = $file_to_load_for_chosen_llm_class[ $class_to_load ];

		if ( ! file_exists( $file_path ) ) {
			file_put_contents( WP_CONTENT_DIR . '/debug.log', print_r( array( __FILE__, __LINE__, 'file does not exist', $file_path, $class_to_load ), true ), FILE_APPEND ); // phpcs:ignore
			wp_die( 'LLM class file does not exist: ' . esc_html( $file_path ) );
		}

		require_once $file_path;

		// Now we have the class file loaded, we need to instantiate the class.
		$client_class = new $class_to_load();

		$this->client_class = $client_class;
	}//end set_client_class()


	/**
	 * Sets the prompt we'll be sending to the LLM. Called from the code which instantiates
	 * and uses this class.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function set_prompt( $prompt ) {

		$this->prompt = $prompt;
	}//end set_prompt()


	/**
	 * Calls the specific LLM Class to get the actual request we'll make against the LLM.
	 *
	 * @param bool $use_json_mode Optional. Whether to request JSON mode. Defaults to false.
	 * @return void
	 * @since
	 */
	public function set_request( $use_json_mode = false ) {

		$request_params = array(
			'model'    => $this->model,
			'messages' => $this->prompt,
		);

		/**
		 * Filters the parameters that are sent to the LLM Class
		 *
		 * This filter allows add-on developers to ensure all the data that need to send to the LLM class is sent.
		 *
		 * @since 3.0.1
		 *
		 * @hooked None by default.
		 */
		$request_params = apply_filters( 'socrates_v3_llm_request_params', $request_params );

		// Get the LLM Class to generate the full request we need to send.
		$request = $this->client_class->get_request( $request_params, $use_json_mode );

		// Store this request locally.
		$this->request = $request;
	}//end set_request()


	/**
	 * Send the request to the LLM and set the response locally.
	 *
	 * @param bool $use_json_mode Optional. Whether to request JSON mode. Defaults to false.
	 * @return mixed $data The data returned from the LLM.
	 * @since 3.0.1
	 */
	public function send_request( $use_json_mode = false ) {

		// The actual request we'll make against the LLM, is created within the specific LLM class.
		$this->set_request( $use_json_mode );

		$data = $this->client_class->make_request( $this->client, $this->request );

		$this->llm_response_data = $data;
	}//end send_request()


	/**
	 * Retrieves the data the LLM has sent back.
	 *
	 * @param bool $use_json_mode Optional. Whether the request used JSON mode. Defaults to false.
	 * @return mixed If JSON mode, returns parsed array or null/error string on failure.
	 *               If not JSON mode, returns array ['reasoning' => string|null, 'response' => string].
	 *               Returns error string if the initial response indicates an error.
	 * @since 3.0.1 (Modified in 3.5.0 to reflect reasoning return type)
	 */
	public function get_llm_response_data( $use_json_mode = false ) {

		return $this->abstract_data_from_llm_response( $use_json_mode );
	}//end get_llm_response_data()


	/**
	 * We have a raw response from the LLM, but we need just the response text.
	 * This method calls the appropriate method in the specific LLM class.
	 *
	 * @param bool $use_json_mode Optional. Whether the request used JSON mode. Defaults to false.
	 * @return mixed If JSON mode, returns parsed array or null/error string on failure.
	 *               If not JSON mode, returns array ['reasoning' => string|null, 'response' => string].
	 *               Returns error string if the initial response indicates an error.
	 * @since 3.0.1 (Modified in 3.5.0 to reflect reasoning return type)
	 */
	public function abstract_data_from_llm_response( $use_json_mode = false ) {

		return $this->client_class->get_response_string( $this->llm_response_data, $use_json_mode );
	}//end abstract_data_from_llm_response()
}
