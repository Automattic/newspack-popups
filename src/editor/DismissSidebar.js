/**
 * Dismiss button settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { Fragment } from '@wordpress/element';
import { SelectControl, TextControl } from '@wordpress/components';

const DismissSidebar = ( { dismiss_text, dismiss_text_alignment, onMetaFieldChange } ) => {
	return (
		<Fragment>
			<TextControl
				label={ __( 'Label', 'newspack-popups' ) }
				help={ __(
					'When clicked, this button will permanently dismiss the campaign for the current reader.',
					'newspack-popups'
				) }
				value={ dismiss_text }
				onChange={ value => onMetaFieldChange( 'dismiss_text', value ) }
			/>
			<SelectControl
				label={ __( 'Alignment', 'newspack-listings' ) }
				id="newspack-popups-dimiss-button-alignment"
				onChange={ value => onMetaFieldChange( 'dismiss_text_alignment', value ) }
				value={ dismiss_text_alignment }
				options={ [
					{
						label: __( 'Center', 'newspack-popups' ),
						value: '',
					},
					{
						label: __( 'Left', 'newspack-popups' ),
						value: 'left',
					},
					{
						label: __( 'Right', 'newspack-popups' ),
						value: 'right',
					},
				] }
			/>
		</Fragment>
	);
};

export default DismissSidebar;
