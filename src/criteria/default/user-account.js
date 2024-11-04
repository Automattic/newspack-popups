/* globals newspackPopupsCriteria */
import { setMatchingFunction } from '../utils';

setMatchingFunction( 'user_account', ( config, { store } ) => {
	const reader = store?.get?.( 'reader' );
	switch ( config.value ) {
		case 'with-account':
			return newspackPopupsCriteria.is_non_preview_user || ( reader?.email && reader?.authenticated );
		case 'without-account':
			return ! newspackPopupsCriteria.is_non_preview_user && ( ! reader?.email || ! reader?.authenticated );
	}
} );
