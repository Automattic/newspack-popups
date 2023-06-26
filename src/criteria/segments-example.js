import { getCriteria } from './utils';

/**
 * Sample segments.
 */
const segments = [
	{
		id: 'segment-1',
		criteria: {
			articles_read: { min: 1 },
		},
	},
];

window.newspackRAS = window.newspackRAS || [];
window.newspackRAS.push( () => {
	/**
	 * Whether the reader matches the segment criteria.
	 */
	const matchSegment = segment => {
		for ( const criteriaId in segment.criteria ) {
			const criteria = getCriteria( criteriaId );
			if ( ! criteria ) {
				continue;
			}
			const config = segment.criteria[ criteriaId ];
			if ( ! criteria.matches( config ) ) {
				return false;
			}
		}
		return true;
	};
	/**
	 * Match sample segments.
	 */
	for ( const segment of segments ) {
		// eslint-disable-next-line no-console
		console.log( {
			segmentId: segment.id,
			matched: matchSegment( segment ),
		} );
	}
} );
