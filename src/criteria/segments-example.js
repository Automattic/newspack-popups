/* globals newspackPopupsSegments */
import { debug, getCriteria } from './utils';

/**
 * Match reader to segments.
 */
const segments = newspackPopupsSegments || {};

window.newspackRAS = window.newspackRAS || [];
window.newspackRAS.push( () => {
	/**
	 * Whether the reader matches the segment criteria.
	 */
	const match = segmentCriteria => {
		for ( const item of segmentCriteria ) {
			const criteria = getCriteria( item.criteria_id );
			if ( ! criteria ) {
				continue;
			}
			if ( ! criteria.matches( item ) ) {
				return false;
			}
		}
		return true;
	};

	/**
	 * Close an overlay when its close button is clicked.
	 *
	 * @param {Event} event Dispatched click event.
	 */
	const closeOverlay = event => {
		const parent = event.currentTarget.closest( '.newspack-lightbox' );

		if ( parent && parent.contains( event.currentTarget ) ) {
			parent.setAttribute( 'amp-access-hide', true );
			parent.style.display = 'none';
		}

		event.preventDefault();
	};

	/**
	 * Get the reader's highest-priority segment match.
	 *
	 * @return {string|null} Segment ID, or null.
	 */
	const getBestPrioritySegment = () => {
		const matchingSegments = [];
		for ( const segmentId in segments ) {
			if ( match( segments[ segmentId ].criteria ) ) {
				matchingSegments.push( {
					id: segmentId,
					priority: segments[ segmentId ].priority,
				} );
			}
		}

		if ( ! matchingSegments.length ) {
			return null;
		}

		matchingSegments.sort( ( a, b ) => a.priority - b.priority );

		return matchingSegments[ 0 ].id;
	};

	/**
	 * Check the reader's activity against a given prompt's assigned segments.
	 *
	 * @param {Array}  assignedSegments Array of segment IDs assigned to the prompt.
	 * @param {string} matchingSegment  ID of the reader's highest-priority matching segment.
	 * @return {boolean} True if the prompt should be displayed, false if not.
	 */
	const shouldPromptBeDisplayed = ( assignedSegments, matchingSegment ) => {
		// If no assigned segments, it should be shown to everyone.
		if ( ! assignedSegments ) {
			return true;
		}

		// If the reader matches a segment assigned to the prompt, it should be shown to the reader.
		if ( matchingSegment && assignedSegments.includes( matchingSegment ) ) {
			return true;
		}

		// TODO: By prompt frequency.

		// TODO: By scroll trigger.

		return false;
	};

	const prompts = [ ...document.querySelectorAll( '.newspack-popup' ) ];
	const matchingSegment = getBestPrioritySegment();
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
