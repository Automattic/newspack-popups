import { setMatchingAttribute } from '../utils';

setMatchingAttribute( 'articles_read', ras => {
	const views = ras.getUniqueActivitiesBy( 'article_view', 'post_id' );
	return views.filter( view => view.timestamp > Date.now() - 30 * 24 * 60 * 60 * 1000 ).length;
} );

setMatchingAttribute( 'articles_read_in_session', ras => {
	const views = ras.getUniqueActivitiesBy( 'article_view', 'post_id' );
	return views.filter( view => view.timestamp > Date.now() - 45 * 60 * 1000 ).length;
} );
