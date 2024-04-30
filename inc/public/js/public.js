document.addEventListener( 'DOMContentLoaded', function() {

	const container = document.querySelector( '.socrates-container' );

	// Function to adjust the layout based on the container's width
	function adjustLayout() {

		if ( container.offsetWidth > 800 ) {
			container.classList.add( 'wide-layout' );
		} else {
			container.classList.remove( 'wide-layout' );
		}
	}

	// Initial check
	adjustLayout();

	// Checking when the window resizes
	window.addEventListener( 'resize', adjustLayout );

} );

/**
 * Auto-resizing textarea based on content.
 *
 * This script allows for automatically adjusting the height of any textarea
 * element based on its content. The textarea will start with a height
 * sufficient for 2 lines and will grow vertically when the content exceeds
 * the current height.
 *
 * How it works:
 * - The `makeResizable` function sets up the necessary event listeners
 *   on the provided textarea element to enable auto-resizing.
 * - Textareas with the class "resizable-textarea" will have this behavior applied.
 */
function socratesMakeResizableTextarea( textarea ) {

    function resizeTextarea() {
        if ( textarea.scrollHeight > textarea.clientHeight ) {
            textarea.style.height = textarea.scrollHeight + 'px';
        }
    }//end resizeTextarea()

    // Set the initial height based on 2 lines of content.
    const computedStyle = getComputedStyle( textarea );
    const lineHeight = parseInt( computedStyle.lineHeight, 10 );
    textarea.style.height = lineHeight * 2 + 'px'; // 2 lines of content

    // Add event listeners to the textarea.
    textarea.addEventListener( 'input', resizeTextarea );
    textarea.addEventListener( 'keydown', resizeTextarea );

}//end socratesMakeResizableTextarea()

// Run the script on all textareas with the class "resizable-textarea".
document.addEventListener( 'DOMContentLoaded', function() {

    const textareas = document.querySelectorAll( '.resizable-textarea' );
    textareas.forEach( socratesMakeResizableTextarea );

} );
