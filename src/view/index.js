/**
 * Internal dependencies
 */
import './style.scss';
import './patterns.scss';
import { handleSegmentation } from './segmentation';
import { manageAnalytics } from './analytics/ga4';
import { domReady } from './utils';

if ( typeof window !== 'undefined' ) {
	domReady( () => {
		handleSegmentation();
		manageAnalytics();
	} );
}
