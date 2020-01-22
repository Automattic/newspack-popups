/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

const connectPreviewModal = compose( [
	withSelect( select => {
		const { getCurrentPostId } = select( 'core/editor' );
		return {
			postId: getCurrentPostId(),
		};
	} ),
	withDispatch( dispatch => {
		return {};
	} ),
] );

const PreviewModal = ( { postId, onRequestClose } ) => {
	const url = `/?newspack_popup_preview_id=${ postId }`;
	return postId ? (
		<Fragment>
			<style>
				{ `
					.components-modal__content {
						padding: 0;
					}
					.components-modal__header {
						margin: 0;
					}
				` }
			</style>
			<Modal
				// clicking on content is triggering close
				shouldCloseOnClickOutside={ false }
				title={ __( 'Popup preview' ) }
				onRequestClose={ onRequestClose }
			>
				<iframe src={ url } frameBorder="0" style={ { width: '80vw', height: '80vh' } } />
			</Modal>
		</Fragment>
	) : null;
};

const ConnectedPreviewModal = connectPreviewModal( PreviewModal );

const PopupPreviewSetting = ( {
	content,
	options,
	performAutosave,
	isSavingPost,
	embedPreview,
} ) => {
	const [ showPreview, setShowPreview ] = useState( false );
	const displayPreviewModal = ! isSavingPost && showPreview;

	useEffect(() => {
		if ( showPreview ) {
			performAutosave();
		}
	}, [ showPreview ]);

	return (
		<Fragment>
			<Button
				onClick={ () => setShowPreview( ! showPreview ) }
				isPrimary
				style={ { marginBottom: '17px' } }
			>
				{ __( 'Preview' ) }
			</Button>
			{ displayPreviewModal && (
				<ConnectedPreviewModal
					onRequestClose={ () => setShowPreview( false ) }
					embedPreview={ embedPreview }
				/>
			) }
		</Fragment>
	);
};

const connectPopupPreviewSetting = compose( [
	withSelect( select => {
		const { isSavingPost } = select( 'core/editor' );
		return {
			isSavingPost: isSavingPost(),
		};
	} ),
	withDispatch( dispatch => {
		return {
			performAutosave: () => {
				dispatch( 'core/editor' ).savePost();
			},
		};
	} ),
] );

export default connectPopupPreviewSetting( PopupPreviewSetting );
