/* global newspackPopupsCriteria */
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
 * Get all registered criteria or a specific by key.
 *
 * @param {string} key
 */
export function getCriteria( key ) {
	if ( key ) {
		return newspackPopupsCriteria?.criteria[ key ];
	}
	return newspackPopupsCriteria?.criteria;
}
