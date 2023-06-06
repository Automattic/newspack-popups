import { getEventPayload, getRawId } from '../utils';

/**
 * Event fired as soon as a prompt is loaded in the DOM.
 *
 * @param {Array} prompts Array of prompts loaded in the DOM.
 */
export const manageLoadedEvents = prompts => {
	prompts.forEach( prompt => {
		const payload = getEventPayload( 'loaded', getRawId( prompt.getAttribute( 'id' ) ) );

		console.log( 'event name', 'prompt_interaction' );
		console.log( 'event payload', payload );
	} );
};
