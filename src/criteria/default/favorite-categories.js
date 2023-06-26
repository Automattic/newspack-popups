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
	/* TODO: Decide how to rank categories. */
	return false;
} );
