import { setMatchingAttribute } from '../utils';

const matchingAttribute = ( { store } ) => {
	const value = document.referrer
		? ( new URL( document.referrer ).hostname.replace( 'www.', '' ) || '' ).toLowerCase()
		: '';
	// Persist the referrer in the store.
	if ( value ) {
		store.set( 'referrer', value );
	}
	return store.get( 'referrer' );
};

setMatchingAttribute( 'sources_to_match', matchingAttribute );
setMatchingAttribute( 'sources_to_exclude', matchingAttribute );
