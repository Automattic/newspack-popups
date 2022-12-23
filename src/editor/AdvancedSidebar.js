/**
 * Popup Advanced settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import { BaseControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { CategoryAutocomplete, SelectControl, TextControl } from 'newspack-components';

const AdvancedSidebar = ( {
	onMetaFieldChange,
	excluded_categories = [],
	excluded_tags = [],
	additional_classes = '',
	dismiss_action,
	isOverlay,
} ) => {
	return (
		<Fragment>
			<BaseControl>
				<TextControl
					label={ __( 'Additional CSS class(es)', 'newspack-popups' ) }
					help={ __( 'Separate multiple classes with spaces.', 'newspack-popups' ) }
					value={ additional_classes }
					onChange={ value =>
						onMetaFieldChange( {
							additional_classes: value,
						} )
					}
				/>
				<hr />
				<CategoryAutocomplete
					label={ __( 'Excluded Categories', 'newspack-popups' ) }
					description={ __(
						'The prompt will not be shown on posts that have any these categories.',
						'newspack-popups'
					) }
					value={ excluded_categories }
					onChange={ tokens =>
						onMetaFieldChange( {
							excluded_categories: tokens.map( token => parseInt( token.id ) ),
						} )
					}
				/>
				<hr />
				<CategoryAutocomplete
					label={ __( 'Excluded Tags', 'newspack-popups' ) }
					description={ __(
						'The prompt will not be shown on posts that have any these tags.',
						'newspack-popups'
					) }
					taxonomy="tags"
					value={ excluded_tags }
					onChange={ tokens =>
						onMetaFieldChange( {
							excluded_tags: tokens.map( token => parseInt( token.id ) ),
						} )
					}
				/>
				{ isOverlay && (
					<>
						<hr />
						<SelectControl
							label={ __( 'Dismiss action', 'newspack-popups' ) }
							value={ dismiss_action }
							options={ [
								{ value: 'close_button', label: __( 'Close Button', 'newspack-popups' ) },
								{ value: 'form_submission', label: __( 'Form Submission', 'newspack-popups' ) },
							] }
							onChange={ value => onMetaFieldChange( { dismiss_action: value } ) }
						/>
					</>
				) }
			</BaseControl>
		</Fragment>
	);
};

export default AdvancedSidebar;
