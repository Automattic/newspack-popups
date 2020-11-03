/**
 * External dependencies
 */
import { createFocusTrap } from 'focus-trap';

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import './style.scss';
import './patterns.scss';

const values = object => Object.keys( object ).map( key => object[ key ] );

const performXHRequest = ( { url, data } ) => {
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

const processFormData = ( data, formElement ) => {
	Object.keys( data ).forEach( key => {
		let value = data[ key ];
		if ( value === '${formFields[email]}' ) {
			const inputEl = formElement.querySelector( '[name="email"]' );
			if ( inputEl ) {
				value = inputEl.value;
			}
		}
		if ( value === 'CLIENT_ID( newspack-cid )' || value === 'CLIENT_ID(newspack-cid)' ) {
			value = getCookies()[ 'newspack-cid' ];
		}
		data[ key ] = value;
	} );
	return data;
};

const manageAnalytics = () => {
	const ampAnalytics = [ ...document.querySelectorAll( 'amp-analytics' ) ];
	ampAnalytics.forEach( ampAnalyticsElement => {
		const { requests, triggers } = JSON.parse( ampAnalyticsElement.children[ 0 ].innerText );
		if ( triggers && requests ) {
			const endpoint = requests.event;
			values( triggers ).forEach( trigger => {
				if ( trigger.on === 'amp-form-submit-success' ) {
					const element = document.querySelector( trigger.selector );
					if ( element ) {
						element.addEventListener( 'submit', () => {
							performXHRequest( {
								url: endpoint,
								data: processFormData( trigger.extraUrlParams, element ),
							} );
						} );
					}
				}
			} );
		}
	} );
};

const manageForms = container => {
	const forms = [ ...container.querySelectorAll( 'form.popup-action-form' ) ];
	forms.forEach( form => {
		form.addEventListener( 'submit', event => {
			const inputs = [ ...form.querySelectorAll( 'input' ) ];
			const data = inputs.reduce( ( acc, input ) => {
				acc[ input.name ] = input.value;
				return acc;
			}, {} );
			performXHRequest( {
				url: form.attributes[ 'action-xhr' ].value,
				data: processFormData( data, form ),
			} );
			event.preventDefault();
		} );
	} );
};

const manageOverlays = () => {
	[ ...document.querySelectorAll( '.newspack-lightbox' ) ].forEach( element => {
		const wrapper = element.querySelector( '.newspack-popup-wrapper' );
		if ( wrapper ) {
			const observer = new MutationObserver( mutationsList => {
				[ ...mutationsList ].forEach( mutation => {
					const isHidden = mutation.target.hasAttribute( 'amp-access-hide' );
					const delayValue = mutation.target.getAttribute( 'data-amp-delay' );
					if ( ! isHidden && delayValue ) {
						// Timeout for the animation.
						setTimeout( () => createFocusTrap( wrapper ).activate(), parseInt( delayValue ) + 500 );
					}
				} );
			} );
			observer.observe( element, { attributes: true, subtree: false } );
		}
	} );
};

if ( typeof window !== 'undefined' ) {
	domReady( () => {
		manageAnalytics();
		const campaignArray = [
			...document.querySelectorAll( '.newspack-lightbox' ),
			...document.querySelectorAll( '.newspack-inline-popup' ),
		];
		campaignArray.forEach( campaign => {
			manageForms( campaign );
		} );
		manageOverlays();
	} );
}
