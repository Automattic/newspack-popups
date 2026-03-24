/**
 * Donor Status criteria matching function.
 */
import { setMatchingFunction } from '../utils';

setMatchingFunction( 'Donor_Status', ( config, { store } ) => {
	if ( config.value === 'donor' ) {
		return store.get( 'is_donor' ) === true;
	}
	if ( config.value === 'non-donor' ) {
		return store.get( 'is_donor' ) !== true && store.get( 'is_former_donor' ) !== true;
	}
	if ( config.value === 'former-donor' ) {
		return store.get( 'is_former_donor' ) === true;
	}
	return true;
} );
