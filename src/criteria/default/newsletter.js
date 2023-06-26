import { setMatchingFunction } from '../utils';

setMatchingFunction( 'newsletter', ( config, { store } ) => {
	switch ( config.value ) {
		case 1:
			return store.get( 'is_subscriber' );
		case 2:
			return ! store.get( 'is_subscriber' );
	}
} );
