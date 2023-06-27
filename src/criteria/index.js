/* globals newspackPopupsCriteria */

import { registerCriteria } from './utils';

/**
 * Register criteria from the global newspackPopupsCriteria object.
 */
if ( newspackPopupsCriteria?.config ) {
	for ( const id in newspackPopupsCriteria.config ) {
		registerCriteria( id, newspackPopupsCriteria.config[ id ] );
	}
}
