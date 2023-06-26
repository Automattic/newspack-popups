/* globals newspackPopupsCriteria */

/**
 * This is the object that will hold all the registered criteria.
 *
 * @type {Object}
 */
const registeredCriteria = {};
newspackPopupsCriteria.criteria = registeredCriteria;

/**
 * Common matching functions that can be used by criteria.
 */
const matchingFunctions = {
	/**
	 * Matches the exact value of the criteria against the segment config.
	 */
	default: ( criteria, config ) => criteria.value === config.value,
	/**
	 * Matches the criteria value against a list provided by the segment config.
	 */
	list: ( criteria, config ) => {
		let list = config.value;
		if ( typeof list === 'string' ) {
			list = config.value.split( ',' ).map( item => item.trim() );
		}
		if ( ! Array.isArray( list ) ) {
			return false;
		}
		if ( ! criteria.value || ! list.includes( criteria.value ) ) {
			return false;
		}
		return true;
	},
	/**
	 * Matches the criteria value against a range of 'min' and 'max' provided by
	 * the segment config.
	 */
	range: ( criteria, config ) => {
		const { min, max } = config;
		if ( ! criteria.value || ( min && criteria.value < min ) || ( max && criteria.value > max ) ) {
			return false;
		}
		return true;
	},
};

/**
 * Registers a criteria.
 *
 * @param {Object}          config                   The criteria configuration.
 * @param {string}          config.id                ID. (required)
 * @param {string|Function} config.matchingFunction  Function to use for matching. Defaults to 'default'.
 * @param {string|Function} config.matchingAttribute Either the attribute name to match from the reader data library
 *                                                   store or a function that returns the value. Defaults to the ID.
 */
function registerCriteria( config ) {
	if ( ! config.id ) {
		throw new Error( 'Criteria must have an ID.' );
	}
	const criteria = {
		matchingFunction: 'default',
		...config,
	};

	// Setup matching for the criteria.
	const setupMatching = ras => {
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
		setupMatching( ras );
		return criteria.matchingFunction( segmentConfig, ras );
	};
	registeredCriteria[ criteria.id ] = criteria;
}
if ( newspackPopupsCriteria?.config?.length ) {
	newspackPopupsCriteria.config.forEach( registerCriteria );
}
