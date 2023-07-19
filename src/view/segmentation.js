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
		let overlayDisplayed;

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
					// Only show one overlay at a time.
					if ( ! overlayDisplayed ) {
						overlayDisplayed = true;
						const scroll = prompt.getAttribute( 'data-scroll' );
						if ( scroll ) {
							// By scroll trigger.
							const marker = document.getElementById( `page-position-marker_${ promptId }` );
							if ( marker ) {
								getObserver( unhide ).observe( marker );
							}
						} else {
							// By delay.
							const delay = prompt.getAttribute( 'data-delay' ) || 0;
							setTimeout( unhide, delay );
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
