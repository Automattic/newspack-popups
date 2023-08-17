import { getRawId } from './prompts';
import { getCriteria } from '../../criteria/utils';

const day = 1000 * 60 * 60 * 24;
export const periods = {
	day,
	week: day * 7,
	month: day * 30,
};

/**
 * Checks if the current page request is a segment or campaign preview.
 *
 * @return {Object|null} View as object or null.
 */
const parseViewAs = () => {
	const params = new URL( window.location ).searchParams;
	if ( params.get( 'view_as' ) ) {
		const viewAs = params
			.get( 'view_as' )
			.split( ';' )
			.reduce( ( acc, item ) => {
				const parts = item.split( ':' );
				if ( 1 === parts.length ) {
					acc[ parts[ 0 ] ] = true;
				} else {
					acc[ parts[ 0 ] ] = parts[ 1 ];
				}
				return acc;
			}, {} );
		return viewAs;
	}

	return null;
};

/**
 * Checks if the current page request is a single prompt preview.
 *
 * @return {number|null} Prompt ID, or null.
 */
export const getPreviewedPromptId = () => {
	const params = new URL( window.location ).searchParams;
	if ( params.get( 'pid' ) ) {
		return parseInt( params.get( 'pid' ) );
	}
	return null;
};

/**
 * Whether the reader matches the segment criteria.
 *
 * @param {Object} segmentCriteria Segment criteria.
 *
 * @return {boolean} True if the reader matches all of the segment's criteria, false if not.
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
 * Get the reader's highest-priority segment match, or the segment to preview.
 *
 * @param {Object} segments Segments.
 *
 * @return {string|null} Segment ID, or null.
 */
export const getBestPrioritySegment = segments => {
	// If previewing as a specific segment.
	const viewAs = parseViewAs();
	if ( viewAs?.segment ) {
		return viewAs.segment;
	}

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
 * @param {HTMLElement}  prompt          HTML element of the prompt being checked.
 * @param {string}       matchingSegment ID of the reader's highest-priority matching segment.
 * @param {Object}       ras             Reader Data Library object.
 * @param {null|boolean} override        If true or false, force the value.
 * @return {boolean} True if the prompt should be displayed, false if not.
 */
export const shouldPromptBeDisplayed = ( prompt, matchingSegment, ras, override = null ) => {
	// By override.
	if ( true === override || false === override ) {
		return override;
	}

	if ( ras ) {
		// eslint-disable-next-line @wordpress/no-unused-vars-before-return
		const [ start, between, max, reset ] = prompt.getAttribute( 'data-frequency' ).split( ',' );
		const pageviews = ras.store.get( 'pageviews' );
		if ( pageviews[ reset ] ) {
			const views = pageviews[ reset ].count || 0;

			// If reader hasn't amassed enough pageviews yet.
			if ( views <= parseInt( start ) ) {
				return false;
			}

			// If not displaying every pageview.
			if ( 0 < between ) {
				const viewsAfterStart = Math.max( 0, views - ( parseInt( start ) + 1 ) );
				if ( 0 < viewsAfterStart % ( parseInt( between ) + 1 ) ) {
					return false;
				}
			}

			// If there's a max frequency.
			const promptId = getRawId( prompt.getAttribute( 'id' ) );
			const seenEvents = ( ras.getActivities( 'prompt_seen' ) || [] ).filter( activity => {
				return (
					activity.data?.prompt_id === promptId &&
					periods[ reset ] > Date.now() - activity.timestamp
				);
			} );
			if ( 0 < parseInt( max ) && seenEvents.length >= parseInt( max ) ) {
				return false;
			}
		}

		// By assigned segments.
		const assignedSegments = prompt.getAttribute( 'data-segments' )
			? prompt.getAttribute( 'data-segments' ).split( ',' )
			: null;
		if ( assignedSegments && 0 > assignedSegments.indexOf( matchingSegment ) ) {
			return false;
		}
	}

	return true;
};

/**
 * Get an override value to supersede segmentation and frequency controls. Possible values:
 * - true - The prompt will always be displayed.
 * - false - The prompt will never be displaeyd.
 * - null (default) - Let segmentation and frequency controls determine if the prompt should be displayed.
 *
 * @param {number}  promptId         ID of the prompt to check.
 * @param {boolean} isOverlay        Whether the prompt is an overlay prompt.
 * @param {boolean} overlayDisplayed Whether another overlay prompt has already been displayed.
 *
 * @return {boolean|null} The override value to pass to the shouldPromptBeDisplayed function.
 */
export const getOverride = ( promptId, isOverlay = false, overlayDisplayed = false ) => {
	// If previewing a single prompt, it should always be displayed.
	if ( promptId === getPreviewedPromptId() ) {
		return true;
	}

	// If an overlay and another overlay has already been displayed, it should not be displaeyd.
	if ( isOverlay && overlayDisplayed ) {
		return false;
	}

	// Default behavior lets frequency/segmentation determine whether it should be dipslayed.
	return null;
};
