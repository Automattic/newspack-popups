import { getRawId } from './prompts';
import { getCriteria } from '../../criteria/utils';

const day = 1000 * 60 * 60 * 24;
export const periods = {
	day,
	week: day * 7,
	month: day * 30,
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
 * Get the reader's highest-priority segment match.
 *
 * @param {Object} segments Segments.
 *
 * @return {string|null} Segment ID, or null.
 */
export const getBestPrioritySegment = segments => {
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
 * @param {HTMLElement} prompt          HTML element of the prompt being checked.
 * @param {string}      matchingSegment ID of the reader's highest-priority matching segment.
 * @param {Object}      ras             Reader Data Library object.
 * @return {boolean} True if the prompt should be displayed, false if not.
 */
export const shouldPromptBeDisplayed = ( prompt, matchingSegment, ras ) => {
	// By frequency.
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
				activity.data?.prompt_id === promptId && periods[ reset ] > Date.now() - activity.timestamp
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

	return true;
};
