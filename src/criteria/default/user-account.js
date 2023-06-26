import { setMatchingFunction } from '../utils';

setMatchingFunction( 'user_account', ( config, { store } ) => {
	switch ( config.value ) {
		case 1:
			return store.get( 'reader' )?.email;
		case 2:
			return ! store.get( 'reader' )?.email;
	}
} );
