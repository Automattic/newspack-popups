import { getEventPayload, getRawId, sendEvent } from '../utils';

/**
 * Execute a callback function to send a GA event when a prompt is dismissed.
 *
 * @param {Array} prompts Array of prompts loaded in the DOM.
 */

export const manageClickedEvents = prompts => {
	prompts.forEach( prompt => {
		const anchorLinks = [
			...prompt.querySelectorAll( '.newspack-inline-popup a, .newspack-popup__content a' ),
		];
		const handleEvent = () => {
			const payload = getEventPayload( 'clicked', getRawId( prompt.getAttribute( 'id' ) ) );
			sendEvent( payload );
		};

		anchorLinks.forEach( link => link.addEventListener( 'click', handleEvent ) );
	} );
};
