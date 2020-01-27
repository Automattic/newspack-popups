/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import { WebPreview } from 'newspack-components';

const PopupPreviewSetting = ( { savePost, isSavingPost, postId } ) => {
	const url = `/?newspack_popups_preview_id=${ postId }`;

	return (
		<WebPreview
			url={ url }
			renderButton={ ( { showPreview } ) => (
				<Button
					isPrimary
					isBusy={ isSavingPost }
					disabled={ isSavingPost }
					style={ {
						marginBottom: '10px',
						// https://github.com/WordPress/gutenberg/pull/19842
						color: 'white',
					} }
					onClick={ () => savePost().then( showPreview ) }
				>
					{ __( 'Preview' ) }
				</Button>
			) }
		/>
	);
};

const connectPopupPreviewSetting = compose( [
	withSelect( select => {
		const { isSavingPost, getCurrentPostId } = select( 'core/editor' );
		return {
			postId: getCurrentPostId(),
			isSavingPost: isSavingPost(),
		};
	} ),
	withDispatch( dispatch => {
		return {
			savePost: () => dispatch( 'core/editor' ).savePost(),
		};
	} ),
] );

export default connectPopupPreviewSetting( PopupPreviewSetting );
