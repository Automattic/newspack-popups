/**
 * This file is a proof-of-concept of how the criteria registration and matching
 * system could work.
 */

/**
 * This is the object that will hold all the registered criteria.
 *
 * @type {Object}
 */
const registeredCriteria = {};

/**
 * Common matching functions that can be used by criteria.
 */
const matchingFunctions = {
	/**
	 * The 'default' matching function will match the exact value of the attribute
	 * from the given segment config.
	 */
	default: ( criteria, config ) => criteria.value === config.value,
	/**
	 * The 'list' matching function will match the value of the attribute against
	 * a list of values from the given segment config.
	 *
	 * The list can be a string of comma-separated values or an array and returns
	 * true if the value is in the list.
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
	 * The 'range' matching function will match the value of the attribute against
	 * a range of values from the given segment config.
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
 * @param {string}          config.name              Name. Defaults to the ID.
 * @param {string}          config.help              Help text.
 * @param {string}          config.description       Description.
 * @param {string}          config.category          Category. Defaults to 'reader_activity'.
 * @param {string|Function} config.matchingFunction  Function to use for matching. Defaults to 'default'.
 * @param {string|Function} config.matchingAttribute Either the attribute name to match from the reader data library
 *                                                   store or a function that returns the value. Defaults to the ID.
 * @param {Array}           config.options           The options for criteria that will be rendered in the segment UI.
 * @param {number}          config.options[].value   Option value.
 * @param {string}          config.options[].label   Option label.
 */
function registerCriteria( config ) {
	if ( ! config.id ) {
		throw new Error( 'Criteria must have an ID.' );
	}
	config = {
		category: 'reader_activity',
		matchingFunction: 'default',
		...config,
	};
	if ( ! config.name ) {
		config.name = config.id;
	}
	if ( ! config.matchingAttribute ) {
		config.matchingAttribute = config.id;
	}
	if (
		typeof config.matchingFunction === 'string' &&
		matchingFunctions[ config.matchingFunction ]
	) {
		config.matchingFunction = matchingFunctions[ config.matchingFunction ].bind( null, config );
	}
	if ( typeof config.matchingFunction !== 'function' ) {
		throw new Error( 'Criteria must have a matching function.' );
	}
	registeredCriteria[ config.id ] = config;
}

/**
 * Registering 'Articles Read' criteria.
 *
 * The initialization function for this criteria will set the matching attribute
 * based on the number of article views in the set time period. The views are
 * set as reader activity through ras.dispatchActivity(), which also belong in
 * the reader data library store.
 */
const articles_read = {
	id: 'articles_read',
	name: 'Articles read',
	help: 'Number of articles read in the last 30 day period.',
	category: 'reader_engagement',
	matchingFunction: 'range',
	matchingAttribute: ras => {
		const views = ras.getActivities( 'article_view' );
		return views.filter( view => view.timestamp > Date.now() - 30 * 24 * 60 * 60 * 1000 );
	},
};
registerCriteria( articles_read );

const articles_read_in_session = {
	id: 'articles_read_in_session',
	name: 'Articles read in session',
	help: 'Number of articles read in the current session (45 minutes).',
	category: 'reader_engagement',
	matchingFunction: 'range',
	matchingAttribute: ras => {
		const views = ras.getActivities( 'article_view' );
		return views.filter( view => view.timestamp > Date.now() - 45 * 60 * 1000 );
	},
};
registerCriteria( articles_read_in_session );

/**
 * Registering a criteria that will use a custom matching function and 'options'
 * to render in the segment UI.
 *
 * This criteria may not need an initialization function and have its matching
 * attribute set by another logic, likely through the backend.
 *
 * Check the \Newspack\Reader_Data class from newspack-plugin for more details.
 */
const newsletter = {
	id: 'newsletter',
	name: 'Newsletter',
	category: 'reader_activity',
	options: [
		{
			label: 'Subscribers and non-subscribers',
			value: 0,
		},
		{
			label: 'Subscribers',
			value: 1,
		},
		{
			label: 'Non-Subscribers',
			value: 2,
		},
	],
	matchingFunction: ( config, store ) => {
		if ( store.get( 'is_subscriber' ) ) {
			return config.value === 1;
		}
		return config.value === 2;
	},
};
registerCriteria( newsletter );

/**
 * Registering the 'Sources to match' criteria.
 *
 * This criteria will use the comma-separated list matching function.
 */
const referrer_sources = {
	id: 'referrer_sources',
	name: 'Sources to match',
	help: 'Segment based on traffic source',
	description: 'A comma-separated list of domains.',
	category: 'referrer_sources',
	matchingFunction: 'list',
	matchingAttribute: ( { store } ) => {
		const value = document.referrer
			? ( new URL( document?.referrer ).hostname.replace( 'www.', '' ) || '' ).toLowerCase()
			: '';
		// Persist the referrer in the store.
		if ( value ) {
			store.set( 'referrer', value );
		}
		return store.get( 'referrer' );
	},
};
registerCriteria( referrer_sources );

/**
 * Registering 'Favorite Categories' criteria.
 *
 * This criteria will use the 'list' type and matching function, but the UI
 * should be tweaked in the editor UI to render a category selector.
 *
 * This selector UI shouldn't be here due to the number of components it needs,
 * which cannot be in the frontend.
 */
const favorite_categories = {
	id: 'favorite_categories',
	name: 'Favorite categories',
	help: 'Most-read categories of the reader',
	category: 'reader_engagement',
	matchingFunction: 'list',
	matchingAttribute: 'favorite_categories',
};
registerCriteria( favorite_categories );

/**
 * Sample segment configuration to test against the registered criteria.
 */
const sampleSegment = {
	criteria: {
		articles_read: {
			min: 1,
			max: 10,
		},
		articles_read_in_session: {
			min: 1,
			max: 10,
		},
		newsletter: {
			value: 1,
		},
		referrer_sources: {
			value: 'google.com,facebook.com',
		},
	},
};

window.newspackRAS = window.newspackRAS || [];
window.newspackRAS.push( ras => {
	const { store } = ras;

	/**
	 * Get the criteria value for each registered criteria.
	 */
	for ( const id in registeredCriteria ) {
		const criteria = registeredCriteria[ id ];
		if ( typeof criteria.matchingAttribute === 'function' ) {
			criteria.value = criteria.matchingAttribute( ras );
		} else if ( typeof criteria.matchingAttribute === 'string' ) {
			criteria.value = store.get( criteria.matchingAttribute );
		}
	}

	/**
	 * Execute matching logic for each criteria in the sample segment.
	 */
	for ( const criteriaId in sampleSegment.criteria ) {
		const criteria = registeredCriteria[ criteriaId ];
		// Bail if criteria is not registered.
		if ( ! criteria ) {
			continue;
		}
		const config = sampleSegment.criteria[ criteriaId ];
		// Bail if there's no value to match against.
		if ( ! config.value && ! config.min && ! config.max ) {
			continue;
		}
		const matched = criteria.matchingFunction( config, store );
		console.log( { criteriaId, matched } ); // eslint-disable-line no-console
	}
} );
