/**
 * External dependencies
 */
import 'whatwg-fetch';

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import './style.scss';
import './patterns.scss';
import { getClientIDValue, waitUntil } from './utils';
import { manageAnalyticsLinkers, manageAnalyticsEvents } from './analytics';
import { manageForms } from './forms';

if ( typeof window !== 'undefined' ) {
	domReady( () => {
		// Handle linkers right away, before amp-access sets a cookie value.
		manageAnalyticsLinkers();

		// But don't manage analytics events until the client ID is available.
		waitUntil( getClientIDValue, manageAnalyticsEvents );

		manageForms();
	} );
}
