import { getCriteria } from './utils';

/**
 * Sample segments.
 */
const segments = {
	1: [
		{
			criteria_id: 'articles_read',
			value: { min: 5, max: 10 },
		},
		{
			criteria_id: 'newsletter',
			value: 'non-subscribers',
		},
	],
	2: [
		{
			criteria_id: 'donation',
			value: 'donors',
		},
	],
};

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
	 * Match sample segments.
	 */
	for ( const segmentId in segments ) {
		// eslint-disable-next-line no-console
		console.log( {
			segmentId,
			config: segments[ segmentId ],
			matched: match( segments[ segmentId ] ),
		} );
	}
} );
