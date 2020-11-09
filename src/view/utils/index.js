/**
 * WordPress dependencies
 */
import { getQueryArg, removeQueryArgs } from '@wordpress/url';

export const values = object => Object.keys( object ).map( key => object[ key ] );

export const performXHRequest = ( { url, data } ) => {
	const XHR = new XMLHttpRequest();
	XHR.open( 'POST', url );
	XHR.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );

	const encodedData = Object.keys( data )
		.map( key => encodeURIComponent( key ) + '=' + encodeURIComponent( data[ key ] ) )
		.join( '&' )
		.replace( /%20/g, '+' );

	XHR.send( encodedData );
};

const getCookies = () =>
	document.cookie.split( '; ' ).reduce( ( acc, cookieStr ) => {
		const cookie = cookieStr.split( '=' );
		acc[ cookie[ 0 ] ] = cookie[ 1 ];
		return acc;
	}, {} );

export const getClientIDValue = () => getCookies()[ 'newspack-cid' ];

export const substituteDynamicValue = value => {
	if ( value && value.replace( /\s/g, '' ) === 'CLIENT_ID(newspack-cid)' ) {
		value = getClientIDValue() || '';
	}
	return value;
};

export const processFormData = ( data, formElement ) => {
	Object.keys( data ).forEach( key => {
		let value = data[ key ];
		if ( value === '${formFields[email]}' ) {
			const inputEl = formElement.querySelector( '[name="email"]' );
			if ( inputEl ) {
				value = inputEl.value;
			}
		}
		data[ key ] = substituteDynamicValue( value );
	} );
	return data;
};

export const getCookieValueFromLinker = (
	{ linkers, cookies },
	url = window.location.href,
	documentCookie = document.cookie
) => {
	let cookieValue;
	let cleanURL;
	if ( linkers && linkers.enabled && cookies && cookies.enabled ) {
		const linkerName = Object.keys( linkers ).filter( k => k !== 'enabled' )[ 0 ];
		const cookieName = Object.keys( cookies ).filter( k => k !== 'enabled' )[ 0 ];
		const linkerParam = getQueryArg( url, linkerName );
		const hasCIDCookie = documentCookie.indexOf( cookieName ) >= 0;
		cleanURL = removeQueryArgs( url, linkerName );
		if ( linkerParam && ! hasCIDCookie ) {
			// eslint-disable-next-line no-unused-vars
			const [ version, checksum, cidName, cidValue ] = linkerParam.split( '*' );
			try {
				// Strip dots, not sure why they were in a URL – maybe chrome devtools?
				const decodedCID = atob( cidValue.replace( /\./g, '' ) );
				if ( decodedCID ) {
					cookieValue = `${ cookieName }=${ decodedCID }`;
				}
			} catch ( e ) {
				// Nothingness.
			}
		}
	}
	return { cookieValue, cleanURL };
};

export const waitUntil = ( condition, callback, maxTries = 10 ) => {
	let tries = 0;
	const interval = setInterval( () => {
		tries++;
		if ( tries <= maxTries ) {
			if ( condition() ) {
				callback();
				clearInterval( interval );
			}
		} else {
			clearInterval( interval );
		}
	}, 200 );
};
