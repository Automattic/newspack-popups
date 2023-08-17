/* globals newspack_popups_view */

import {
	debug,
	closeOverlay,
	getBestPrioritySegment,
	getIntersectionObserver,
	getRawId,
	getOverride,
	handleSeen,
	logPageview,
	shouldPromptBeDisplayed,
} from './utils';

/**
 * Match reader to segments.
 */
export const handleSegmentation = prompts => {
	const maybeDisplayPrompts = ( ras = null ) => {
		const segments = newspack_popups_view?.segments || {};
		const matchingSegment = getBestPrioritySegment( segments );
		debug( 'matchingSegment', matchingSegment );
		// Log a pageview for frequency counts.
		if ( ras ) {
			logPageview( ras );
		}
		let overlayDisplayed;

		prompts.forEach( prompt => {
			const promptId = prompt.getAttribute( 'id' );
			const isOverlay = prompt.classList.contains( 'newspack-lightbox' );
			const override = getOverride( getRawId( promptId ), isOverlay, overlayDisplayed );

			// Attach event listeners to overlay close buttons.
			const closeButtons = [
				...prompt.querySelectorAll( '.newspack-lightbox__close, button.newspack-lightbox-overlay' ),
			];
			closeButtons.forEach( closeButton => {
				closeButton.addEventListener( 'click', closeOverlay );
			} );
			// Check segmentation.
			const shouldDisplay = shouldPromptBeDisplayed( prompt, matchingSegment, ras, override );

			// Only show one overlay at a time.
			if ( ! overlayDisplayed && isOverlay && shouldDisplay ) {
				overlayDisplayed = true;
			}

			// Unhide the prompt.
			if ( shouldDisplay ) {
				const unhide = () => {
					prompt.classList.remove( 'hidden' );

					// Log a "prompt_seen" activity when the prompt becomes visible.
					if ( ras ) {
						handleSeen( prompt, ras );
					}
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
	};

	// If no segments to handle.
	if ( ! newspack_popups_view.segments ) {
		maybeDisplayPrompts();
	} else {
		window.newspackRAS = window.newspackRAS || [];
		window.newspackRAS.push( maybeDisplayPrompts );
	}
};
