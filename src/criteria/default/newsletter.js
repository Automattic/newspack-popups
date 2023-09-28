import { setMatchingFunction } from '../utils';

setMatchingFunction( 'newsletter', ( config, { store } ) => {
	switch ( config.value ) {
		case 'subscribers':
			return store.get( 'newsletter_subscribed_lists' )?.length;
		case 'non-subscribers':
			return ! store.get( 'newsletter_subscribed_lists' )?.length;
	}
} );
