/**
 * User Account criteria matching function.
 */
import { setMatchingFunction } from '../utils';

setMatchingFunction( 'Account', ( config, { store } ) => {
	if ( config.value === 'with-account' ) {
		return window?.newspackPopupsCriteria?.is_non_preview_user || !! store.get( 'reader' )?.email;
	}
	if ( config.value === 'without-account' ) {
		return ! window?.newspackPopupsCriteria?.is_non_preview_user && ! store.get( 'reader' )?.email;
	}
	return true;
} );
