<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check to see if we're displaying links or not.
$hide_links = get_option( 'socratic_hide_links_in_reply' );
$hide_links = absint( $hide_links );

$new_chat_data = ( true === $new_chat ) ? 'data-new-socratic-chat="1"' : '';

// Deleted?
if ( array_key_exists( 'deleted', $socratic_chats[ $chat_id_to_load ] ) && false !== $socratic_chats[ $chat_id_to_load ]['deleted'] ) {
	echo "<p class='socratic-chat-deleted'>This chat was marked as deleted by the user at: " . $socratic_chats[ $chat_id_to_load ]['deleted'] . "</p>";
}

?>

<ol class="socratic-chat" id="soc-<?php echo esc_attr( $chat_id_to_load ); ?>" data-chat-id="<?php echo esc_attr( $chat_id_to_load ); ?>" <?php echo $new_chat_data; ?>>

	<?php foreach ( $existing_messages as $mkey => $message ): ?>

		<?php
			// We don't show the initial prompt as that's the one that sets the rules/guard rails.
			// @TODO: Make this an option.
			if ( $message['role'] === 'user' && 0 !== $mkey ) :
		?>
			<li><?php echo wp_kses_post( $message['content'] ); ?></li>
		<?php endif; ?>

		<?php if ( $message['role'] === 'assistant' ): ?>
			<li>
				<?php echo wp_kses_post( $message['content'] ); ?>
				<?php
					// If we're showing links, show them
					if ( 1 !== $mkey && 1 !== $hide_links ) {
						$links = \UBC\CTLT\Socrates_Helpers::socrates_get_links_shown_for_specific_prompt( $mkey, $chat_id_to_load );
						include plugin_dir_path( __FILE__ ) . 'chat-links.php';
					}
				?>
			</li>
		<?php endif; ?>

	<?php endforeach; ?>

</ol>

