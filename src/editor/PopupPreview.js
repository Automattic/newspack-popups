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
import { stringify } from 'qs';
import { WebPreview } from 'newspack-components';

const PopupPreviewSetting = ( { savePost, isSavingPost, postId, metaFields } ) => {
	const query = stringify( {
		newspack_popups_preview_id: postId,
		// Autosave does not handle meta fields, so these will be passed in the URL
		...metaFields,
	} );

	return (
		<WebPreview
			url={ `/?${ query }` }
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
		const { isSavingPost, getCurrentPostId, getEditedPostAttribute } = select( 'core/editor' );
		return {
			postId: getCurrentPostId(),
			metaFields: getEditedPostAttribute( 'meta' ),
			isSavingPost: isSavingPost(),
		};
	} ),
	withDispatch( dispatch => {
		return {
			savePost: () => dispatch( 'core/editor' ).autosave(),
		};
	} ),
] );

export default connectPopupPreviewSetting( PopupPreviewSetting );
