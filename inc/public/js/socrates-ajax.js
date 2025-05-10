/**
 * Utility to add a new list item to the chat.
 *
 * @param {string} message - The content to add.
 * @param {string} chatId  - The ID of the chat container.
 *
 * @return {Object} - The added list item.
 */
function addListItemToChat( message, chatId ) {

    const chat     = document.getElementById( chatId );
    const listItem = document.createElement( 'li' );

    // Convert newline characters to <br> tags for proper rendering
    message = message.replace(/\n/g, '<br>');

	// Add the message to the li
    listItem.innerHTML = message;

	// Ensure it starts with 0 opacity and then fades in
    listItem.style.opacity = '0';

	// Add the li to the chat
    chat.appendChild( listItem );

	// Animate the fade-in
    animateFadeIn( listItem );

    return listItem;

}//end addListItemToChat()

/**
 * Removes the specified list item from the chat.
 *
 * @param {Object} listItem - The list item to remove.
 */
function removeListItemFromChat( listItem ) {
    listItem.remove();
}//end removeListItemFromChat()

/**
 * Animates the fade-in of a given element.
 *
 * @param {Object} element - The element to fade in.
 */
function animateFadeIn( element ) {

    setTimeout( () => {
        element.style.transition = 'opacity 0.5s';
        element.style.opacity    = '1';
    }, 0 );

}//end animateFadeIn()

/**
 * Clears the content of the specified textarea.
 *
 * @param {string} textareaId - The ID of the textarea to clear.
 */
function clearTextarea( textareaId ) {

    const textarea = document.getElementById( textareaId );
    textarea.value = '';

}//end clearTextarea()

/**
 * Applies an error state to the given form.
 *
 * @param {string} formId - The ID of the form to modify.
 */
function applyErrorStateToForm( formId ) {

    const form = document.getElementById( formId );

    form.classList.add( 'form-submission-error' );

}//end applyErrorStateToForm()

/**
 * Clears an error state from the given form.
 *
 * @param {string} formId - The ID of the form to modify.
 */
function clearErrorStateFromForm( formId ) {

    const form = document.getElementById( formId );

    form.classList.remove( 'form-submission-error' );

}//end clearErrorStateFromForm()

/**
 * Sets the error message next to the submit button.
 *
 * @param {string} formId - The ID of the form.
 * @param {string} message - The error message to display.
 */
function setErrorNextToSubmit( formId, message ) {

    const form   = document.getElementById( formId );
    let errorMsg = form.querySelector( '.socrates-error-message' );

    if ( ! errorMsg ) {
        errorMsg = document.createElement( 'span' );
        errorMsg.className = 'socrates-error-message';
        form.appendChild( errorMsg );
    }

    errorMsg.textContent = message;

}//end setErrorNextToSubmit()

/**
 * Clears the error message from the form.
 *
 * @param {string} formId - The ID of the form.
 */
function clearErrorMessage( formId ) {

    const form     = document.getElementById( formId );
    const errorMsg = form.querySelector( '.socrates-error-message' );

    if ( errorMsg ) {
        errorMsg.remove();
    }

}//end clearErrorMessage()

/**
 * generateLinksHTML - Generates a structured HTML string from a provided array of link objects.
 *
 * This function takes in an array of link objects, where each object has a 'url' and 'title' property.
 * It then constructs an unordered list (`<ul>`) where each link object is transformed into a list item (`<li>`).
 * Inside each list item is an anchor (`<a>`) with the link's URL and title.
 *
 * The generated HTML string is then returned to be consumed or rendered elsewhere.
 *
 * Using template literals in this function allows for a clean, easily readable, and maintainable structure,
 * especially when compared to creating and appending DOM elements programmatically.
 *
 * @param {Array} links - An array of link objects, where each object has a 'url' and 'title' property.
 * @return {string} - A string representation of the HTML constructed from the provided links.
 *
 * @example
 *
 * const exampleLinks = [
 *   { url: 'https://example.com', title: 'Example Link' },
 *   { url: 'https://openai.com', title: 'OpenAI Link' }
 * ];
 *
 */
function generateLinksHTML( links ) {

	// Check if the provided 'links' parameter is not an array or if it's an empty array.
    // If either condition is true, return an empty string.
    if ( ! Array.isArray( links ) || links.length === 0 ) {
        return '';
    }

    // Using a template literal to construct the outer structure of the list.
    return `
	<p class="links-intro-text">Here are some links which may help with your reply. You may choose to use them or not.</p>
    <ul class="socrates-links">
        ${
            // Using the 'map' function to iterate over each link object in the array.
            // The 'map' function transforms each link object into a string representation of a list item.
			// Inside the list item, we construct an anchor element.
			// The 'href' attribute is set to the link's URL and the text content is set to the link's title.
			// The 'target="_blank"' ensures the link opens in a new tab or window when clicked.
            links.map( link => `
                <li><a target="_blank" href="${link.url}">${link.title}</a></li>
            ` )
            // The 'join' function is used to concatenate the individual strings from the 'map' operation
            // into a single string without any separators. This is crucial to avoid unwanted commas in the output.
            .join('')
        }
    </ul>
    `;
}

/**
 * Truncates a given text string after a specified number of words.
 *
 * @param {string} text - The string of text to truncate.
 * @param {number} wordLimit - The maximum number of words allowed before truncation.
 * @returns {string} - The truncated string.
 */
function truncateText( text, wordLimit ) {

    const words = text.split( ' ' );

	if ( words.length > wordLimit ) {
        return words.slice( 0, wordLimit ).join(' ') + '...';
    }

    return text;

}//end truncateText()

/**
 * Updates the text content of the anchor tag following a .current-tag span.
 *
 * @param {string} text - The text to set in the anchor tag.
 */
function updateAnchorText( text ) {

    const anchor = document.querySelector( '.active .current-tag + a' );

	if ( ! anchor ) {
		return;
	}

    anchor.textContent = text;

}//end updateAnchorText()

/**
 * Function to reload the page with a specific query string, removing all others.
 */
function reloadWithQueryString( newQueryString ) {
    // Get the current location without any query parameters.
    // 'location.origin' gives the base URL, and 'location.pathname' gives the path after the domain.
    var baseUrl = window.location.origin + window.location.pathname;

    // Redirect to the base URL with the new query string.
    window.location.href = baseUrl + newQueryString;
}

/**
 * Add event listener to the button when the DOM is fully loaded.
 */
document.addEventListener( 'DOMContentLoaded', function () {
    // Select the button by its ID.
    var button = document.getElementById( 'socratic_new_chat' );

    // Ensure the button exists to prevent errors.
    if ( button ) {
        // Add click event listener to the button.
        button.addEventListener( 'click', function ( event ) {
            // Prevent the default button action.
            event.preventDefault();

            // Reload the page with the new query string.
            reloadWithQueryString( '?new-socratic-chat=1' );
        } );
    }
} );


/**
 * When someone types in the textarea, we want to update the anchor tag text
 */
document.addEventListener('DOMContentLoaded', function() {

	// Grab the anchor and textarea elements.
	const anchor       = document.querySelector('.active .current-tag + a');
	const textarea     = document.getElementById('socratic_reply');
	const submitButton = document.getElementById('socratic_submit');

	// Code guard to ensure we only proceed if the anchor exists.
	if ( ! anchor || ! textarea || ! submitButton) {
		return;
	}

	let shouldUpdateAnchor = ! anchor.textContent;

	// Add an input event listener to the textarea.
	textarea.addEventListener( 'input', function( event ) {

		if ( ! shouldUpdateAnchor ) {
			return;
		}

		const truncatedValue = truncateText( event.target.value, 5 );
		updateAnchorText( truncatedValue );

	} );

	// Listen for a click event on the submit button.
	submitButton.addEventListener( 'click', function() {
		shouldUpdateAnchor = false;
	} );

} );

// Wait for the DOM to load
document.addEventListener( 'DOMContentLoaded', function() {

    const form = document.getElementById( 'socratic_reply_form' );

    if ( form ) {

        form.addEventListener( 'submit', function( event ) {

			event.preventDefault();

			// Add a spinner after the submit button
			const submitButton = form.querySelector( '[type="submit"]' );
            const spinner = form.querySelector('.socrates-spinner');

			// Show the spinner
            spinner.style.display = 'block';

            // Disable the submit button
            submitButton.disabled = true;

			// We need the ID of the chat that this form is appending to.
            const chatWrapper = event.target.closest( '.socratic-chat-wrapper' );
            const olElement   = chatWrapper.querySelector( '.socratic-chat' );
            const chatId      = olElement.id;

			// This is what the user just submitted.
            const reply = document.getElementById( 'socratic_reply' ).value;

            // Placeholder text can be adjusted via plugin settings.
            const thinkingText = socratesAjax.thinkingText || 'Thinking...';

			// Add what the user just replied to the chat.
            addListItemToChat( reply, chatId );

			// Add a placeholder as we wait for the server's response.
            const thinkingListItem = addListItemToChat( thinkingText, chatId );

			// Send along the actual ChatID which is a data attribute.
			const usableChatID = olElement.dataset.chatId;

			// Send if this is a new chat
			const isNewChat = olElement.dataset.newSocraticChat;

			// Clear any existing error message before making the new request.
            clearErrorMessage( 'socratic_reply_form' );

			// Make the AJAX request.
            fetch( socratesAjax.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams( {
                    'action': 'handle_socrates_ajax',
                    'nonce': socratesAjax.nonce,
                    'reply': reply,
					'chatID': usableChatID,
					'isNewChat': isNewChat,
                } )
            } )

            .then( response => {
				return response.json();
			} )

			.then( data => {
				// Once we receive the response, re-enable the submit button and hide the spinner
                submitButton.disabled = false;
                spinner.style.display = 'none';

                if ( data.success ) {

					// Clear the error state from the form if present
                    clearErrorStateFromForm( 'socratic_reply_form' );
					clearErrorMessage( 'socratic_reply_form' );

					// Clear the thinking placeholder content before adding new content.
					thinkingListItem.innerHTML = '';

					// --- Debugging Log ---
					console.log('AJAX Success Data:', data.data);
					console.log('Reasoning content received:', data.data.reasoning);
					// --- End Debugging Log ---

					// Check if reasoning data exists and has content.
					if ( data.data.reasoning && data.data.reasoning.trim() !== '' ) {
						// Create the <details> element for reasoning.
						const details = document.createElement('details');
						// details.open = false; // Closed by default
						const summary = document.createElement('summary');
						summary.textContent = "Socrates's Thoughts"; // Consider making translatable
						const reasoningDiv = document.createElement('div');
						// Sanitize reasoning content appropriately if needed, though backend already did wp_kses_post
						reasoningDiv.innerHTML = data.data.reasoning.replace(/\n/g, '<br>');

						details.appendChild(summary);
						details.appendChild(reasoningDiv);
						thinkingListItem.appendChild(details); // Prepend details section
					}

					// Create a div for the main response and links.
					const mainResponseDiv = document.createElement('div');
					mainResponseDiv.innerHTML = data.data.message.replace(/\n/g, '<br>') + generateLinksHTML( data.data.links );

					// Append the main response div.
					thinkingListItem.appendChild(mainResponseDiv);

					// Apply fade-in animation now that content is set.
					animateFadeIn(thinkingListItem);

					// Clear the textarea to be ready for another reply.
					clearTextarea( 'socratic_reply' );

                } else {

					let errorMessage = data.data.message || 'An unknown error occurred.';

					// Set the error message next to the submit button
                    setErrorNextToSubmit( 'socratic_reply_form', errorMessage );

					// When there's an error, we remove the user's reply from the chat, remove the placeholder, and apply an error state to the form.
					removeListItemFromChat( thinkingListItem.previousElementSibling );
                    removeListItemFromChat( thinkingListItem );
                    applyErrorStateToForm( 'socratic_reply_form' );

                }
            } )

            .catch( error => {
				// In case of an error in fetching itself, re-enable the submit button and hide the spinner
                submitButton.disabled = false;
                spinner.style.display = 'none';
				removeListItemFromChat( thinkingListItem.previousElementSibling );
				removeListItemFromChat( thinkingListItem );
				applyErrorStateToForm( 'socratic_reply_form' );
                console.error( 'Error with AJAX request:', error );
            } );
        } );
    }
} );

// Delete Button : #socratic_delete handle this being pressed and sending an AJAX request.
document.addEventListener( 'DOMContentLoaded', function() {

    const deleteButton = document.getElementById( 'socratic_delete' );

    if ( deleteButton ) {

        deleteButton.addEventListener( 'click', function( event ) {

			event.preventDefault();

			// Add an Are You sure check
			if ( ! confirm( 'Are you sure you want to delete this chat?' ) ) {
				return;
			}

			// Clear the error state from the form if present
			clearErrorStateFromForm( 'socratic_reply_form' );
			clearErrorMessage( 'socratic_reply_form' );

			const chatWrapper = event.target.closest( '.socratic-chat-wrapper' );
			const olElement   = chatWrapper.querySelector( '.socratic-chat' );
			const usableChatID = olElement.dataset.chatId;

			// Make the AJAX request.
			fetch( socratesAjax.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					'action': 'socrates_delete_chat',
					'nonce': socratesAjax.nonce,
					'chatID': usableChatID,
				} )
			} )

			.then( response => {
				return response.json();
			} )

			.then( data => {

				if ( data.success ) {

					// Reload the page with the new query string.
					reloadWithQueryString( '' );

				} else {

					let errorMessage = data.data.message || 'An unknown error occurred.';

					// Set the error message next to the submit button
                    setErrorNextToSubmit( 'socratic_reply_form', errorMessage );

				}
			} )

			.catch( error => {

				console.error( 'Error with AJAX request:', error );
			} );

		} );

	}

} );
