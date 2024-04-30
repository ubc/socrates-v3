<?php
/**
 * Class Socrates_Participant_Chat
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

/**
 * Socrates_Participant_Chat Class.
 *
 * The main class which sets up everything for the public side of things.
 */
class Socrates_Participant_Chat {

	/**
	 * Set up the public side of things.
	 *
	 * @since 3.0.1
	 */
	public function __construct() {

		// Register block editor scripts.
		add_action( 'init', array( $this, 'register_block_editor_script' ) );

		// Register the Gutenberg block.
		add_action( 'init', array( $this, 'register_block' ) );

		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Add our public block styles and scripts.
		add_action( 'socrates_v3_before_public_output', array( $this, 'enqueue_public_block_assets' ) );

	}//end __construct()

	/**
	 * Register block editor script.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function register_block_editor_script() {
		wp_register_script(
			'socrates-v3-block',
			plugin_dir_url( __FILE__ ) . 'js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor' ),
			true
		);
	}//end register_block_editor_script()

	/**
	 * Register the Gutenberg block.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function register_block() {
		register_block_type(
			'socrates-v3/socrates-v3-block',
			array(
				'editor_script' => 'socrates-v3-block',
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}//end register_block()

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function register_rest_routes() {
		register_rest_route(
			'socrates/v3',
			'/editor',
			array(
				'methods' => 'GET',
				'callback' => function() {
					ob_start();
					include plugin_dir_path( __FILE__ ) . 'block-template-editor.php';
					$output = ob_get_clean();
					return array('html' => $output);
				}
			)
		);
	}//end register_rest_routes()

	/**
	 * Render the Gutenberg block on the front-end.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The block content.
	 * @return string The rendered block HTML.
	 * @since 3.0.1
	 */
	public function render_block( $attributes, $content ) {
		ob_start();
		include plugin_dir_path( __FILE__ ) . 'block-template-public.php';
		return ob_get_clean();
	}//end render_block()

	/**
	 * Add our public block styles and scripts.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function enqueue_public_block_assets() {

		// Add our public block styles.
		wp_enqueue_style(
			'socrates-v3-public-styles',
			plugin_dir_url( __FILE__ ) . 'css/public-styles.css',
			array(),
			'1.0.0'
		);

		// Add our public js
		wp_enqueue_script(
			'socrates-v3-public',
			plugin_dir_url( __FILE__ ) . 'js/public.js',
			array(),
			'1.0.0',
			true
		);

	}//end enqueue_public_block_assets()

}
