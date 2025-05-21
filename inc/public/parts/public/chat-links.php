<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Only output if we have any links.
if ( ! $links || ! is_array( $links ) || empty( $links ) ) {
	return;
}

$links_preamble = get_option( 'socratic_links_preamble', 'Here are some links which may help with your reply. You may choose to use them or not.' );
$links_preamble = sanitize_text_field( $links_preamble );

?>

<p class="links-intro-text"><?php echo esc_html( $links_preamble ); ?></p>

<ul class="socrates-links">
	<?php foreach ( $links as $key => $link ) : ?>
		<li><a target="_blank" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['title'] ); ?></a></li>
	<?php endforeach; ?>
</ul>
