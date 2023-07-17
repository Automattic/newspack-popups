import { getCriteria } from '../../criteria/utils';

/**
 * Whether the reader matches the segment criteria.
 */
export const match = segmentCriteria => {
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
 * @param {Array}  assignedSegments Array of segment IDs assigned to the prompt.
 * @param {string} matchingSegment  ID of the reader's highest-priority matching segment.
 * @return {boolean} True if the prompt should be displayed, false if not.
 */
export const shouldPromptBeDisplayed = ( assignedSegments, matchingSegment ) => {
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
