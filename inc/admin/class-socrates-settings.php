<?php
/**
 * Class Socrates_Settings
 *
 * @package UBC\CTLT
 */

namespace UBC\CTLT;

/**
 * Socrates_Settings Class.
 *
 * This class is used to handle the settings of the Socrates plugin.
 */
class Socrates_Settings {

	/**
	 * Construct the plugin object.
	 */
	public function __construct() {

		// Register our settings.
		add_action( 'admin_init', array( $this, 'admin_init__socrates_register_socrates_settings' ) );

		// Add admin menu
		add_action( 'admin_menu', array( $this, 'admin_menu__socrates_admin_menu' ) );

		// Load our JavaScript
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts__socrates_enqueue_admin_js' ) );

	}

	/**
	 * Set up the main Socrates settings page.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function admin_menu__socrates_admin_menu() {

		// Only allow administrators to access this menu item
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_options_page(
			'Socratic Method Version 3 Settings',
			'Socrates',
			'manage_options',
			'socrates',
			array( $this, 'add_options_page__socrates_settings_page' )
		);

	}//end admin_menu__socrates_admin_menu()


	/**
	 * Register our settings for our settings panel.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function admin_init__socrates_register_socrates_settings() {

		// Prompt settings
		register_setting( 'socrates_prompt_settings', 'socratic_starting_prompt', array(
			'sanitize_callback' => array( $this, 'sanitize_starting_prompt' )
		) );

		register_setting( 'socrates_prompt_settings', 'socratic_chatgpt_api_key', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_prompt_settings', 'socratic_chatgpt_model', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		// Feed settings
		register_setting( 'socrates_feed_settings', 'socratic_feeds', array(
			'sanitize_callback' => array( $this, 'sanitize_feeds' )
		) );

		register_setting( 'socrates_feed_settings', 'socratic_categories', array(
			'sanitize_callback' => array( $this, 'sanitize_categories' )
		) );

		register_setting( 'socrates_feed_settings', 'socratic_minimum_threshold_score', array(
			'sanitize_callback' => 'absint'
		) );

		register_setting( 'socrates_feed_settings', 'socratic_email_cadence', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_feed_settings', 'socratic_link_collection_cadence', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_notw_settings', 'socratic_notw_day', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_notw_settings', 'socratic_include_other_category_links', array(
			'sanitize_callback' => 'sanitize_text_field',
		) );

		register_setting( 'socrates_notw_settings', 'socratic_notw_post_category', array(
			'sanitize_callback' => 'absint',
		) );


		/**
		 * Fires after the core settings for Socrates are registered.
		 *
		 * This action is called after the core settings for the Socrates plugin are registered.
		 * It allows developers to hook into the registration process to perform custom actions or modifications.
		 *
		 * @since 3.0.1
		 *
		 * @see register_setting()
		 * @see sanitize_text_field()
		 * @see esc_url_raw()
		 *
		 * @hooked None by default.
		 */
		do_action( 'socrates_v3_after_register_core_settings' );

	}//end admin_init__socrates_register_socrates_settings()


	/**
	 * Our settings page is tabbed. It allows us to keep specific settings
	 * per part of the functionality the plugin provides in their own area.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function add_options_page__socrates_settings_page() {

		// Set the active tab
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'prompt_settings';

		?>

		<h1><?php _e( 'Socratic Method Version 3 Settings' ); ?></h1>

		<!-- Display the tabs -->
		<h2 class="nav-tab-wrapper">

			<?php

			/**
			 * Fires before the core settings tabs for Socrates are output.
			 *
			 * This action is called before the core settings tabs are output for the Socrates plugin.
			 *
			 * @since 3.0.1
			 * @hooked None by default.
			 */
			do_action( 'socrates_v3_before_core_settings_tabs' );

			?>

			<a href="?page=socrates&tab=prompt_settings" class="nav-tab <?php echo $active_tab == 'prompt_settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Prompt Settings' ); ?></a>
			<a href="?page=socrates&tab=feed_settings" class="nav-tab <?php echo $active_tab == 'feed_settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Feed Settings' ); ?></a>
			<a href="?page=socrates&tab=news_of_the_week" class="nav-tab <?php echo $active_tab == 'news_of_the_week' ? 'nav-tab-active' : ''; ?>"><?php _e( 'News Of The Week Settings' ); ?></a>
			<?php

				/**
				 * Fires after the core settings tabs for Socrates are output.
				 *
				 * This action is called after the core settings tabs for the Socrates plugin are output.
				 *
				 * @since 3.0.1
				 * @hooked None by default.
				 */
				do_action( 'socrates_v3_after_core_settings_tabs' );

			?>
		</h2>

		<?php

		switch ( $active_tab ) {
			case 'prompt_settings':
				$this->socrates_tab_output__prompt_settings();
				break;

			case 'feed_settings':
				$this->socrates_tab_output__feed_settings();
				break;

			case 'news_of_the_week':
				$this->socrates_tab_output__notw_settings();
				break;

			default:
				do_action( 'socrates_v3_active_tab_callback', $active_tab );
				break;

		}

	}//end add_options_page__socrates_settings_page()


	/**
	 * Prompt Settings Tab output.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function socrates_tab_output__prompt_settings() {

		?>
		<form method="post" id="prompt-form" action="options.php">

			<?php
			settings_fields( 'socrates_prompt_settings' );
			do_settings_sections( 'socrates_prompt_settings' );
			?>

			<table class="form-table" role="presentation">
				<tbody>
					<!-- ChatGPT API Key field -->
					<tr>
						<th scope="row"><label for="socratic_chatgpt_api_key">ChatGPT API Key</label></th>
						<td><input type="text" id="socratic_chatgpt_api_key" name="socratic_chatgpt_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'socratic_chatgpt_api_key' ) ); ?>"></td>
					</tr>
					<!-- ChatGPT Model field -->
					<tr>
						<th scope="row"><label for="socratic_chatgpt_model">ChatGPT Model To Use</label></th>
						<td>
							<select id="socratic_chatgpt_model" name="socratic_chatgpt_model">
								<?php
								$models = array( 'gpt-3.5-turbo-0613', 'gpt-3.5-turbo', 'gpt-3.5-turbo-16k', 'gpt-4', 'gpt-4-32k' );
								$current_model = get_option('socratic_chatgpt_model');
								foreach ( $models as $model ) {
									echo '<option value="' . esc_attr( $model ) . '"' . selected( $current_model, $model, false ) . '>' . esc_html( $model ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<!-- Starting Prompt field -->
					<tr>
						<th scope="row"><label for="starting-prompt">Starting Prompt</label></th>
						<td><textarea id="starting-prompt" name="socratic_starting_prompt" rows="10" class="large-text code" cols="50"><?php echo wp_kses_post( str_replace( array( '<br>', '<br/>', '<br />'), "\n", get_option( 'socratic_starting_prompt' ) ) ); ?></textarea></td>
					</tr>
				</tbody>
			</table>

			<button id="fetch-data" type="button" class="button">Fetch Feeds</button>

			<div id="data-container"></div> <!-- This div will contain the fetched data -->

			<div id="spinner" style="display: none;">Some spinner</div>

			<?php submit_button(); ?>

		</form>

		<?php


	}//end socrates_tab_output__prompt_settings()


	/**
	 * Feed settings tab output.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function socrates_tab_output__feed_settings() {

		?>
		<!-- Feed Settings tab content -->
		<form method="post" id="feed-form" action="options.php">
			<?php
			settings_fields( 'socrates_feed_settings' );
			do_settings_sections( 'socrates_feed_settings' );

			// Get the feeds data
			$feeds = get_option( 'socratic_feeds' );
			if ( ! $feeds ) {
				$feeds = array( array( 'url' => '', 'weight' => '' ) );
			}

			// Get the categories data
			$categories = get_option( 'socratic_categories' );
			if ( ! $categories ) {
				$categories = array( '' );
			}
			?>

			<table class="form-table" role="presentation">
				<tbody>
					<!-- Feeds fields -->
					<tr>
						<th scope="row"><label for="feed-url-0">Feed URL</label></th>
						<td>
							<div id="feeds-container">
							<?php foreach ( $feeds as $index => $feed ): ?>
								<div class="feed-row">
									<label for="feed-url-<?php echo $index; ?>">Feed URL</label>
									<input type="text" id="feed-url-<?php echo $index; ?>" name="socratic_feeds[<?php echo $index; ?>][url]" value="<?php echo esc_url($feed['url']); ?>">
									<label for="feed-weight-<?php echo $index; ?>">Weight</label>
									<input type="text" id="feed-weight-<?php echo $index; ?>" name="socratic_feeds[<?php echo $index; ?>][weight]" value="<?php echo esc_attr($feed['weight']); ?>">
									<button type="button" class="delete-feed">Delete</button>
								</div>
							<?php endforeach; ?>
							</div>
							<button type="button" id="add-feed">Add New Feed</button>
						</td>
					</tr>
					<!-- Categories fields -->
					<tr>
						<th scope="row"><label for="category-0">Content Category</label></th>
						<td>
							<div id="categories-container">
							<?php foreach ($categories as $index => $category): ?>
								<div class="category-row">
									<label for="category-<?php echo $index; ?>">Content Category</label>
									<input type="text" id="category-<?php echo $index; ?>" name="socratic_categories[<?php echo $index; ?>]" value="<?php echo esc_attr( $category ); ?>">
									<button type="button" class="delete-category">Remove Category</button>
								</div>
							<?php endforeach; ?>
							</div>
							<button type="button" id="add-category">Add Category</button>
						</td>
					</tr>
					<!-- Threshold Score field -->
					<tr>
						<th scope="row"><label for="socratic_minimum_threshold_score">Threshold Score</label></th>
						<td><input type="text" id="socratic_minimum_threshold_score" name="socratic_minimum_threshold_score" class="regular-text" value="<?php echo esc_attr( get_option( 'socratic_minimum_threshold_score' ) ); ?>"></td>
					</tr>
					<!-- E-mail Cadence field -->
					<tr>
						<th scope="row"><label for="email-cadence">E-mail Cadence</label></th>
						<td>
							<select id="email-cadence" name="socratic_email_cadence">
								<?php
								$days = array( 'Every Monday', 'Every Tuesday', 'Every Wednesday', 'Every Thursday', 'Every Friday', 'Every Saturday', 'Every Sunday' );
								$current_day = get_option('socratic_email_cadence');
								foreach ( $days as $day ) {
									echo '<option value="' . esc_attr( $day ) . '"' . selected( $current_day, $day, false ) . '>' . esc_html( $day ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<!-- Link Collection Cadence field -->
					<tr>
						<th scope="row"><label for="link-collection-cadence">Link Collection Cadence</label></th>
						<td>
							<select id="link-collection-cadence" name="socratic_link_collection_cadence">
								<?php
								$options = array( 'Daily at Midnight', 'Every other day at Midnight', 'Every Sunday at Midnight' );
								$current_option = get_option( 'socratic_link_collection_cadence' );
								foreach ( $options as $option ) {
									echo '<option value="' . esc_attr( $option ) . '"' . selected( $current_option, $option, false ) . '>' . esc_html( $option ) . '</option>';
								}
								?>
							</select>
							<p id="notice" class="notice notice-warning is-dismissible" style="display: none;">This may mean you miss the occasional post from some feeds which produce a lot of content each week</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php

	}//end socrates_tab_output__feed_settings()


	/**
	 * Output for the NOTW Tab.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function socrates_tab_output__notw_settings() {

		?>
		<!-- Feed Settings tab content -->
		<form method="post" id="notw-form" action="options.php">
			<?php
			settings_fields( 'socrates_notw_settings' );
			do_settings_sections( 'socrates_notw_settings' );
			?>

			<table class="form-table" role="presentation">
				<tbody>
					<!-- NOTW Day field -->
					<tr>
						<th scope="row"><label for="socratic_notw_day">News of the Week Post Day</label></th>
						<td>
							<select id="socratic_notw_day" name="socratic_notw_day">
								<?php
								$options = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',  );
								$current_option = get_option( 'socratic_notw_day' );
								foreach ( $options as $option ) {
									echo '<option value="' . esc_attr( $option ) . '"' . selected( $current_option, $option, false ) . '>' . esc_html( $option ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<!-- NEws of the week category field -->
					<tr>
						<th scope="row">News of the Week Post Category</th>
						<td>
							<?php
								$categories = $this->get_post_categories_for_notw_category();
								$current_value = get_option( 'socratic_notw_post_category' );
								echo '<select name="socratic_notw_post_category" name="socratic_notw_post_category">';
								foreach ( $categories as $category ) {
									echo '<option value="' . esc_attr( $category->term_id ) . '"' . selected( $category->term_id, $current_value, false ) . '>' . esc_html( $category->name ) . '</option>';
								}
								echo '</select>';
							?>

						</td>
					</tr>
					<!-- Include other categories field -->
					<tr>
						<th scope="row">Include "other" category links in News of the Week posts</th>
						<td>
							<?php
								$include_other_cat = get_option( 'socratic_include_other_category_links' );
								if ( ! $include_other_cat || empty( $include_other_cat ) ) {
									$include_other_cat = 'no';
								}
							?>
							<?php echo '<label><input type="radio" name="socratic_include_other_category_links" value="yes"' . checked( 'yes', $include_other_cat, false ) . '>Yes</label>'; ?>
							<?php echo '<label><input type="radio" name="socratic_include_other_category_links" value="no"' . checked( 'no', $include_other_cat, false ) . '>No</label>'; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<button id="create-notw-post" type="button" class="button">Create NOTW Post</button>

			<div id="notw-result"></div> <!-- This div will contain the fetched data -->

			<div id="spinner" style="display: none;">Some spinner</div>

			<?php submit_button(); ?>
		</form>
		<?php

	}//end socrates_tab_output__notw_settings()


	/**
	 * Sanitize the starting prompt,reserving the newlines.
	 *
	 * @param  string $prompt
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function sanitize_starting_prompt( $prompt ) {

		return wp_kses_post( nl2br( $prompt ) );

	}//end sanitize_starting_prompt()


	/**
	 * Sanitize the feeds
	 *
	 * @param  array $feeds
	 *
	 * @return array Sanitized feeds
	 * @since 3.0.1
	 */
	public function sanitize_feeds( $feeds ) {

		foreach ( $feeds as $index => $feed ) {
			$feeds[$index]['url']    = esc_url_raw( $feed['url'] );
			$feeds[$index]['weight'] = sanitize_text_field( $feed['weight'] );
		}

		return $feeds;

	}//end sanitize_feeds()

	/**
	 * Sanitize the categories
	 */
	public function sanitize_categories( $input ) {
		return array_map( 'sanitize_text_field', $input );
	}


	/**
	 * We need to know which category to put the news of the week posts in.
	 *
	 * @return array
	 * @since 3.0.1
	 */
	public function get_post_categories_for_notw_category() {

		$categories = get_terms( array(
			'taxonomy' => 'category',
			'hide_empty' => false,
		) );

		return $categories;

	}//end get_post_categories_for_notw_category()

	/**
	 * Register and enqueue our admin JS
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function admin_enqueue_scripts__socrates_enqueue_admin_js() {

		wp_enqueue_script(
			'socrates-admin',
			plugins_url( '/socrates-admin.js', dirname( dirname( __FILE__ ) ) ),
			array(),
			'1.0',
			true
		);
		// Create nonce
		wp_localize_script( 'socrates-admin', 'socrates_obj', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'socrates_nonce' ),
		));

	}//end admin_enqueue_scripts__socrates_enqueue_admin_js()

}
