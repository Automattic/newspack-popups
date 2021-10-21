/**
 * Popup Advanced settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import { BaseControl } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import AutocompleteTokenField from '../components/autocomplete-tokenfield';

const AdvancedSidebar = ( { onMetaFieldChange, excluded_categories = [] } ) => {
	const getCategoryTitle = category =>
		decodeEntities( category.name ) || __( '(no title)', 'newspack-blocks' );

	const fetchCategorySuggestions = search => {
		return apiFetch( {
			path: addQueryArgs( '/wp/v2/categories', {
				search,
				per_page: 20,
				_fields: 'id,name,parent',
				orderby: 'count',
				order: 'desc',
			} ),
		} ).then( categories =>
			categories.map( category => ( {
				value: category.id,
				label: getCategoryTitle( category ),
			} ) )
		);
	};

	const fetchSavedCategories = categoryIDs => {
		return apiFetch( {
			path: addQueryArgs( '/wp/v2/categories', {
				per_page: 100,
				_fields: 'id,name',
				include: categoryIDs.join( ',' ),
			} ),
		} ).then( categories =>
			categories.map( category => ( {
				value: category.id,
				label: getCategoryTitle( category ),
			} ) )
		);
	};

	return (
		<Fragment>
			<BaseControl className="newspack-popups__segmentation-sidebar">
				<AutocompleteTokenField
					key="categories"
					tokens={ excluded_categories }
					onChange={ _excluded_categories => {
						onMetaFieldChange( 'excluded_categories', _excluded_categories );
					} }
					fetchSuggestions={ fetchCategorySuggestions }
					fetchSavedInfo={ fetchSavedCategories }
					label={ __( 'Excluded Categories', 'newspack-blocks' ) }
				/>
			</BaseControl>
		</Fragment>
	);
};

export default AdvancedSidebar;
