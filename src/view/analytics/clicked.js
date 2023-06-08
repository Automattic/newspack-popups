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
		const handleEvent = e => {
			const extraParams = {};

			if ( e.currentTarget?.href && '#' !== e.currentTarget?.href ) {
				extraParams.action_value = e.currentTarget.getAttribute( 'href' );
			}

			const payload = getEventPayload(
				'clicked',
				getRawId( prompt.getAttribute( 'id' ) ),
				extraParams
			);
			sendEvent( payload );
		};

		anchorLinks.forEach( link => link.addEventListener( 'click', handleEvent ) );
	} );
};
