/* globals newspack_popups_view */

import { debug, closeOverlay, getBestPrioritySegment, shouldPromptBeDisplayed } from './utils';

/**
 * Match reader to segments.
 */
export const handleSegmentation = () => {
	window.newspackRAS = window.newspackRAS || [];
	window.newspackRAS.push( () => {
		const prompts = [ ...document.querySelectorAll( '.newspack-popup' ) ];
		const segments = newspack_popups_view?.segments || {};
		const matchingSegment = getBestPrioritySegment( segments );
		debug( 'matchingSegment', matchingSegment );

		prompts.forEach( prompt => {
			// Attach event listners to overlay close buttons.
			const closeButton = prompt.querySelector( '.newspack-lightbox__close' );

			if ( closeButton ) {
				closeButton.addEventListener( 'click', closeOverlay );
			}

			// Check segmentation.
			const assignedSegments = prompt.getAttribute( 'data-segments' )
				? prompt.getAttribute( 'data-segments' ).split( ',' )
				: null;
			const shouldDisplay = shouldPromptBeDisplayed( assignedSegments, matchingSegment );

			// Unhide the prompt.
			if ( shouldDisplay ) {
				const delay = prompt.getAttribute( 'data-delay' ) || 0;

				setTimeout( () => {
					prompt.classList.remove( 'hidden' );
				}, delay );
			}

			// Debug logging for prompt display.
			const promptId = prompt.getAttribute( 'id' );
			debug( promptId, shouldDisplay );
		} );
	} );
};
