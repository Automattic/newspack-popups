/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import './style.scss';
import './patterns.scss';

const hideCampaign = container => {
	container.style.display = 'none';
	container.setAttribute( 'aria-hidden', 'true' );
};

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

			hideCampaign( container );

			event.preventDefault();
		} );
	} );
};

const maybeShowCampaign = container => {
	const campaignId = container.getAttribute( 'data-id' );
	const endpoint = `${ newspack_popups_data.endpoint }?popup_id=${ campaignId }&url=${
		newspack_popups_data.url
	}`;
	const XHR = new XMLHttpRequest();

	XHR.open( 'GET', endpoint );
	XHR.onreadystatechange = e => {
		if ( XHR.readyState === XMLHttpRequest.DONE ) {
			if ( 0 === XHR.status || ( XHR.status >= 200 && XHR.status < 400 ) ) {
				const response = JSON.parse( XHR.responseText );

				console.log( response );

				if ( true === response.displayPopup ) {
					container.style.opacity = 1;
					container.style.transform = 'none';
					container.style.visibility = 'visible';
					manageForms( container );
				} else {
					hideCampaign( container );
				}
			} else {
				manageForms( container );
			}
		}
	};
	XHR.send();
};

if ( typeof window !== 'undefined' ) {
	domReady( () => {
		const campaignArray = [
			...document.querySelectorAll( '.newspack-lightbox' ),
			...document.querySelectorAll( '.newspack-inline-popup' ),
		];
		campaignArray.forEach( campaign => {
			maybeShowCampaign( campaign );
		} );
	} );
}
