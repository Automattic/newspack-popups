/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import './style.scss';
import './patterns.scss';
import { values, performXHRequest, processFormData, getCookieValueFromLinker } from './utils';

const manageAnalytics = () => {
	const ampAnalytics = [ ...document.querySelectorAll( 'amp-analytics' ) ];
	ampAnalytics.forEach( ampAnalyticsElement => {
		const { requests, triggers, linkers, cookies } = JSON.parse(
			ampAnalyticsElement.children[ 0 ].innerText
		);

		// Linker reader – if incoming from AMP Cache, read linker param and set cookie and a linker-less URL.
		// https://github.com/ampproject/amphtml/blob/master/extensions/amp-analytics/linker-id-receiving.md
		const { cookieValue, cleanURL } = getCookieValueFromLinker( { linkers, cookies } );
		if ( cookieValue && cleanURL ) {
			document.cookie = cookieValue;
			window.history.pushState( {}, document.title, cleanURL );
		}

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
	} );
}
