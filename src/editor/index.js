/**
 * Popup Custom Post Type
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel, PluginPostStatusInfo } from '@wordpress/edit-post';

/**
 * Internal dependencies
 */
import { optionsFieldsSelector } from './utils';
import PopupSidebar from './PopupSidebar';
import PopupStatusSidebar from './PopupStatusSidebar';
import PopupFrequencySidebar from './PopupFrequencySidebar';
import PopupColorsSidebar from './PopupColorsSidebar';
import PopupPreview from './PopupPreview';
import './style.scss';

// Action dispatchers for the sidebar components.
const mapDispatchToProps = dispatch => {
	const { createNotice, removeNotice } = dispatch( 'core/notices' );
	return {
		onMetaFieldChange: ( key, value ) => {
			dispatch( 'core/editor' ).editPost( { meta: { [ key ]: value } } );
		},
		onSitewideDefaultChange: value => {
			dispatch( 'core/editor' ).editPost( { newspack_popups_is_sitewide_default: value } );
		},
		createNotice,
		removeNotice,
	};
};

// Connect data to components.
const PopupStatusSidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( PopupStatusSidebar );

const PopupSidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( PopupSidebar );

const PopupFrequencySidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( PopupFrequencySidebar );

const PopupColorsSidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( PopupColorsSidebar );

// Register components.
registerPlugin( 'newspack-popups-status', { render: PopupStatusSidebarWithData } );

registerPlugin( 'newspack-popups', {
	render: () => (
		<PluginDocumentSettingPanel
			name="popup-settings-panel"
			title={ __( 'Campaign Settings', 'newspack-popups' ) }
		>
			<PopupSidebarWithData />
		</PluginDocumentSettingPanel>
	),
	icon: null,
} );

registerPlugin( 'newspack-popups-frequency', {
	render: () => (
		<PluginDocumentSettingPanel
			name="popup-frequency-panel"
			title={ __( 'Campaign Frequency Settings', 'newspack-popups' ) }
		>
			<PopupFrequencySidebarWithData />
		</PluginDocumentSettingPanel>
	),
	icon: null,
} );

registerPlugin( 'newspack-popups-colors', {
	render: () => (
		<PluginDocumentSettingPanel
			name="popup-colors-panel"
			title={ __( 'Campaign Color Settings', 'newspack-popups' ) }
		>
			<PopupColorsSidebarWithData />
		</PluginDocumentSettingPanel>
	),
	icon: null,
} );

// Add a button in post status section
const PluginPostStatusInfoTest = () => (
	<PluginPostStatusInfo>
		<PopupPreview />
	</PluginPostStatusInfo>
);
registerPlugin( 'newspack-popups-preview', { render: PluginPostStatusInfoTest } );
