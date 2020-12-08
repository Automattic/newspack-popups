/**
 * Dismiss button settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useEffect } from '@wordpress/element';
import { SelectControl, TextControl } from '@wordpress/components';

const DismissSidebar = ( { dismiss_text, dismiss_text_alignment, onMetaFieldChange } ) => {
	// Render a preview of the dismiss button at the end of the block content area.
	useEffect(() => {
		const alignClass = 'has-text-align-' + ( dismiss_text_alignment || 'center' );

		let dismissButtonPreview = document.querySelector(
			'.newspack-popups__not-interested-button-preview'
		);

		if ( ! dismissButtonPreview ) {
			const rootContainer = document.querySelector(
				'.block-editor-block-list__layout.is-root-container'
			);

			if ( rootContainer ) {
				dismissButtonPreview = document.createElement( 'div' );
				rootContainer.appendChild( dismissButtonPreview );
			}
		}

		if ( dismissButtonPreview ) {
			dismissButtonPreview.className =
				'newspack-popups__not-interested-button-preview wp-block ' + alignClass;
			dismissButtonPreview.textContent = dismiss_text;
		}
	}, [ dismiss_text, dismiss_text_alignment ]);

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
