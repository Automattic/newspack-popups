/* globals newspack_popups_view */

import {
	debug,
	closeOverlay,
	getBestPrioritySegment,
	getIntersectionObserver,
	handleSeen,
	logPageview,
	shouldPromptBeDisplayed,
} from './utils';

/**
 * Match reader to segments.
 */
export const handleSegmentation = prompts => {
	window.newspackRAS = window.newspackRAS || [];
	window.newspackRAS.push( ras => {
		const segments = newspack_popups_view?.segments || {};
		const matchingSegment = getBestPrioritySegment( segments );
		debug( 'matchingSegment', matchingSegment );
		// Log a pageview for frequency counts.
		logPageview( ras );
		let overlayDisplayed;

		prompts.forEach( prompt => {
			const promptId = prompt.getAttribute( 'id' );
			const isOverlay = prompt.classList.contains( 'newspack-lightbox' );

			// Attach event listners to overlay close buttons.
			const closeButtons = [
				...prompt.querySelectorAll( '.newspack-lightbox__close, button.newspack-lightbox-overlay' ),
			];
			closeButtons.forEach( closeButton => {
				closeButton.addEventListener( 'click', closeOverlay );
			} );

			// Check segmentation.
			const shouldDisplay = shouldPromptBeDisplayed(
				prompt,
				matchingSegment,
				ras,
				isOverlay && overlayDisplayed ? false : null
			);

			// Only show one overlay at a time.
			if ( ! overlayDisplayed && isOverlay && shouldDisplay ) {
				overlayDisplayed = true;
			}

			// Unhide the prompt.
			if ( shouldDisplay ) {
				const unhide = () => {
					prompt.classList.remove( 'hidden' );

					// Log a "prompt_seen" activity when the prompt becomes visible.
					handleSeen( prompt, ras );
				};
				if ( isOverlay ) {
					const scroll = prompt.getAttribute( 'data-scroll' );
					if ( scroll ) {
						// By scroll trigger.
						const marker = document.getElementById( `page-position-marker_${ promptId }` );
						if ( marker ) {
							getIntersectionObserver( unhide ).observe( marker );
						}
					} else {
						// By delay.
						const delay = prompt.getAttribute( 'data-delay' ) || 0;
						setTimeout( unhide, delay );
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