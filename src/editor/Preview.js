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

// Mapping of abbreviated query string params.
const SHORTENED_QUERY_KEYS = {
	background_color: 'bc',
	display_title: 'ti',
	hide_border: 'hb',
	dismiss_text: 'dt',
	dismiss_text_alignment: 'da',
	frequency: 'fr',
	overlay_color: 'oc',
	overlay_opacity: 'oo',
	overlay_size: 'os',
	placement: 'pl',
	trigger_type: 'tt',
	trigger_delay: 'td',
	trigger_scroll_progress: 'ts',
	archive_insertion_posts_count: 'ac',
	archive_insertion_is_repeating: 'ar',
	utm_suppression: 'ut',
};

const PreviewSetting = ( { autosavePost, isSavingPost, postId, metaFields } ) => {
	const abbreviatedMetaFields = {};
	Object.keys( metaFields ).forEach( key => {
		if ( SHORTENED_QUERY_KEYS.hasOwnProperty( key ) ) {
			abbreviatedMetaFields[ SHORTENED_QUERY_KEYS[ key ] ] = metaFields[ key ];
		}
	} );

	const query = stringify( {
		pid: postId,
		// Autosave does not handle meta fields, so these will be passed in the URL
		...abbreviatedMetaFields,
	} );

	const isArchivePagesPrompt = metaFields.placement === 'archives';
	const previewURL =
		window.newspack_popups_data[ isArchivePagesPrompt ? 'preview_archive' : 'preview_post' ] || '/';

	return (
		<WebPreview
			url={ `${ previewURL }?${ query }` }
			renderButton={ ( { showPreview } ) => (
				<Button
					isPrimary
					isBusy={ isSavingPost }
					disabled={ isSavingPost }
					onClick={ () => autosavePost().then( showPreview ) }
				>
					{ __( 'Preview', 'newspack-popups' ) }
				</Button>
			) }
		/>
	);
};

const connectPreviewSetting = compose( [
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
			autosavePost: () => dispatch( 'core/editor' ).autosave(),
		};
	} ),
] );

export default connectPreviewSetting( PreviewSetting );
