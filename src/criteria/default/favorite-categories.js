import { setMatchingFunction } from '../utils';

setMatchingFunction( 'favorite_categories', ( config, ras ) => {
	const views = ras.getUniqueActivitiesBy( 'article_view', 'post_id' );
	const categories = views.reduce( ( c, v ) => {
		if ( v.data?.categories?.length ) {
			c.push( ...v.data.categories );
		}
		return c;
	}, [] );
	const counts = categories.reduce( ( number, category ) => {
		number[ category ] = ( number[ category ] || 0 ) + 1;
		return number;
	}, {} );
	const countsArray = Object.entries( counts );
	countsArray.sort( ( a, b ) => b[ 1 ] - a[ 1 ] );

	let match = false;

	// Must have viewed at least 2 categories or 2 posts within the same category in order to rank.
	if (
		( 1 < countsArray.length && countsArray[ 0 ][ 1 ] > countsArray[ 1 ][ 1 ] ) ||
		1 < countsArray[ 0 ][ 1 ]
	) {
		config.value.forEach( categoryId => {
			// Return true if one of the categories in the criteria is the most-read category.
			if ( parseInt( countsArray[ 0 ][ 0 ] ) === parseInt( categoryId ) ) {
				match = true;
			}
		} );
	}
	return match;
} );
