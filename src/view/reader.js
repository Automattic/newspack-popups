const NEWSPACK_PREFIX = 'newspack-popups-';

/**
 * Get a template for the reader data object with default values.
 *
 * @return {Object} The default reader object.
 */
const getReaderTemplate = () => {
	return {
		id: 0, // The reader's WP user ID if known, or 0.
		isSubscriber: false, // Whether the reader is known to be a newsletter subscriber.
		isDonor: false, // Whether the reader is known to be a donor, one-time or recurring.
		isDonorRecurring: false, // Whether the reader is known to have at least one active recurring donation.
		isDonorFormer: false, // Whether the reader is known to have had at a recurring donation that has expired or been cancelled.
		articles: 0, // Total number of articles (post_type = 'post') viewed by the reader in their lifetime.
		categories: [], // Array of category IDs most read by the reader.
		events: [], // Array of temporary reader data events from the past 30 days.
	};
};

/**
 * Create a reader data object in localStorage.
 * Defines the model for reader data.
 *
 * @return {Object} Reader data.
 */
const createReader = () => {
	const template = getReaderTemplate();

	localStorage.setItem( NEWSPACK_PREFIX + 'reader', JSON.stringify( template ) );

	return template;
};

/**
 * Get the current reader's data from localStorage.
 * If it doesn't exist, create it.
 *
 * @return {Object} Reader data.
 */
const getReader = () => {
	const cachedReader = localStorage.getItem( NEWSPACK_PREFIX + 'reader' );
	return cachedReader ? JSON.parse( cachedReader ) : createReader();
};

/**
 * Update reader data by key.
 * Validate key and value type before updating.
 *
 * @param {Object} data Reader data to update.
 * @return {boolean} True if updated, false if not.
 */
const updateReader = data => {
	const template = getReaderTemplate();
	const reader = getReader();
	let updated = false;

	for ( const key in data ) {
		if ( template.hasOwnProperty( key ) && typeof template[ key ] === typeof data[ key ] ) {
			if ( Array.isArray( data[ key ] ) ) {
				// Append array data to existing data. Limit items in the array to a max of 1000.
				reader[ key ] = reader[ key ].concat( data[ key ] ).slice( -1000 );
			} else {
				// Otherwise, just update the value.
				reader[ key ] = data[ key ];
			}

			updated = true;
		}
	}

	localStorage.setItem( NEWSPACK_PREFIX + 'reader', JSON.stringify( reader ) );

	return updated;
};

/**
 * Init reader data and expose methods in global scope.
 */
export const initReader = () => {
	window.newspackPopups = {
		reader: getReader(),
		getReader,
		updateReader,
	};
};
