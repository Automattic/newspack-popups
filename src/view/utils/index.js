/* globals gtag, newspackPopupsData */

export * from './prompts';
export * from './segments';

/**
 * External dependencies
 */

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

/**
 * Given a data object and a form HTML element,
 * update the data with values from the form.
 *
 * @param {Object}          data        An object.
 * @param {HTMLFormElement} formElement A form element.
 * @return {Object} Updated data.
 */
export const processFormData = ( data, formElement ) => {
	Object.keys( data ).forEach( key => {
		let value = data[ key ];
		if ( -1 < value.indexOf( '${formFields' ) ) {
			const inputEl = formElement.querySelector( '[name="email"], [type="email"]' );
			if ( inputEl ) {
				value = inputEl.value;
			}
		}
		data[ key ] = substituteDynamicValue( value );
	} );
	return data;
};

/**
 * Replace a dynamic value, like a document referrer, in a string.
 *
 * @param {string} value A string to replace value in.
 * @return {string} String with the value replaced.
 */
export const substituteDynamicValue = value => {
	if ( value ) {
		const trimmedValue = String( value ).replace( /\s/g, '' );
		switch ( trimmedValue ) {
			case 'DOCUMENT_REFERRER':
				value = document.referrer || '';
				break;
		}
	}
	return value;
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

/**
 * If an AMP module was loaded, e.g. via another plugin or a custom header script, it should not be polyfilled.
 */
export const shouldPolyfillAMPModule = name => undefined === customElements.get( `amp-${ name }` );

export const parseOnHandlers = onAttributeValue =>
	onAttributeValue
		.split( ';' )
		.filter( Boolean )
		.map( onHandler => /(?<action>\w*):(?<id>(\w|-)*)\.(?<method>.*)/.exec( onHandler ).groups );

/**
 * Get a GA4 event payload for a given prompt.
 *
 * @param {string} action      Action name for the event.
 * @param {number} promptId    ID of the prompt
 * @param {Object} extraParams Additional key/value pairs to add as params to the event payload.
 *
 * @return {Object} Event payload.
 */
export const getEventPayload = ( action, promptId, extraParams = {} ) => {
	if ( ! newspackPopupsData || ! newspackPopupsData[ promptId ] ) {
		return false;
	}

	return { ...newspackPopupsData[ promptId ], ...extraParams, action };
};

/**
 * Send an event to GA4.
 *
 * @param {Object} payload   Event payload.
 * @param {string} eventName Name of the event. Defaults to `np_prompt_interaction` but can be overriden if necessary.
 */
export const sendEvent = ( payload, eventName = 'np_prompt_interaction' ) => {
	if ( 'function' === typeof gtag && payload ) {
		gtag( 'event', eventName, payload );
	}
};
