/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { createHigherOrderComponent } from '@wordpress/compose';

const withDismissButtonPreview = createHigherOrderComponent( BlockListBlock => {
	return props => {
		const { rootClientId } = props;
		const meta = useSelect( select => {
			const { getEditedPostAttribute } = select( 'core/editor' );
			return getEditedPostAttribute( 'meta' );
		} );

		const { dismiss_text, dismiss_text_alignment } = meta;
		const alignClass = 'has-text-align-' + ( dismiss_text_alignment || 'center' );

		return (
			<>
				<BlockListBlock { ...props } />
				{ dismiss_text && ! rootClientId && (
					<div
						className={ `newspack-popups__not-interested-button-preview wp-block ${ alignClass }` }
					>
						{ dismiss_text }
					</div>
				) }
			</>
		);
	};
} );

export default withDismissButtonPreview;
