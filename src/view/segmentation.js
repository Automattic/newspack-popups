/* globals newspack_popups_view, newspackPopups */

/**
 * Log the current pageview as a visit event in reader data.
 *
 * @return {boolean} True if logged, false if not.
 */
const logVisit = () => {
	const { visit } = newspack_popups_view;

	if ( ! visit.post_id || ! visit.post_type ) {
		return;
	}

	const event = { ...visit, date_created: new Date() };
	const toUpdate = { events: [ event ] };

	// If the visit is an article, increment the lifetie view count.
	if ( 'post' === visit.post_type ) {
		toUpdate.articles = ( newspackPopups.reader?.articles || 0 ) + 1;
	}

	return newspackPopups.updateReader( toUpdate );
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
	const reader = newspackPopups.reader;
	const { configuration = {} } = segment;
	let matches = true;

	// Article view count.
	if (
		configuration.min_posts &&
		configuration.min_posts > 0 &&
		reader.articles < configuration.min_posts
	) {
		matches = false;
	}
	if (
		configuration.max_posts &&
		configuration.max_posts > 0 &&
		reader.articles > configuration.max_posts
	) {
		matches = false;
	}

	// Newsletter signup status.
	if ( configuration.is_subscribed && ! reader.isSubscriber ) {
		matches = false;
	}
	if ( configuration.is_not_subscribed && reader.isSubscriber ) {
		matches = false;
	}

	// Donation status.
	if ( configuration.is_donor && ( ! reader.isDonor || ! reader.isDonorRecurring ) ) {
		matches = false;
	}
	if ( configuration.is_not_donor && ( reader.isDonor || reader.isDonorRecurring ) ) {
		matches = false;
	}
	if ( configuration.is_former_donor && ! reader.isDonorFormer ) {
		matches = false;
	}

	// User account.
	if ( ( configuration.is_logged_in || configuration.has_user_account ) && ! reader.id ) {
		matches = false;
	}
	if ( ( configuration.is_not_logged_in || configuration.no_user_account ) && reader.id ) {
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
	} );
};

/**
 * Init segmentation.
 */
export const initSegmentation = () => {
	// Must have a reader and segments to check against.
	if ( ! window.newspackPopups?.reader || ! newspack_popups_view?.segments ) {
		return;
	}

	logVisit();
	shouldPromptsBeDisplayed();
};
