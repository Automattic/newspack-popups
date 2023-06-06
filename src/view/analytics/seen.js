import { getEventPayload, getRawId } from '../utils';

// The minimum continuous amount of time the prompt must be in the viewport before being considered visible.
const MINIMUM_VISIBLE_TIME = 250;

// The minimum percentage of the prompt that must be in the viewport before being considered visible.
const MINIMUM_VISIBLE_PERCENTAGE = 0.5;

/**
 * Execute a callback function to send a GA event when a prompt becomes visible.
 *
 * @param {Function} handleEvent Callback function to execute when the prompt becomes eligible for display.
 * @param {number}   promptId    ID of the prompt being observed.
 * @return {IntersectionObserver} Observer instance.
 */
const getObserver = ( handleEvent, promptId ) => {
	const timers = {};
	const observer = new IntersectionObserver(
		entries => {
			entries.forEach( observerEntry => {
				if ( observerEntry.isIntersecting ) {
					if ( ! timers[ promptId ] ) {
						timers[ promptId ] = setTimeout( () => {
							handleEvent();
							observer.unobserve( observerEntry.target );
						}, MINIMUM_VISIBLE_TIME || 0 );
					}
				} else if ( timers[ promptId ] ) {
					clearTimeout( timers[ promptId ] );
					timers[ promptId ] = false;
				}
			} );
		},
		{
			threshold: MINIMUM_VISIBLE_PERCENTAGE,
		}
	);

	return observer;
};

/**
 * Event fired when a prompt becomes visible in the viewport.
 *
 * @param {Array} prompts Array of prompts.
 */
export const manageSeenEvents = prompts => {
	prompts.forEach( prompt => {
		const promptId = getRawId( prompt.getAttribute( 'id' ) );
		const payload = getEventPayload( 'seen', promptId );
		const handleEvent = () => {
			console.log( 'event name', 'prompt_interaction' );
			console.log( 'event payload', payload );
		};

		getObserver( handleEvent, promptId ).observe( prompt, { attributes: true } );
	} );
};
