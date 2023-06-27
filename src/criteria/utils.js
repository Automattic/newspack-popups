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
}

/**
 * Set the criteria matching attribute.
 *
 * @param {string}          criteriaId        The criteria ID.
 * @param {string|Function} matchingAttribute Either the attribute name to match from the reader data library store or
 *                                            a function that returns the value.
 */
export function setMatchingAttribute( criteriaId, matchingAttribute ) {
	const criteria = newspackPopupsCriteria?.criteria[ criteriaId ];
	if ( ! criteria ) {
		throw new Error( `Criteria ${ criteriaId } not found.` );
	}
	criteria.matchingAttribute = matchingAttribute;
}

/**
 * Set the criteria matching function.
 *
 * @param {string}          criteriaId       The criteria ID.
 * @param {string|Function} matchingFunction Function to use for matching
 */
export function setMatchingFunction( criteriaId, matchingFunction ) {
	const criteria = newspackPopupsCriteria?.criteria[ criteriaId ];
	if ( ! criteria ) {
		throw new Error( `Criteria ${ criteriaId } not found.` );
	}
	criteria.matchingFunction = matchingFunction;
}

/**
 * Get all registered criteria or a specific by ID.
 *
 * @param {string} id The criteria ID.
 */
export function getCriteria( id ) {
	if ( ! newspackPopupsCriteria?.criteria ) {
		return null;
	}
	if ( id ) {
		return newspackPopupsCriteria?.criteria[ id ];
	}
	return newspackPopupsCriteria?.criteria;
}
