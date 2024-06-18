/**
 * This script is for handling various event actions like fetching data, adding/deleting feeds,
 * adding/deleting categories and displaying a notice based on the selected link collection cadence.
 */

// Event that triggers after the DOM content is loaded.
document.addEventListener( 'DOMContentLoaded', function() {
    let fetchDataButton = document.getElementById( 'fetch-data' ),
        startingPrompt = document.getElementById( 'starting-prompt' ),
        spinner = document.getElementById( 'spinner' );

    if ( ! fetchDataButton ) {
        return;
    }

    // Event that triggers on click of fetchDataButton
    fetchDataButton.addEventListener( 'click', function() {
        let xhr = new XMLHttpRequest(),
            params = 'action=fetch_feeds&nonce=' + socrates_obj.nonce + '&socratic_starting_prompt=' + encodeURIComponent( startingPrompt.value );

        xhr.open( 'POST', socrates_obj.ajax_url, true );
        xhr.setRequestHeader( 'Content-type', 'application/x-www-form-urlencoded' );

        // Before sending the request
        xhr.onloadstart = function() {
            fetchDataButton.disabled = true;
            spinner.style.display = 'block';
        };

        // On receiving the response
        xhr.onload = function() {
            if ( this.status >= 200 && this.status < 400 ) {
                document.getElementById( 'data-container' ).innerHTML = this.response;
            } else {
                console.error( 'AJAX error: ' + this.status );
            }

			console.log( 'ok?' );
			console.log( this.response );
            fetchDataButton.disabled = false;
            spinner.style.display = 'none';
        };

		xhr.onprogress = function() {
			if (this.status >= 200 && this.status < 400) {
				// Success!
				document.getElementById('data-container').innerHTML += this.response;
				console.log( 'ok2?' );
				console.log( this.response );
			} else {
				// Error :(
				console.error('AJAX error: ' + this.status);
			}
		};

        // In case of an error
        xhr.onerror = function() {
            console.error( 'AJAX connection error' );
            fetchDataButton.disabled = false;
            spinner.style.display = 'none';
        };

        xhr.send( params );
    } );
} );

document.addEventListener( 'DOMContentLoaded', function() {
    let feedsContainer = document.getElementById( 'feeds-container' ),
        addFeedButton = document.getElementById( 'add-feed' );

    if ( ! addFeedButton ) {
        return;
    }

    // Add a new feed
    addFeedButton.addEventListener( 'click', function() {
        let index = feedsContainer.children.length + 1;
        let newRow = document.createElement( 'div' );

        newRow.classList.add( 'feed-row' );
        newRow.innerHTML = `
            <label for="feed-url-${index}">Feed URL</label>
            <input type="text" class="regular-text" style="margin-right: 2em;" id="feed-url-${index}" name="socratic_feeds[${index}][url]">
            <label for="feed-weight-${index}">Weight</label>
            <input type="text" class="small-text" id="feed-weight-${index}" name="socratic_feeds[${index}][weight]">
            <button type="button" class="delete-feed button button-secondary dashicons dashicons-no" style="min-width: 32px;"></button>
        `;

        feedsContainer.appendChild( newRow );
    } );

    // Delete a feed
    feedsContainer.addEventListener( 'click', function(e) {
		console.log( [e, e.target] );
        if ( e.target.classList.contains( 'delete-feed' ) ) {
            e.target.parentNode.remove();
        }
    } );
} );

document.addEventListener( 'DOMContentLoaded', function() {
    let categoriesContainer = document.getElementById( 'categories-container' ),
        addCategoryButton = document.getElementById( 'add-category' );

    if ( ! addCategoryButton ) {
        return;
    }

    // Add a new category
    addCategoryButton.addEventListener( 'click', function() {
        let index = categoriesContainer.children.length,
            newRow = document.createElement( 'div' );

        newRow.classList.add( 'category-row' );
        newRow.innerHTML = `
            <label for="category-${index}"></label>
            <input type="text" class="regular-text" id="category-${index}" name="socratic_categories[${index}]">
            <button type="button" class="delete-category button button-secondary dashicons dashicons-no" style="min-width: 32px;"></button>
        `;

        categoriesContainer.appendChild( newRow );
    } );

    // Delete a category
    categoriesContainer.addEventListener( 'click', function(e) {
        if ( e.target.classList.contains( 'delete-category' ) ) {
            e.target.parentNode.remove();
        }
    } );
} );

// If the admin selects the Every Sunday at Midnight cadence, show a message letting them now some links may be missed
document.addEventListener( 'DOMContentLoaded', function() {
    let linkCollectionCadence = document.getElementById( 'link-collection-cadence' ),
        notice = document.getElementById( 'notice' );

    if ( ! linkCollectionCadence ) {
        return;
    }

    // Display a notice based on the selected link collection cadence
    linkCollectionCadence.addEventListener( 'change', function() {
        if ( this.value === 'Every Sunday at Midnight' ) {
            notice.style.display = 'block';
        } else {
            notice.style.display = 'none';
        }
    } );
} );

document.addEventListener('DOMContentLoaded', function() {

    var createNotwPostButton = document.getElementById( 'create-notw-post' );

	if ( ! createNotwPostButton ) {
		return;
	}

	createNotwPostButton.addEventListener( 'click', function() {

		var xhr = new XMLHttpRequest();
		var params = 'action=create_notw_post&nonce=' + socrates_obj.nonce;

		xhr.open( 'POST', socrates_obj.ajax_url, true );
		xhr.setRequestHeader( 'Content-type', 'application/x-www-form-urlencoded' );

		xhr.onload = function() {
			if ( this.status >= 200 && this.status < 400 ) {
				// Success!
				document.getElementById( 'notw-result' ).innerHTML = this.response;
			} else {
				// Error :(
				console.error( 'AJAX error: ' + this.status );
			}
		};

		xhr.onerror = function() {
			// Connection error
			console.error( 'AJAX connection error' );
		};

		xhr.send( params );
	} );
} );

// Show and Hide the relevant LLM Settings based on which Tool the person has selected.
document.addEventListener( 'DOMContentLoaded', function() {

    var toolSelector = document.getElementById( 'socratic_generative_ai_tool' );

	if ( ! toolSelector ) {
		return;
	}

	var allToolSettings = document.querySelectorAll( '.tool-settings' );

    var chatgptSettings = document.querySelectorAll( '.chatgpt-settings' );
    var claudeSettings  = document.querySelectorAll( '.claude-settings' );

	/**
	 * Toggles the display of settings based on the selected AI tool.
	 *
	 * This function is responsible for dynamically showing or hiding the settings
	 * rows related to the ChatGPT and Claude AI tools in the plugin's settings page.
	 * It first hides all the settings rows for both tools and then displays only
	 * the rows relevant to the currently selected AI tool in the 'GenerativeAI tool to use' dropdown.
	 *
	 * The function is designed to be called both on initial page load and in response
	 * to changes in the selection of the dropdown. On page load, it sets up the initial
	 * visibility state of the settings rows based on the saved or default value of the
	 * dropdown. When the user changes the selection in the dropdown, this function is
	 * triggered again to update the visibility of the settings rows accordingly.
	 *
	 * Usage:
	 * - Called on DOMContentLoaded to set initial settings visibility.
	 * - Bound to the 'change' event of the dropdown to update settings visibility on user interaction.
	 *
	 * Assumptions:
	 * - The tool settings for each tool have a class of {tool-name}-settings i.e. 'chatgpt-settings'.
	 *
	 * Side effects:
	 * - Directly modifies the `style.display` property of relevant HTML elements.
	 *
	 * @returns {void} This function does not return a value; it directly manipulates the DOM.
	 */
    function toggleSettings() {

        var tool = toolSelector.value;

        // Hide all settings first
		allToolSettings.forEach( function( row ) {
		  row.style.display = 'none';
		} );

		// The class of the tr will be {tool-name}-settings i.e. 'chatgpt-settings' so, formulate this class
		// name based on the tool selected.
		var settingsClass = '.' + tool + '-settings';

		// Fetch all of the selected tools' settings
		var selectedToolSettings = document.querySelectorAll( settingsClass );

		// Now show only the relevant settings
		selectedToolSettings.forEach( function( row ) {
		    row.style.display = '';
		} );

    }

    // Initial toggle on page load based on the current selection
    toggleSettings();

    // Event listener for changes in the dropdown
    toolSelector.addEventListener( 'change', toggleSettings );

} );
