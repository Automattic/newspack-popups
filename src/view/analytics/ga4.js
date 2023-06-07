/* global gtag */

import { manageLoadedEvents } from './loaded';
import { getPrompts } from '../utils';

export const manageAnalytics = () => {
	// Must have a gtag instance to proceed.
	if ( 'function' === typeof gtag ) {
		// Fetch all prompts on the page just once.
		const prompts = getPrompts();

		manageLoadedEvents( prompts );
	}
};
