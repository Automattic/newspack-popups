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
 * type and matchingAttribute.
 */
const articles_read = {
	id: 'articles_read',
	name: 'Articles read',
	help: 'Number of articles read in the last 30 day period.',
	category: 'reader_engagement',
	type: 'range',
	matchingAttribute: 'articles_read_30_days',
};
registerCriteria( articles_read );

const articles_read_in_session = {
	id: 'articles_read_in_session',
	name: 'Articles read in session',
	help: 'Number of articles read in the current session (45 minutes).',
	category: 'reader_engagement',
	type: 'range',
	matchingAttribute: 'articles_read_in_session',
};
registerCriteria( articles_read_in_session );

/**
 * Registering a criteria that will use dropdown and a custom matching function.
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
		const isSubscriber = store.get( 'is_subscriber' );
		if ( ! isSubscriber ) {
			return config.value === 2;
		}
		return config.value === 1;
	},
};
registerCriteria( newsletter );

/**
 * Registering a criteria that will use the comma-separated list matching function.
 */
const referrer_sources = {
	id: 'referrer_sources',
	name: 'Sources to match',
	help: 'Segment based on traffic source',
	description: 'A comma-separated list of domains.',
	category: 'referrer_sources',
	type: 'list',
	matchingAttribute: 'referrer',
};
registerCriteria( referrer_sources );

/**
 * Sample segment configuration to test agains the registered criteria.
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

/**
 * Run the sample segment against the registered criteria.
 */
window.newspackRAS = window.newspackRAS || [];
window.newspackRAS.push( ras => {
	const { store } = ras;
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
