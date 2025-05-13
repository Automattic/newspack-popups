/**
 * Internal dependencies
 */
import { domReady, logPageview } from './utils';

if (typeof window !== 'undefined') {
	domReady(() => {
		// Log a pageview for frequency counts.
		window.newspackRAS = window.newspackRAS || [];
		window.newspackRAS.push(logPageview);
	});
}
