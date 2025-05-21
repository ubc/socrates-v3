<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use \UBC\CTLT\Socrates_Helpers as Helpers;

/**
 * Hook to allow us to externally run code before the public output.
 */
do_action( 'socrates_v3_before_public_output' );

if ( ! is_user_logged_in() ) {
	echo Helpers::show_error_message( 'must_log_in' );
	return;
}

$socratic_chats   = Helpers::get_socratic_chats_for_user( get_current_user_id() );
$chat_id_from_url = Helpers::socrates_get_chat_id_from_url();

// Are we creating a new chat?
$new_chat = Helpers::is_this_a_new_chat();

// Which chat to load?
$chat_id_to_load = Helpers::get_chat_id_to_load();

// Can this user see this chat?
if ( ! $new_chat && ! Helpers::can_user_view_chat( get_current_user_id(), $chat_id_to_load ) ) {
	echo Helpers::show_error_message( 'user_cant_view_chat' );
	return;
}

if ( $new_chat ) {
	// If we're creating a new chat, we need to set the starting point.
	// Start with the initial prompt.
	$default_chat = Helpers::socrates_generate_starting_meta_state( $chat_id_to_load );

	$socratic_chats[ $chat_id_to_load ] = $default_chat;
}

$chat_to_load = $socratic_chats[ $chat_id_to_load ];

// Now check if the chat we're trying to load is marked as 'deleted'.
if ( array_key_exists( 'deleted', $chat_to_load ) && true === $chat_to_load['deleted'] ) {
	echo "The requested chat is not available for your user account. <a href='" . $url . "'>Start a new chat</a>.";
	return;
}

$existing_messages = ( array_key_exists( 'messages', $chat_to_load ) ) ? $chat_to_load['messages'] : array();



// Vars we must have by this point:
// [ ] $socratic_chats
// [ ] $chat_id_to_load
// [ ] $new_chat
// [ ] $existing_messages
// [ ] $links
?>

<div class="socrates-container">

	<div class="socratic-chat-wrapper">

		<?php include plugin_dir_path( __FILE__ ) . 'parts/public/chat-list.php'; ?>

		<?php include plugin_dir_path( __FILE__ ) . 'parts/public/chat-form.php'; ?>

	</div>


	<div class="socratic-list-of-chats-wrapper">
		<div class="soc-conv-and-details">
			<p class="your-socratic-conversations-topper">Your Socratic Conversations:</p>

			<?php if ( ! $socratic_chats || ( is_array( $socratic_chats ) && count ( $socratic_chats ) <= 1 ) ) : ?>
				<p>This is your first/only socratic conversation</p>
			<?php else : ?>

			<ul class="socratic-list-of-chats">
				<?php foreach ( $socratic_chats as $chat_id => $chat ) : ?>
					<?php $active_class = ( $chat_id === $chat_id_to_load ) ? ' class="active"' : ''; ?>
					<?php $current_text = ( $chat_id === $chat_id_to_load ) ? '<span class="current-tag">Current</span>' : ''; ?>
					<li <?php echo $active_class; ?>>
						<?php echo $current_text; ?><a href="?chat_id=<?php echo esc_html( $chat_id ); ?>"><?php echo esc_html( $chat['summary'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php endif; ?>

			<button name="socratic_new_chat" id="socratic_new_chat" class="socratic_new_chat">Start New</button>
		</div>
	</div>

</div>

