/**
 * Socrates Block
 *
 * A WordPress block to fetch and display data from the Socrates API.
 *
 * @package socrates-v3-block
 */

// Import necessary WordPress dependencies.
const { registerBlockType } = wp.blocks;
const { RawHTML } = wp.element;

/**
 * Fetch and update the block's inner HTML.
 *
 * @param {string} clientId The client ID of the block.
 */
const fetchAndUpdateBlockHTML = ( clientId ) => {
	fetch( `${wpApiSettings.root}socrates/v3/editor` )
		.then( ( response ) => response.json() )
		.then( ( data ) => {
			const blockElement = document.getElementById( clientId );

			// Ensure the block element exists before updating its HTML.
			if ( blockElement ) {
				blockElement.innerHTML = data.html;
			}
		} )
		.catch( ( error ) => {
			console.error( `Failed to fetch data: ${error}` );
		} );
};

// Register the Socrates block.
registerBlockType( 'socrates-v3/socrates-v3-block', {

	title: 'Socrates',
	icon: 'admin-comments',
	category: 'common',

	/**
	 * The edit function describes the structure of your block in the context of the editor.
	 * This represents what the editor will render when the block is used.
	 *
	 * @param {Object} props The block properties.
	 * @return {Object} Block editor UI.
	 */
	edit: function( props ) {
		fetchAndUpdateBlockHTML( props.clientId );

		return RawHTML( { children: `<div id="${props.clientId}"></div>` } );
	},

	/**
	 * The save function defines the way in which the different attributes should be combined
	 * into the final markup, which is then serialized by Gutenberg into `post_content`.
	 *
	 * @return {string} HTML content to be saved.
	 */
	save: function() {
		return '<div class="socrates-v3-block"></div>';
	},
} );
