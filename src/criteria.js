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
	 * The 'default' matching function will match the exact value from the
	 * criteria with the given segment config.
	 */
	default: ( criteria, config ) => criteria.value === config.value,
	/**
	 * The 'list' matching function will match the criteria value against a list
	 * provided by the segment config.
	 *
	 * The list can be a string of comma-separated values or an array and returns
	 * true if the value exists in the list.
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
	 * The 'range' matching function will match the criteria value against a range
	 * between 'min' and 'max' provided by the segment config.
	 */
	range: ( criteria, config ) => {
		const { min, max } = config;
		if (
			! criteria.value ||
			( min && criteria.value <= min ) ||
			( max && criteria.value >= max )
		) {
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
 * Registers the 'Articles Read' criteria.
 */
const articles_read = {
	id: 'articles_read',
	matchingFunction: 'range',
	matchingAttribute: ras => {
		const views = ras.getActivities( 'article_view' );
		return views.filter( view => view.timestamp > Date.now() - 30 * 24 * 60 * 60 * 1000 );
	},
};
registerCriteria( articles_read );

const articles_read_in_session = {
	id: 'articles_read_in_session',
	matchingFunction: 'range',
	matchingAttribute: ras => {
		const views = ras.getActivities( 'article_view' );
		return views.filter( view => view.timestamp > Date.now() - 45 * 60 * 1000 );
	},
};
registerCriteria( articles_read_in_session );

/**
 * Registers the 'Newsletter' criteria.
 *
 * This criteria's matching attribute will likely be set by another logic.
 */
const newsletter = {
	id: 'newsletter',
	matchingFunction: ( config, store ) => {
		if ( store.get( 'is_subscriber' ) ) {
			return config.value === 1;
		}
		return config.value === 2;
	},
};
registerCriteria( newsletter );

/**
 * Registers the 'Sources to match' criteria.
 */
const referrer_sources = {
	id: 'referrer_sources',
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
 * Registers the 'Favorite Categories' criteria.
 *
 * The UI should be tweaked in the editor UI to render a category selector and
 * can be achieved by using @wordpress/hooks/applyFilters while iterating
 * through the criteria to render.
 */
const favorite_categories = {
	id: 'favorite_categories',
	matchingFunction: 'list',
	matchingAttribute: 'favorite_categories',
};
registerCriteria( favorite_categories );

/**
 * Sample segments to match.
 */
const segments = [];

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
	 * Whether the reader matches the segment criteria.
	 */
	function matchSegment( segment ) {
		for ( const criteriaId in segment.criteria ) {
			const criteria = registeredCriteria[ criteriaId ];
			if ( ! criteria ) {
				continue;
			}
			const config = segment.criteria[ criteriaId ];
			if ( ! criteria.matchingFunction( config, store ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Match each segment.
	 */
	for ( const segment of segments ) {
		console.log( { segmentId: segment.id, matched: matchSegment( segment ) } ); // eslint-disable-line no-console
	}
} );
