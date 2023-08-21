/**
 * Internal dependencies
 */
import './style.scss';
import './patterns.scss';
import { handleSegmentation } from './segmentation';
import { handleAnalytics } from './analytics/ga4';
import { domReady, getPrompts } from './utils';

if ( typeof window !== 'undefined' ) {
	domReady( () => {
		// Fetch all prompts on the page just once.
		const prompts = getPrompts();

		handleSegmentation( prompts );
		handleAnalytics( prompts );
	} );
}
