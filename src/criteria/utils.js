/* globals newspackPopupsCriteria */

import matchingFunctions from './matching-functions';

/**
 * Registers a criteria.
 *
 * @param {string}          id                       The criteria ID. (required)
 * @param {Object}          config                   Criteria matching configuration.
 * @param {string|Function} config.matchingFunction  Function to use for matching. Defaults to 'default'.
 * @param {string|Function} config.matchingAttribute Either the attribute name to match from the reader data library
 *                                                   store or a function that returns the value. Defaults to the ID.
 *
 * @return {Object} The criteria object.
 *
 * @throws {Error} If the criteria ID is not provided.
 */
export function registerCriteria( id, config = {} ) {
	if ( ! id ) {
		throw new Error( 'Criteria must have an ID.' );
	}
	const criteria = {
		id,
		matchingFunction: 'default',
		...config,
	};

	/**
	 * Setup matching requirements for the criteria.
	 */
	const setup = ras => {
		// Run setup only once.
		if ( criteria._configured ) {
			return;
		}
		criteria._configured = true;

		// Default attribute to the criteria ID.
		if ( ! criteria.matchingAttribute ) {
			criteria.matchingAttribute = criteria.id;
		}

		// Configure matching function.
		if (
			typeof criteria.matchingFunction === 'string' &&
			matchingFunctions[ criteria.matchingFunction ]
		) {
			criteria.matchingFunction = matchingFunctions[ criteria.matchingFunction ].bind(
				null,
				criteria
			);
		}

		// Bail if unable to configure matching function.
		if ( typeof criteria.matchingFunction !== 'function' ) {
			console.warn( `Unable to configure matching function for criteria ${ criteria.id }.` ); // eslint-disable-line no-console
			return;
		}

		// Set criteria value.
		if ( typeof criteria.matchingAttribute === 'function' ) {
			criteria.value = criteria.matchingAttribute( ras );
		} else if ( typeof criteria.matchingAttribute === 'string' ) {
			criteria.value = ras?.store?.get( criteria.matchingAttribute ) || null;
		}
	};

	// Check if the criteria matches the segment config.
	criteria.matches = segmentConfig => {
		const ras = window.newspackReaderActivation;
		if ( ! ras ) {
			console.warn( 'Reader activation script not loaded.' ); // eslint-disable-line no-console
		}
		setup( ras );
		return criteria.matchingFunction( segmentConfig, ras );
	};
	if ( ! newspackPopupsCriteria.criteria ) {
		newspackPopupsCriteria.criteria = {};
	}
	newspackPopupsCriteria.criteria[ id ] = criteria;

	return criteria;
}

/**
 * Get all registered criteria or a specific by ID.
 *
 * @param {string} id The criteria ID.
 *
 * @return {Object|undefined} The criteria object or an object of all criteria.
 *                            undefined if the criteria ID is not found.
 */
export function getCriteria( id ) {
	if ( id ) {
		return newspackPopupsCriteria.criteria[ id ];
	}
	return newspackPopupsCriteria.criteria;
}

/**
 * Set the criteria matching attribute.
 *
 * @param {string}          id                The criteria ID.
 * @param {string|Function} matchingAttribute Either the attribute name to match from the reader data library store or
 *                                            a function that returns the value.
 *
 * @throws {Error} If the criteria ID is not found.
 */
export function setMatchingAttribute( id, matchingAttribute ) {
	const criteria = getCriteria( id );
	if ( ! criteria ) {
		throw new Error( `Criteria ${ id } not found.` );
	}
	criteria.matchingAttribute = matchingAttribute;
}

/**
 * Set the criteria matching function.
 *
 * @param {string}          id               The criteria ID.
 * @param {string|Function} matchingFunction Function to use for matching
 *
 * @throws {Error} If the criteria ID is not found.
 */
export function setMatchingFunction( id, matchingFunction ) {
	const criteria = getCriteria( id );
	if ( ! criteria ) {
		throw new Error( `Criteria ${ id } not found.` );
	}
	criteria.matchingFunction = matchingFunction;
}
