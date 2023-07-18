/* globals newspack_popups_view */

import {
	debug,
	closeOverlay,
	getBestPrioritySegment,
	getObserver,
	handleSeen,
	logPageview,
	shouldPromptBeDisplayed,
} from './utils';

/**
 * Match reader to segments.
 */
export const handleSegmentation = prompts => {
	const segments = newspack_popups_view?.segments || {};
	const matchingSegment = getBestPrioritySegment( segments );
	debug( 'matchingSegment', matchingSegment );

	window.newspackRAS = window.newspackRAS || [];
	window.newspackRAS.push( ras => {
		// Log a pageview for frequency counts.
		logPageview( ras );

		prompts.forEach( prompt => {
			const promptId = prompt.getAttribute( 'id' );

			// Attach event listners to overlay close buttons.
			const closeButton = prompt.querySelector( '.newspack-lightbox__close' );
			if ( closeButton ) {
				closeButton.addEventListener( 'click', closeOverlay );
			}

			// Check segmentation.
			const shouldDisplay = shouldPromptBeDisplayed( prompt, matchingSegment, ras );

			// Unhide the prompt.
			if ( shouldDisplay ) {
				const unhide = () => {
					prompt.classList.remove( 'hidden' );

					// Log a "prompt_seen" activity when the prompt becomes visible.
					handleSeen( prompt, ras );
				};
				const isOverlay = prompt.classList.contains( 'newspack-lightbox' );
				if ( isOverlay ) {
					const delay = prompt.getAttribute( 'data-delay' );
					if ( null !== delay && '' !== delay ) {
						// By delay.
						setTimeout( () => unhide, delay );
					} else {
						// By scroll trigger.
						const marker = document.getElementById( `page-position-marker_${ promptId }` );
						if ( marker ) {
							getObserver( unhide ).observe( marker );
						}
					}
				} else {
					unhide();
				}
			}

			// Debug logging for prompt display.
			debug( promptId, shouldDisplay );
		} );
	} );
};
