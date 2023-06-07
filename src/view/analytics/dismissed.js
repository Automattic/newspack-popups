import { getEventPayload, getRawId, parseOnHandlers } from '../utils';

/**
 * Execute a callback function to send a GA event when a prompt is dismissed.
 *
 * @param {Function} handleEvent Callback function to execute when the prompt is dismissed.
 * @return
 */

const manageBind = bindElement => {
	const onHandlers = parseOnHandlers( bindElement.getAttribute( 'on' ) );
	onHandlers.forEach( onHandler => {
		if ( onHandler.action === 'tap' ) {
			const handleClick = () => {
				if ( onHandler.method === 'hide' ) {
					const payload = getEventPayload( 'dismissed', getRawId( onHandler.id ) );
					console.log( 'From dismiss: event name', 'prompt_interaction' );
					console.log( 'From dismiss: event payload', payload );
				}
			};
			bindElement.addEventListener( 'click', handleClick );
		}
	} );
};

export const manageDismissals = () => {
	[ ...document.querySelectorAll( '.newspack-popup button[on]' ) ].map( manageBind );
};
