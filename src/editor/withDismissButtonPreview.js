/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { createHigherOrderComponent } from '@wordpress/compose';

const withDismissButtonPreview = createHigherOrderComponent( BlockListBlock => {
	return props => {
		const { index, rootClientId } = props;
		const meta = useSelect( select => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) );
		const blockCount = useSelect( select => select( 'core/block-editor' ).getBlockCount() );
		const { dismiss_text, dismiss_text_alignment } = meta;
		const alignClass = 'has-text-align-' + ( dismiss_text_alignment || 'center' );
		const isLastBlock = index === blockCount - 1;

		return (
			<>
				<BlockListBlock { ...props } />
				{ dismiss_text && ! rootClientId && isLastBlock && (
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
