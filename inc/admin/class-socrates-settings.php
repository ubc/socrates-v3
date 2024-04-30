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
			'Socrates Settings',
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

		// Socrates settings
		register_setting( 'socrates_socrates_settings', 'socratic_socratic_starting_prompt', array(
			'sanitize_callback' => array( $this, 'sanitize_llm_starting_prompt' )
		) );

		register_setting( 'socrates_socrates_settings', 'socratic_socratic_initial_reply', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_socrates_settings', 'socratic_hide_links_in_reply', array(
			'sanitize_callback' => array( $this, 'sanitize_checkbox' )
		) );

		register_setting( 'socrates_socrates_settings', 'socratic_links_preamble', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );


		// LLM settings
		register_setting( 'socrates_llm_settings', 'socratic_generative_ai_tool', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_llm_settings', 'socratic_chatgpt_api_key', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_llm_settings', 'socratic_chatgpt_model', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_llm_settings', 'socratic_anthropic_api_key', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_llm_settings', 'socratic_anthropic_model', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		register_setting( 'socrates_llm_settings', 'socratic_anthropic_model_version', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		// NOTW settings
		register_setting( 'socrates_notw_settings', 'socratic_notw_starting_prompt', array(
			'sanitize_callback' => array( $this, 'sanitize_llm_starting_prompt' )
		) );

		register_setting( 'socrates_notw_settings', 'socratic_feeds', array(
			'sanitize_callback' => array( $this, 'sanitize_feeds' )
		) );

		register_setting( 'socrates_notw_settings', 'socratic_categories', array(
			'sanitize_callback' => array( $this, 'sanitize_categories' )
		) );

		register_setting( 'socrates_notw_settings', 'socratic_minimum_threshold_score', array(
			'sanitize_callback' => 'absint'
		) );

		register_setting( 'socrates_notw_settings', 'socratic_link_collection_cadence', array(
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
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'socrates_settings';

		?>

		<h1><?php _e( 'Socrates Settings' ); ?></h1>

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

			<a href="?page=socrates&tab=socrates_settings" class="nav-tab <?php echo $active_tab == 'socrates_settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Socratic Method Settings' ); ?></a>
			<a href="?page=socrates&tab=news_of_the_week" class="nav-tab <?php echo $active_tab == 'news_of_the_week' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Source Material Settings' ); ?></a>
			<a href="?page=socrates&tab=llm_settings" class="nav-tab <?php echo $active_tab == 'llm_settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'GenAI Settings' ); ?></a>

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
			case 'socrates_settings':
				$this->socrates_tab_output__socrates_settings();
				break;

			case 'llm_settings':
				$this->socrates_tab_output__llm_settings();
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
	public function socrates_tab_output__socrates_settings() {

		?>
		<form method="post" id="prompt-form" action="options.php">

			<?php
			settings_fields( 'socrates_socrates_settings' );
			do_settings_sections( 'socrates_socrates_settings' );

			$default_socratic_starting_prompt = "The Socratic method is a form of cooperative argumentative dialogue between individuals, based on asking and answering questions to stimulate critical thinking and to draw out ideas and underlying presuppositions. The Socratic method is a method of hypothesis elimination, in that better hypotheses are found by steadily identifying and eliminating those that lead to contradictions.

The Socratic method searches for general commonly held truths that shape beliefs and scrutinizes them to determine their consistency with other beliefs. The basic form is a series of questions formulated as tests of logic and fact intended to help a person or group discover their beliefs about some topic, explore definitions, and characterize general characteristics shared by various particular instances.

Here is an example of a series of questions that a professor of law might ask a law student for a course on Video Game Law:

1. Name a digital world issue that interests you in 5 words or under.
2. Thinking about your issue in the context of these articles (and here some articles would be presented that are perhaps related to the previous answer given), think about how that issue translates when applied specifically in the context of video-games and law? Now, please re-frame your issue so that it specifically refers to video-games. Do so in 5 words or under.
3. Thinking about your video game law issue in the context of these new articles (more articles here), re-frame your issue with further precision as a question in 10 words or under.
4. Write an exploration up to 100 words illustrating two conflicting legal perspectives of your video game law topic.
5. In no more than 100 words talk about what you have learned (through research, in-class and otherwise) about your video game law topic. Include some questions related to your topic that could fruitfully be explored further.

However, these questions are too generic.

Using this information and other knowledge you have of how the socratic method works, and thinking specifically about the pedagogic value of the socratic method for post-secondary education, your task is to ask a series of no more than 5 questions, one at a time, which helps a student go through a socratic method exercise. Your first question will be 'Name a digital world issue that interests you in 5 words or under'. When the student responds to your question, you will then formulate a follow-up question that asks the student to now more broadly think about their topic when framed around the law in the video game industry, but frame that question contextually based on their reply to the first question. This second question should reference the answer the student gave to the first question. Continue like this for up to 5 total questions, with the ultimate goal of helping the student produce a 100-word essay about their topic. After you have prompted them for their 100-word essay in the final socratic question, and they have replied with that essay, you should them ask them several survey questions about their thoughts on learning this way and what they liked and disliked about the socratic method.

Your role is of the person asking the questions. You should not answer those questions. So please only provide the questions, one by one. You do not need to give any introductory comments, just the questions. So, things such as \"Certainly, let's begin...\" etc. do not need to be part of your replies. Only ask the questions.

Your reply to this prompt should be the first question to ask the student, and then subsequent replies will follow the above logic.";

			$default_initial_reply = 'Question 1: Name a digital world issue that interests you in 5 words or under.';

?>

			<table class="form-table" role="presentation">
				<tbody>
					<!-- Socratic Starting Prompt field -->
					<tr>
						<th scope="row"><label for="starting-prompt">Socratic Starting Prompt</label></th>
						<td>
						<textarea id="starting-prompt" name="socratic_socratic_starting_prompt" rows="40" class="large-text code" cols="50"><?php echo esc_textarea( get_option( 'socratic_socratic_starting_prompt', $default_socratic_starting_prompt ) ); ?></textarea>
							<p class="description">This is the prompt that is sent to the Large Language Model.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="starting-prompt">Socratic Starting Prompt Initial Reply</label></th>
						<td>
							<input type="text" id="socratic_socratic_initial_reply" name="socratic_socratic_initial_reply" class="large-text" value="<?php echo esc_attr( get_option( 'socratic_socratic_initial_reply', $default_initial_reply ) ); ?>">
							<p class="description">This will be the first thing the person using this tool (your student/visitor) will see.</p>
						</td>
					</tr>

					<!-- Hide Links in Reply Checkbox field -->
					<tr>
						<th scope="row"><label for="hide-links-in-reply">Hide links in reply</label></th>
						<td>
							<input type="checkbox" id="hide-links-in-reply" name="socratic_hide_links_in_reply" value="1" <?php checked( get_option( 'socratic_hide_links_in_reply' ), 1 ); ?>>
							<p class="description"><label for="hide-links-in-reply">As part of the reply from the GenAI tool we can append a set of up to 3 links for the user to optionally view to help them with their answer. Checking this box will not show those links.</label></p>
						</td>
					</tr>

					<!-- Links Preamble Text Field -->
					<tr>
						<th scope="row"><label for="links-preamble">Links preamble</label></th>
						<td>
							<input type="text" id="links-preamble" name="socratic_links_preamble" value="<?php echo esc_attr( get_option( 'socratic_links_preamble' ) ); ?>" class="large-text">
							<p class="description">Before the links are shown to the user, this text is shown. Describe what the links are for, perhaps mentioning they are optional (if valid).</p>
						</td>
					</tr>


				</tbody>
			</table>

			<?php submit_button(); ?>

		</form>

		<?php


	}//end socrates_tab_output__socrates_settings()


	/**
	 * Feed settings tab output.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function socrates_tab_output__llm_settings() {

		?>
		<!-- Feed Settings tab content -->
		<form method="post" id="feed-form" action="options.php">
			<?php
			settings_fields( 'socrates_llm_settings' );
			do_settings_sections( 'socrates_llm_settings' );

			?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="socratic_generative_ai_tool">GenerativeAI tool to use</label></th>
						<td>
							<select id="socratic_generative_ai_tool" name="socratic_generative_ai_tool">
							<?php

								$available_tools = array(
									'chatgpt' => 'ChatGPT by OpenAI',
									'claude' => 'Claude by Anthropic',
								);

								// Filter the tools so it's editable externally
								$available_tools = apply_filters( 'socratic_generative_ai_tools', $available_tools );

								$current_tool = get_option( 'socratic_generative_ai_tool', 'chatgpt' );
								foreach ( $available_tools as $tool => $tool_name ) {
									echo '<option value="' . esc_attr( $tool ) . '"' . selected( $current_tool, $tool, false ) . '>' . esc_html( $tool_name ) . '</option>';
								}
							?>
							</select>
						</td>
					</tr>

					<tr class="tool-settings chatgpt-settings">
						<th scope="row"><label for="socratic_chatgpt_api_key">ChatGPT API Key</label></th>
						<td>
							<input type="text" id="socratic_chatgpt_api_key" name="socratic_chatgpt_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'socratic_chatgpt_api_key' ) ); ?>">
							<p class="description">You can create one from the <a href="https://platform.openai.com/account/api-keys">OpenAI API Keys page</a>.</p>
						</td>
					</tr>
					<!-- ChatGPT Model field -->

					<tr class="tool-settings chatgpt-settings">
						<th scope="row"><label for="socratic_chatgpt_model">ChatGPT Model To Use</label></th>
						<td>
							<select id="socratic_chatgpt_model" name="socratic_chatgpt_model">
								<?php
								$models = array(
									'gpt-3.5-turbo-0613' => 'ChatGPT 3.5 Turbo 0613 (legacy) $$',
									'gpt-3.5-turbo'      => 'ChatGPT 3.5 Turbo Latest $$',
									'gpt-3.5-turbo-16k'  => 'ChatGPT 3.5 Turbo Latest with 16K Context $$$',
									'gpt-4-1106-preview' => 'ChatGPT 4 Turbo with 128K Context $$$$$',
									'gpt-4'              => 'ChatGPT 4 with 8K Context $$$$',
									'gpt-4-32k'          => 'ChatGPT 4 with 32K Context $$$$',
								);
								$current_model = get_option( 'socratic_chatgpt_model' );
								foreach ( $models as $model => $model_name ) {
									echo '<option value="' . esc_attr( $model ) . '"' . selected( $current_model, $model, false ) . '>' . esc_html( $model_name ) . '</option>';
								}
								?>
							</select>
							<p class="description">Note: Different models cost different amounts.</p>
						</td>
					</tr>

					<tr class="tool-settings claude-settings">
						<th scope="row"><label for="socratic_anthropic_api_key">Anthropic API Key</label></th>
						<td>
							<input type="text" id="socratic_anthropic_api_key" name="socratic_anthropic_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'socratic_anthropic_api_key' ) ); ?>">
							<p class="description">You can create one from the <a href="https://console.anthropic.com/">Anthropic Console screen</a>.</p>
						</td>
					</tr>

					<tr class="tool-settings claude-settings">
						<th scope="row"><label for="socratic_anthropic_model">Anthropic Model To Use</label></th>
						<td>
							<select id="socratic_anthropic_model" name="socratic_anthropic_model">
								<?php
								$anthropic_models = array(
									'claude-2'         => 'Claude 2.0 Latest $$',
									'claude-instant-1' => 'Claude Instant 1.0 $',
								);
								$current_anthropic_model = get_option( 'socratic_anthropic_model' );
								foreach ( $anthropic_models as $anthropic_model => $anthropic_model_name ) {
									echo '<option value="' . esc_attr( $anthropic_model ) . '"' . selected( $current_anthropic_model, $anthropic_model, false ) . '>' . esc_html( $anthropic_model_name ) . '</option>';
								}
								?>
							</select>
							<p class="description">Note: Different models cost different amounts.</p>
						</td>
					</tr>

					<tr class="tool-settings claude-settings">
						<th scope="row"><label for="socratic_anthropic_model">Anthropic Model Version To Use</label></th>
						<td>
							<input type="text" id="socratic_anthropic_model_version" name="socratic_anthropic_model_version" class="regular-text" value="<?php echo esc_attr( get_option( 'socratic_anthropic_model_version' ) ); ?>">
							<p class="description">i.e. 2023-06-01</p>
						</td>
					</tr>

					<?php do_action( 'socrates_tab_output__llm_settings' ); ?>

				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php

	}//end socrates_tab_output__llm_settings()


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

			// Get the feeds data
			$feeds = get_option( 'socratic_feeds' );
			if ( ! $feeds ) {
				$feeds = array( array( 'url' => '', 'weight' => '' ) );
			}

			$default_notw_starting_prompt = "Here are titles and excerpts for some blog posts. Rate each on a scale of 1-10 with how likely they are to discuss topics loosely connected to the video game industry, i-gaming, technology, digital media, and especially legal topics associated with any of those, with 1 being the least likely and 10 being most likely. Also give a confidence score for each of your ratings as a percentage, the more confident you are in giving the correct rating, the higher the percentage. Finally, for each, provide a category descriptor, i.e. if the post discusses video games, you should categorize the post as 'video games'. Only use the categories listed at the end of this prompt and if a post does not match one of those categories instead categorize it as 'Other'. Your response should be in the format:\r\n\r\nPost W :: Score X/10 :: Confidence Y% :: Category Z\r\n\r\nwhere W is the number of the post, X is your score out of 10, Y is your confidence percentage, and Z is the singular category that best describes the post. Do not write anything other than this for each post. Start each on a new line.";

			// Get the categories data
			$categories = get_option( 'socratic_categories' );
			if ( ! $categories ) {
				$categories = array( '' );
			}
			?>

			<table class="form-table" role="presentation">
				<tbody>
					<!-- Starting Prompt field -->
					<tr>
						<th scope="row"><label for="starting-prompt">Starting Prompt</label></th>
						<td>
							<textarea id="starting-prompt" name="socratic_notw_starting_prompt" rows="13" class="large-text code" cols="50"><?php echo wp_kses_post( str_replace( array( '<br>', '<br/>', '<br />'), "\n", get_option( 'socratic_notw_starting_prompt', $default_notw_starting_prompt ) ) ); ?></textarea>
							<p class="description">This is the prompt that is sent to the Large Language Model. The list of links to check and your chosen link categories will also be appended to this prompt.</p>
						</td>
					</tr>
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
							<p class="description">On what day will the news of the week post be created?</p>
						</td>
					</tr>
					<!-- NEws of the week category field -->
					<tr>
						<th scope="row">News of the Week Post Category</th>
						<td>
							<?php
								$notw_categories = $this->get_post_categories_for_notw_category();
								$current_value = get_option( 'socratic_notw_post_category' );
								echo '<select name="socratic_notw_post_category" name="socratic_notw_post_category">';
								foreach ( $notw_categories as $category ) {
									echo '<option value="' . esc_attr( $category->term_id ) . '"' . selected( $category->term_id, $current_value, false ) . '>' . esc_html( $category->name ) . '</option>';
								}
								echo '</select>';
							?>
							<p class="description">Into which category should the news of the week post be placed?</p>
						</td>
					</tr>
					<!-- Include other categories field -->
					<tr>
						<th scope="row">Include "other" category?</th>
						<td>
							<?php
								$include_other_cat = get_option( 'socratic_include_other_category_links' );
								if ( ! $include_other_cat || empty( $include_other_cat ) ) {
									$include_other_cat = 'no';
								}
							?>
							<?php echo '<label><input type="radio" name="socratic_include_other_category_links" value="yes"' . checked( 'yes', $include_other_cat, false ) . '>Yes</label>'; ?>
							<?php echo '<label><input type="radio" name="socratic_include_other_category_links" value="no"' . checked( 'no', $include_other_cat, false ) . '>No</label>'; ?>
							<p class="description">When the LLM is unable to categorize a link it will place it in the 'other' category. Would you like to include these in your news of the week posts? Other links will be after all other categories.</p>
						</td>
					</tr>
					<!-- Feeds fields -->
					<tr>
						<th scope="row">
							<label for="feed-url-0">Feed URLs</label>
							<p class="description" style="font-weight: normal;">Your list of RSS Feeds</p>
						</th>
						<td>
							<div id="feeds-container">
							<?php foreach ( $feeds as $index => $feed ): ?>
								<div class="feed-row" style="margin-bottom: 0.5em;">
									<label for="feed-url-<?php echo $index; ?>">Feed URL</label>
									<input type="text" class="regular-text" style="margin-right: 2em;" id="feed-url-<?php echo $index; ?>" name="socratic_feeds[<?php echo $index; ?>][url]" value="<?php echo esc_url($feed['url']); ?>">
									<label for="feed-weight-<?php echo $index; ?>">Weight</label>
									<input type="text" class="small-text" id="feed-weight-<?php echo $index; ?>" name="socratic_feeds[<?php echo $index; ?>][weight]" value="<?php echo esc_attr($feed['weight']); ?>">
									<button type="button" style="min-width: 32px;" class="delete-feed button button-secondary dashicons dashicons-no"></button>
								</div>
							<?php endforeach; ?>
							</div>
							<button type="button" class="button button-secondary" id="add-feed" style="margin-top: 0.5em;">Add New Feed</button>
						</td>
					</tr>
					<!-- Categories fields -->
					<tr>
						<th scope="row">
							<label for="category-0">Content Categories</label>
							<p class="description" style="font-weight: normal;">The LLM will try to assign links to one of these categories and they will appear in your News of the Week post as headings.</p>
						</th>
						<td>
							<div id="categories-container">
							<?php foreach ($categories as $index => $category): ?>
								<div class="category-row" style="margin-bottom: 0.5em;">
									<label for="category-<?php echo $index; ?>"></span></label>
									<input type="text" class="regular-text" id="category-<?php echo $index; ?>" name="socratic_categories[<?php echo $index; ?>]" value="<?php echo esc_attr( $category ); ?>">
									<button type="button" class="delete-category button button-secondary dashicons dashicons-no" style="min-width: 32px;"></button>
								</div>
							<?php endforeach; ?>
							</div>
							<button type="button" class="button button-secondary" id="add-category" style="margin-top: 0.5em;">Add Category</button>
						</td>
					</tr>
					<!-- Threshold Score field -->
					<tr>
						<th scope="row"><label for="socratic_minimum_threshold_score">Threshold Score</label></th>
						<td>
							<input type="text" id="socratic_minimum_threshold_score" name="socratic_minimum_threshold_score" class="small-text" value="<?php echo esc_attr( get_option( 'socratic_minimum_threshold_score' ) ); ?>">
							<p class="description">Any link that the LLM scores below this threshold will not be added to your links collection.</p>
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
							<p class="description">How often will links be collected from your feeds?</p>
							<p id="notice" class="notice notice-info is-dismissible" style="display: none;">This may mean you miss the occasional post from some feeds which produce a lot of content each week</p>
						</td>
					</tr>
				</tbody>
			</table>

			<button id="fetch-data" type="button" class="button">Fetch Feeds</button>

			<div id="data-container"></div> <!-- This div will contain the fetched data -->

			<button id="create-notw-post" type="button" class="button">Create NOTW Post</button>

			<div id="notw-result"></div> <!-- This div will contain the fetched data -->

			<div id="spinner" style="display: none;"><span class="spinner is-active"></span></div>

			<?php submit_button(); ?>
		</form>
		<?php

	}//end socrates_tab_output__notw_settings()


	/**
	 * Sanitize the starting prompts,reserving the newlines.
	 *
	 * @param  string $prompt
	 *
	 * @return string
	 * @since 3.0.1
	 */
	public function sanitize_llm_starting_prompt( $prompt ) {
		// Just sanitize, no need to convert line breaks to <br> tags.
		return wp_kses_post( $prompt );
	}// end sanitize_llm_starting_prompt()


	/**
	 * Sanitize the input for checkboxes.
	 *
	 * @param mixed $input
	 * @return int Either 1 or 0.
	 * @since 3.0.1
	 */
	public function sanitize_checkbox( $input ) {
		// If the checkbox has been checked, return 1, otherwise return 0.
		return ( isset( $input ) && true == $input ) ? 1 : 0;
	}// end sanitize_checkbox()


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
