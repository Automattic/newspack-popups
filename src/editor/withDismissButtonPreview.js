/**
 * Higher-order component that appends a preview of the dismiss button to the block editor.
 */

/**
 * WordPress dependencies
 */
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

const withDismissButtonPreview = createHigherOrderComponent( OriginalComponent => {
	return props => {
		const meta = useSelect( select => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) );
		const { dismiss_text, dismiss_text_alignment } = meta;

		// Render a preview of the dismiss button at the end of the block content area.
		useEffect(() => {
			if ( ! dismiss_text ) {
				return;
			}

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

		return <OriginalComponent { ...props } />;
	};
} );

export default withDismissButtonPreview;
