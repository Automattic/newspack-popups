/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import './style.scss';
import './patterns.scss';

const manageForms = container => {
	const forms = [ ...container.querySelectorAll( 'form.popup-action-form' ) ];
	forms.forEach( form => {
		form.addEventListener( 'submit', event => {
			const XHR = new XMLHttpRequest();
			const inputs = [ ...form.querySelectorAll( 'input' ) ];
			const pairs = inputs.map(
				( { name, value } ) => encodeURIComponent( name ) + '=' + encodeURIComponent( value )
			);
			const data = pairs.join( '&' ).replace( /%20/g, '+' );
			const action = form.attributes[ 'action-xhr' ].value;
			XHR.open( 'POST', action );
			XHR.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			XHR.send( data );
			event.preventDefault();
		} );
	} );
};

if ( typeof window !== 'undefined' ) {
	domReady( () => {
		const campaignArray = [ ...document.querySelectorAll( '.newspack-lightbox' ) ];
		campaignArray.forEach( campaign => {
			manageForms( campaign );
		} );
	} );
}
