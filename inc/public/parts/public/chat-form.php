<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<form method="POST" id="socratic_reply_form">

	<textarea class="socratic-reply resizable-textarea" placeholder="Your reply" name="socratic_reply" id="socratic_reply" rows="2"></textarea><br />

	<div class="socrates-submit-and-spinner">
		<button name="socratic_submit" id="socratic_submit" class="socratic_submit socratic_button" type="submit">Reply</button>
		<span class="socrates-spinner"></span>
		<a href="?socratic-chat-delete&chat_id=<?php echo esc_attr( $chat_id_to_load ); ?>" id="socratic_delete" class="socratic_delete socratic_button">Delete Chat</a>
	</div>

</form>
