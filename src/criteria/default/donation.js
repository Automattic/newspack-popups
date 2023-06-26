import { setMatchingFunction } from '../utils';

setMatchingFunction( 'donation', ( config, { store } ) => {
	switch ( config.value ) {
		case 1:
			return store.get( 'is_donor' );
		case 2:
			return ! store.get( 'is_donor' );
		case 3:
			return store.get( 'is_former_donor' );
	}
} );
