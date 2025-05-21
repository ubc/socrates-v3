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

document.addEventListener('DOMContentLoaded', function() {
    let feedsContainer = document.getElementById('feeds-container'),
        addFeedButton = document.getElementById('add-feed');

	// If neither element exists, we're not on the feeds page, so return early
    if (!feedsContainer || !addFeedButton) {
        return;
    }

    let feedIndex = getMaxFeedIndex(feedsContainer) + 1;

    // Add a new feed
    addFeedButton.addEventListener('click', function() {
        let newRow = document.createElement('div');

        newRow.classList.add('feed-row');
        newRow.innerHTML = `
            <label for="feed-url-${feedIndex}">Feed URL</label>
            <input type="text" class="regular-text" style="margin-right: 2em;" id="feed-url-${feedIndex}" name="socratic_feeds[${feedIndex}][url]">
            <label for="feed-weight-${feedIndex}">Weight</label>
            <input type="text" class="small-text" id="feed-weight-${feedIndex}" name="socratic_feeds[${feedIndex}][weight]">
            <button type="button" class="delete-feed button button-secondary dashicons dashicons-no" style="min-width: 32px;"></button>
        `;

        feedsContainer.appendChild(newRow);
        feedIndex++;
    });

    // Delete a feed
    feedsContainer.addEventListener('click', function(e) {
        console.log([e, e.target]);
        if (e.target.classList.contains('delete-feed')) {
            e.target.parentNode.remove();
        }
    });

    // Helper function to get the maximum feed index currently in use
    function getMaxFeedIndex(container) {
        let maxIndex = 0;
        let feedRows = container.querySelectorAll('.feed-row');
        feedRows.forEach(function(row) {
            let inputs = row.querySelectorAll('input');
            inputs.forEach(function(input) {
                let match = input.id.match(/feed-url-(\d+)/);
                if (match && match[1]) {
                    let index = parseInt(match[1], 10);
                    if (index > maxIndex) {
                        maxIndex = index;
                    }
                }
            });
        });
        return maxIndex;
    }
});


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

document.addEventListener('DOMContentLoaded', function() {

    var findRSSFeedsButton = document.getElementById( 'find-rss-feeds' );

	if ( ! findRSSFeedsButton ) {
		return;
	}

	findRSSFeedsButton.addEventListener( 'click', function() {

		var xhr = new XMLHttpRequest();
		var params = 'action=find_rss_feeds&nonce=' + socrates_obj.nonce;

		xhr.open( 'POST', socrates_obj.ajax_url, true );
		xhr.setRequestHeader( 'Content-type', 'application/x-www-form-urlencoded' );

		xhr.onload = function() {
			if ( this.status >= 200 && this.status < 400 ) {
				// Success!
				document.getElementById( 'find-rss-feeds-result' ).innerHTML = this.response;
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

// Dynamic NOTW Prompt Preview Generation
document.addEventListener('DOMContentLoaded', function() {
    const focusDescriptionInput = document.getElementById('socratic_notw_focus_description');
    const emphasisAspectInput = document.getElementById('socratic_notw_rating_emphasis_aspect');
    const categoriesContainer = document.getElementById('categories-container');
    const promptPreviewTextarea = document.getElementById('socratic_notw_prompt_preview');
    const addCategoryButton = document.getElementById('add-category');

    // Check if we are on the correct settings page
    if (!focusDescriptionInput || !emphasisAspectInput || !categoriesContainer || !promptPreviewTextarea || !addCategoryButton) {
        return;
    }

    function updateNotwPromptPreview() {
        const focusDescription = focusDescriptionInput.value.trim();
        const emphasisAspect = emphasisAspectInput.value.trim();
        const categoryInputs = categoriesContainer.querySelectorAll('.category-row input[type="text"]');
        let categories = [];
        categoryInputs.forEach(input => {
            const categoryValue = input.value.trim();
            if (categoryValue) {
                categories.push(categoryValue);
            }
        });

        let prompt = `You will be provided with a list of blog posts (<blog_posts>...</blog_posts>) containing titles and excerpts.
Your task is to analyze each post and rate its relevance to the main subject area: ${focusDescription || '[Please define a Main Subject Area]'}.
\n`;

        if (emphasisAspect) {
            prompt += `When rating, pay specific attention to: ${emphasisAspect}.\n\n`;
        } else {
            prompt += `\n`;
        }

        prompt += `Assign a score from 1 (least relevant) to 10 (most relevant) and a confidence percentage for your rating.
Finally, categorize each post using ONLY ONE of the following categories:\n`;

        if (categories.length > 0) {
            categories.forEach(category => {
                prompt += `- ${category}\n`;
            });
        } else {
            prompt += `[Please add at least one category]\n`;
        }
        prompt += `- Other (Use this if no other category fits)\n\n`;

        prompt += `Your response MUST be a single valid JSON object.\n`;
        prompt += `This JSON object must contain a single key named "results".\n`;
        prompt += `The value of the "results" key MUST be a JSON array.\n`;
        prompt += `Each element in the "results" array MUST be a JSON object corresponding to one of the analyzed blog posts.\n`;
        prompt += `Each post object within the "results" array MUST have the following structure and keys: \n`;
        prompt += '`{"post_id": number, "score": number, "confidence": number, "category": "string"}`\n\n';

        prompt += `Example Input Posts:\n`;
        prompt += `post: 1\n`;
        prompt += `title: AI Wins Art Prize\n`;
        prompt += `excerpt: An AI generated image won first place... implications for copyright...\n`;
        prompt += `post: 2\n`;
        prompt += `title: New Mario Game Announced\n`;
        prompt += `excerpt: Nintendo revealed the next installment... no legal issues mentioned...\n\n`;

        prompt += `Example JSON Object Output (containing a "results" array with objects for the two example posts):\n`;
        prompt += '`{\n';
        prompt += `  "results": [\n`;
        prompt += `    {"post_id": 1, "score": 8, "confidence": 95, "category": "Copyright"},\n`;
        prompt += `    {"post_id": 2, "score": 2, "confidence": 60, "category": "Other"}\n`;
        prompt += `  ]\n`;
        prompt += `}\`\n\n`;

        prompt += `Ensure the final output is ONLY the single JSON object (starting with \`{\` and ending with \`}\`), with no introductory text, explanations, or markdown formatting around the JSON itself.`;

        promptPreviewTextarea.value = prompt;
    }

    // Initial preview generation
    updateNotwPromptPreview();

    // Event listeners for direct inputs
    focusDescriptionInput.addEventListener('input', updateNotwPromptPreview);
    emphasisAspectInput.addEventListener('input', updateNotwPromptPreview);

    // Event listeners for categories container (delegation)
    categoriesContainer.addEventListener('input', function(e) {
        if (e.target.tagName === 'INPUT' && e.target.type === 'text') {
            updateNotwPromptPreview();
        }
    });

    categoriesContainer.addEventListener('click', function(e) {
        // Update preview if a category is deleted
        if (e.target.classList.contains('delete-category')) {
            // Use setTimeout to allow the DOM update (row removal) to complete before recalculating
            setTimeout(updateNotwPromptPreview, 0);
        }
    });

    // Event listener for adding a category
    addCategoryButton.addEventListener('click', function() {
        // Use setTimeout to allow the DOM update (row addition) to complete before recalculating
        setTimeout(updateNotwPromptPreview, 0);
    });
});
