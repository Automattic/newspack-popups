import { getEventPayload } from '../utils';

/**
 * Event fired as soon as a prompt is loaded in the DOM.
 *
 * @param {Array} prompts Array of prompts loaded in the DOM.
 */
export const manageLoadEvents = prompts => {
	prompts.forEach( prompt => {
		const payload = getEventPayload( 'loaded', 'passive', prompt );

		console.log( 'event name', 'prompt_interaction' );
		console.log( 'event payload', payload );
	} );
};
