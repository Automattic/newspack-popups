import { getEventPayload, getRawId } from '../utils';

/**
 * Execute a callback function to send a GA event when a prompt is dismissed.
 *
 * @param {Array} prompts Array of prompts loaded in the DOM.
 */

export const manageDismissals = prompts => {
	prompts.forEach( prompt => {
		const closeButton = prompt.querySelector( '.newspack-lightbox__close' );
		const payload = getEventPayload( 'dismiss', getRawId( prompt.getAttribute( 'id' ) ) );
		const handleEvent = () => {
			console.log( payload );
		};

		closeButton.addEventListener( 'click', handleEvent );
	} );
};
