/* globals newspack_popups_view */

/**
 * Specify a function to execute when the DOM is fully loaded.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/packages/dom-ready/
 *
 * @param {Function} callback A function to execute after the DOM is ready.
 * @return {void}
 */
export function domReady( callback ) {
	if ( typeof document === 'undefined' ) {
		return;
	}
	if (
		document.readyState === 'complete' || // DOMContentLoaded + Images/Styles/etc loaded, so we call directly.
		document.readyState === 'interactive' // DOMContentLoaded fires at this point, so we call directly.
	) {
		return void callback();
	}
	// DOMContentLoaded has not fired yet, delay callback until then.
	document.addEventListener( 'DOMContentLoaded', callback );
}

/**
 * Get all prompts on the page.
 *
 * @return {Array} Array of prompt elements.
 */
export const getPrompts = () => {
	return [ ...document.querySelectorAll( '.newspack-popup' ) ];
};

/**
 * Get raw prompt ID number from element ID name.
 *
 * @param {string} id Element ID of the prompt.
 *
 * @return {number} Raw ID number from the element ID.
 */
export const getRawId = id => {
	const parts = id.split( '_' );
	return parseInt( parts[ parts.length - 1 ] );
};

/**
 * Close an overlay when its close button is clicked.
 *
 * @param {Event} event Dispatched click event.
 */
export const closeOverlay = event => {
	const parent = event.currentTarget.closest( '.newspack-lightbox' );

	if ( parent && parent.contains( event.currentTarget ) ) {
		parent.setAttribute( 'amp-access-hide', true );
		parent.style.display = 'none';
	}

	event.preventDefault();
};

/**
 * Log debugging data if WP_DEBUG is set.
 *
 * @param {string} key  Key name for debug data.
 * @param {any}    data Data to log.
 */
export const debug = ( key, data ) => {
	if ( ! newspack_popups_view.debug ) {
		return;
	}

	window.newspack_popups_debug = window.newspack_popups_debug || {};
	window.newspack_popups_debug[ key ] = data;
};
