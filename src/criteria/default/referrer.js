import { setMatchingAttribute, setMatchingFunction } from '../utils';

const matchingAttribute = ( { store } ) => {
	const value = document.referrer
		? ( new URL( document?.referrer ).hostname.replace( 'www.', '' ) || '' ).toLowerCase()
		: '';
	// Persist the referrer in the store.
	if ( value ) {
		store.set( 'referrer', value );
	}
	return store.get( 'referrer' );
};

setMatchingAttribute( 'sources_to_match', matchingAttribute );
setMatchingAttribute( 'sources_to_exclude', matchingAttribute );

setMatchingFunction( 'sources_to_exclude', ( config, { store } ) => {
	let list = config.value;
	if ( typeof list === 'string' ) {
		list = config.value.split( ',' ).map( item => item.trim() );
	}
	if ( ! Array.isArray( list ) || ! list.length ) {
		return true;
	}
	const value = store.get( 'referrer' );
	if ( ! value || ! list.includes( value ) ) {
		return true;
	}
	return false;
} );
