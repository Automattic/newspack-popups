/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import { registerPlugin } from '@wordpress/plugins';
import { FormToggle } from '@wordpress/components';

const PostStatusElement = ( { hasDisabledPopups, onChange } ) => {
	return (
		<PluginPostStatusInfo>
			<label htmlFor="popups-disabled">{ __( 'Disable Popups', 'newspack-popups' ) }</label>
			<FormToggle
				checked={ hasDisabledPopups }
				onChange={ () => onChange( ! hasDisabledPopups ) }
				id="popups-disabled"
			/>
		</PluginPostStatusInfo>
	);
};

const PostStatusElementWithSelect = compose( [
	withSelect( select => {
		const { getEditedPostAttribute } = select( 'core/editor' );
		const meta = getEditedPostAttribute( 'meta' );
		return { hasDisabledPopups: meta.newspack_popups_has_disabled_popups };
	} ),
	withDispatch( dispatch => {
		const { editPost } = dispatch( 'core/editor' );
		return {
			onChange: hasDisabledPopups => {
				editPost( { meta: { newspack_popups_has_disabled_popups: hasDisabledPopups } } );
			},
		};
	} ),
] )( PostStatusElement );

registerPlugin( 'newspack-popups-post-status-info', {
	render: PostStatusElementWithSelect,
} );
