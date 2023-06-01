/* globals newspack_popups_view */

// Ensure we have a newspackRAS array.
window.newspackRAS = window.newspackRAS || [];

// Cached instance of RAS reader data object.
let readerData;

/**
 * Log debugging data if WP_DEBUG is set.
 *
 * @param {string} key  Key name for debug data.
 * @param {any}    data Data to log.
 */
const debug = ( key, data ) => {
	if ( ! newspack_popups_view.debug ) {
		return;
	}

	window.newspack_popups_debug = window.newspack_popups_debug || {};
	window.newspack_popups_debug[ key ] = data;
};

/**
 * Log the current pageview as a visit event in reader activity data.
 */
const logVisit = () => {
	const { visit } = newspack_popups_view;

	if ( ! visit.post_id || ! visit.post_type ) {
		return;
	}

	// If the visit is an article, increment the lifetime view count.
	if ( 'post' === visit.post_type ) {
		const articleViewCount = readerData.store.get( 'articleViewCount' ) || 0;
		readerData.store.set( 'articleViewCount', articleViewCount + 1 );
	}

	// If the visit has categories.
	if ( visit.categories ) {
		visit.categories = visit.categories.split( ',' ).map( id => parseInt( id ) );

		// Add categories to lifetime category view count.
		const categoryViewCounts = readerData.store.get( 'categoryViewCounts' ) || {};
		visit.categories.forEach( categoryId => {
			if ( ! categoryViewCounts[ 'cat' + categoryId ] ) {
				categoryViewCounts[ 'cat' + categoryId ] = 0;
			}
			categoryViewCounts[ 'cat' + categoryId ]++;
		} );
		readerData.store.set( 'categoryViewCounts', categoryViewCounts );
	}

	window.newspackRAS.push( [ 'view', visit ] );
};

/**
 * Get all prompts on the page.
 *
 * @return {Array} Array of prompt elements.
 */
const getPrompts = () => {
	return [ ...document.querySelectorAll( '.newspack-popup' ) ];
};

/**
 * Does the current reader match the given segment?
 *
 * @param {Object} segment Segment configuration object.
 * @return {boolean} True if the reader matches the segment, false if not.
 */
const doesReaderMatchSegment = segment => {
	const { configuration = {} } = segment;
	let matches = true;

	// Article view count.
	const articleViewCount = readerData.store.get( 'articleViewCount' ) || 0;
	if (
		configuration.min_posts &&
		configuration.min_posts > 0 &&
		articleViewCount < configuration.min_posts
	) {
		matches = false;
	}
	if (
		configuration.max_posts &&
		configuration.max_posts > 0 &&
		articleViewCount > configuration.max_posts
	) {
		matches = false;
	}

	// Newsletter signup status.
	const isSubscriber = readerData.store.get( 'isSubscriber' ) || false;
	if ( configuration.is_subscribed && ! isSubscriber ) {
		matches = false;
	}
	if ( configuration.is_not_subscribed && isSubscriber ) {
		matches = false;
	}

	// Donation status.
	const donorStatus = readerData.store.get( 'donorStatus' ) || {};
	if ( configuration.is_donor && ( ! donorStatus.isDonor || ! donorStatus.isDonorRecurring ) ) {
		matches = false;
	}
	if ( configuration.is_not_donor && ( donorStatus.isDonor || donorStatus.isDonorRecurring ) ) {
		matches = false;
	}
	if ( configuration.is_former_donor && ! donorStatus.isDonorFormer ) {
		matches = false;
	}

	// Is currently logged in.
	const reader = readerData.getReader() || {};
	if (
		( configuration.is_logged_in || configuration.has_user_account ) &&
		! reader.authenticated
	) {
		matches = false;
	}
	if (
		( configuration.is_not_logged_in || configuration.no_user_account ) &&
		reader.authenticated
	) {
		matches = false;
	}

	// TODO: By referrer domain (positive and negative).

	// TODO: By most read category.

	return matches;
};

/**
 * Get the reader's highest-priority segment match.
 *
 * @return {string|null} Segment ID, or null.
 */
const getBestPrioritySegment = () => {
	const segments = newspack_popups_view.segments;

	const matchingSegments = segments.filter( segment => doesReaderMatchSegment( segment ) );

	if ( ! matchingSegments.length ) {
		return null;
	}

	matchingSegments.sort( ( a, b ) => a.priority - b.priority );

	return matchingSegments[ 0 ].id || null;
};

/**
 * Check the reader's activity against a given prompt's assigned segments.
 *
 * @param {Array}  assignedSegments Array of segment IDs assigned to the prompt.
 * @param {string} matchingSegment  ID of the reader's highest-priority matching segment.
 * @return {boolean} True if the prompt should be displayed, false if not.
 */
const shouldPromptBeDisplayed = ( assignedSegments, matchingSegment ) => {
	// If no assigned segments, it should be shown to everyone.
	if ( ! assignedSegments ) {
		return true;
	}

	// If the reader matches a segment assigned to the prompt, it should be shown to the reader.
	if ( matchingSegment && assignedSegments.includes( matchingSegment ) ) {
		return true;
	}

	// TODO: By prompt frequency.

	// TODO: By scroll trigger.

	return false;
};

/**
 * Close an overlay when its close button is clicked.
 *
 * @param {Event} event Dispatched click event.
 */
const closeOverlay = event => {
	const parent = event.currentTarget.closest( '.newspack-lightbox' );

	if ( parent && parent.contains( event.currentTarget ) ) {
		parent.setAttribute( 'amp-access-hide', true );
		parent.style.display = 'none';
	}

	event.preventDefault();
};

/**
 * Determine whether each prompt should be displayed according to their segments.
 */
const shouldPromptsBeDisplayed = () => {
	const prompts = getPrompts();
	const matchingSegment = getBestPrioritySegment();
	debug( 'matchingSegment', matchingSegment );

	prompts.forEach( prompt => {
		// Attach event listners to overlay close buttons.
		const closeButton = prompt.querySelector( '.newspack-lightbox__close' );

		if ( closeButton ) {
			closeButton.addEventListener( 'click', closeOverlay );
		}

		// Check segmentation.
		const assignedSegments = prompt.getAttribute( 'data-segments' )
			? prompt.getAttribute( 'data-segments' ).split( ',' )
			: null;
		const shouldDisplay = shouldPromptBeDisplayed( assignedSegments, matchingSegment );

		// Unhide the prompt.
		if ( shouldDisplay ) {
			const delay = prompt.getAttribute( 'data-delay' ) || 0;

			setTimeout( () => {
				prompt.removeAttribute( 'amp-access-hide' );
			}, delay );
		}

		// Debug logging for prompt display.
		const promptId = prompt.getAttribute( 'id' );
		debug( promptId, shouldDisplay );
	} );
};

/**
 * Init segmentation.
 */
export const initSegmentation = () => {
	// Must have segments to check against.
	if ( ! newspack_popups_view?.segments ) {
		return;
	}

	window.newspackRAS.push( ras => {
		readerData = ras;
		logVisit();
		shouldPromptsBeDisplayed();
	} );
};
