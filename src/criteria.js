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
	range: ( criteria, store, config ) => {
		const value = store.get( criteria.matchingAttribute );
		const { min, max } = config;
		if ( ! value || ( min && value < min ) || ( max && value > max ) ) {
			return false;
		}
		return true;
	},
	dropdown: ( criteria, store, config ) => {
		const value = store.get( criteria.matchingAttribute );
		if ( ! value || value !== config.value ) {
			return false;
		}
		return true;
	},
	list: ( criteria, store, config ) => {
		const list = config.value.split( ',' ).map( item => item.trim() );
		const value = store.get( criteria.matchingAttribute );
		if ( ! value || ! list.includes( value ) ) {
			return false;
		}
		return true;
	},
};

/**
 * Registers a criteria.
 *
 * @param {Object}   config                   The criteria configuration.
 * @param {string}   config.id                ID.
 * @param {string}   config.name              Name.
 * @param {string}   config.help              Help text.
 * @param {string}   config.description       Description.
 * @param {string}   config.category          Category.
 * @param {string}   config.type              Type. One of 'range', 'dropdown' or 'list'.
 * @param {Function} config.init              Criteria initialization function.
 * @param {string}   config.matchingAttribute The attribute to match against from the reader data library store.
 * @param {Function} config.matchingFunction  A custom function to use for matching.
 * @param {Array}    config.options           The options for criteria of type 'dropdown'.
 * @param {number}   config.options[].value   Option value.
 * @param {string}   config.options[].label   Option label.
 */
function registerCriteria( config ) {
	if ( ! config.matchingFunction && matchingFunctions[ config.type ] && config.matchingAttribute ) {
		config.matchingFunction = matchingFunctions[ config.type ].bind( null, config );
	}
	registeredCriteria[ config.id ] = config;
}

/**
 * Registering a criteria that will use the default matching function based on
 * type and matching attribute.
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
	type: 'range',
	matchingAttribute: 'articles_read_30_days',
	init: ( store, ras ) => {
		const views = ras.getActivities( 'article_view' );
		views.filter( view => view.timestamp > Date.now() - 30 * 24 * 60 * 60 * 1000 );
		store.set( 'articles_read_30_days', views.length );
	},
};
registerCriteria( articles_read );

const articles_read_in_session = {
	id: 'articles_read_in_session',
	name: 'Articles read in session',
	help: 'Number of articles read in the current session (45 minutes).',
	category: 'reader_engagement',
	type: 'range',
	matchingAttribute: 'articles_read_in_session',
	init: ( store, ras ) => {
		const views = ras.getActivities( 'article_view' );
		views.filter( view => view.timestamp > Date.now() - 45 * 60 * 1000 );
		store.set( 'articles_read_in_session', views.length );
	},
};
registerCriteria( articles_read_in_session );

/**
 * Registering a criteria that will use dropdown and a custom matching function.
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
	type: 'dropdown',
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
	matchingFunction: ( store, config ) => {
		if ( store.get( 'is_subscriber' ) ) {
			return config.value === 1;
		}
		return config.value === 2;
	},
};
registerCriteria( newsletter );

/**
 * Registering a criteria that will use the comma-separated list matching
 * function.
 */
const referrer_sources = {
	id: 'referrer_sources',
	name: 'Sources to match',
	help: 'Segment based on traffic source',
	description: 'A comma-separated list of domains.',
	category: 'referrer_sources',
	type: 'list',
	matchingAttribute: 'referrer',
	init: store =>
		store.set(
			'referrer',
			( new URL( document.referrer ).hostname.replace( 'www.', '' ) || '' ).toLowerCase()
		),
};
registerCriteria( referrer_sources );

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
	 * Initialize criteria before executing matching logic so that the
	 * matchingAttribute can be set.
	 *
	 * This may not be the case for all criteria, but the `init()` function is an
	 * appropriate place to handle the matchingAttribute in the front-end.
	 */
	for ( const id in registeredCriteria ) {
		const criteria = registeredCriteria[ id ];
		if ( criteria.init && typeof criteria.init === 'function' ) {
			criteria.init( store, ras );
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
		const matched = criteria.matchingFunction( store, config );
		console.log( { criteriaId, matched } ); // eslint-disable-line no-console
	}
} );
