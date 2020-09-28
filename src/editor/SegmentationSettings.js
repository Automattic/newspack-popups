/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { RangeControl } from '@wordpress/components';

const SegmentationSettings = ( { min_posts_read, onMetaFieldChange } ) => {
	return (
		<RangeControl
			label={ __( 'Min. no. of posts read until displayed' ) }
			value={ min_posts_read }
			onChange={ value => onMetaFieldChange( 'min_posts_read', value ) }
			min={ 0 }
			max={ 10 }
		/>
	);
};

const SegmentationSettingsWithData = compose( [
	withSelect( select => {
		const { getEditedPostAttribute } = select( 'core/editor' );
		const meta = getEditedPostAttribute( 'meta' );
		return {
			min_posts_read: meta.min_posts_read || 0,
		};
	} ),
	withDispatch( dispatch => {
		return {
			onMetaFieldChange: ( key, value ) => {
				dispatch( 'core/editor' ).editPost( { meta: { [ key ]: value } } );
			},
		};
	} ),
] )( SegmentationSettings );

registerPlugin( 'newspack-popups-segmentation-settings', {
	render: () => (
		<PluginDocumentSettingPanel
			name="popup-segmentation-settings-panel"
			title={ __( 'Campaign Segmentation', 'newspack-popups' ) }
		>
			<SegmentationSettingsWithData />
		</PluginDocumentSettingPanel>
	),
	icon: null,
} );
