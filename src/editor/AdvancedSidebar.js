/**
 * Popup Advanced settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { CategoryAutocomplete } from 'newspack-components';

const AdvancedSidebar = ( { onMetaFieldChange, excluded_categories = [], excluded_tags = [], additional_classes = '' } ) => {
	return (
		<>
			<TextControl
				__next40pxDefaultSize
				label={ __( 'Additional CSS class(es)', 'newspack-popups' ) }
				help={ __( 'Separate multiple classes with spaces.', 'newspack-popups' ) }
				value={ additional_classes }
				onChange={ value =>
					onMetaFieldChange( {
						additional_classes: value,
					} )
				}
			/>
			<CategoryAutocomplete
				__next40pxDefaultSize
				label={ __( 'Excluded Categories', 'newspack-popups' ) }
				description={ __( 'The prompt will not be shown on posts that have any these categories.', 'newspack-popups' ) }
				value={ excluded_categories }
				onChange={ tokens =>
					onMetaFieldChange( {
						excluded_categories: tokens.map( token => parseInt( token.id ) ),
					} )
				}
			/>
			<div style={ { paddingTop: '16px' } } />
			<CategoryAutocomplete
				__next40pxDefaultSize
				label={ __( 'Excluded Tags', 'newspack-popups' ) }
				description={ __( 'The prompt will not be shown on posts that have any these tags.', 'newspack-popups' ) }
				taxonomy="tags"
				value={ excluded_tags }
				onChange={ tokens =>
					onMetaFieldChange( {
						excluded_tags: tokens.map( token => parseInt( token.id ) ),
					} )
				}
			/>
		</>
	);
};

export default AdvancedSidebar;
