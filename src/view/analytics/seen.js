import { getEventPayload, getObserver, getRawId, sendEvent } from '../utils';

/**
 * Event fired when a prompt becomes visible in the viewport.
 *
 * @param {Array} prompts Array of prompts.
 */
export const manageSeenEvents = prompts => {
	prompts.forEach( prompt => {
		const handleEvent = () => {
			const promptId = getRawId( prompt.getAttribute( 'id' ) );
			const payload = getEventPayload( 'seen', promptId );
			sendEvent( payload );
		};

		getObserver( handleEvent ).observe( prompt, { attributes: true } );
	} );
};
