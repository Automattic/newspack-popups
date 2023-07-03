/* globals newspackPopupsSegmentsExample */
import { getCriteria } from './utils';

/**
 * Sample segments.
 */
const segments = newspackPopupsSegmentsExample?.segments || [];

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
			if ( ! criteria.matches( item.value ) ) {
				return false;
			}
		}
		return true;
	};
	/**
	 * Match sample segments.
	 */
	for ( const segmentId in segments ) {
		// eslint-disable-next-line no-console
		console.log( {
			segmentId,
			matched: match( segments[ segmentId ] ),
		} );
	}
} );
