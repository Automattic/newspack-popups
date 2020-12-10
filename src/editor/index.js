/**
 * Popup Custom Post Type
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withSelect, withDispatch } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel, PluginPostStatusInfo } from '@wordpress/edit-post';

/**
 * Internal dependencies
 */
import { optionsFieldsSelector } from './utils';
import Sidebar from './Sidebar';
import StatusSidebar from './StatusSidebar';
import FrequencySidebar from './FrequencySidebar';
import SegmentationSidebar from './SegmentationSidebar';
import DismissSidebar from './DismissSidebar';
import ColorsSidebar from './ColorsSidebar';
import Preview from './Preview';
import withDismissButtonPreview from './withDismissButtonPreview';
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
const StatusSidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( StatusSidebar );

const SidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( Sidebar );

const FrequencySidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( FrequencySidebar );

const SegmentationSidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( SegmentationSidebar );

const DismissSidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( DismissSidebar );

const ColorsSidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( mapDispatchToProps ),
] )( ColorsSidebar );

// Register components.
registerPlugin( 'newspack-popups-status', { render: StatusSidebarWithData } );

registerPlugin( 'newspack-popups', {
	render: () => (
		<PluginDocumentSettingPanel
			name="popup-settings-panel"
			title={ __( 'Campaign Settings', 'newspack-popups' ) }
		>
			<SidebarWithData />
		</PluginDocumentSettingPanel>
	),
	icon: null,
} );

registerPlugin( 'newspack-popups-frequency', {
	render: () => (
		<PluginDocumentSettingPanel
			name="-frequency-panel"
			title={ __( 'Frequency Settings', 'newspack-popups' ) }
		>
			<FrequencySidebarWithData />
		</PluginDocumentSettingPanel>
	),
	icon: null,
} );

registerPlugin( 'newspack-popups-segmentation', {
	render: () => (
		<PluginDocumentSettingPanel
			name="popup-segmentation-panel"
			title={ __( 'Segmentation Settings', 'newspack-popups' ) }
		>
			<SegmentationSidebarWithData />
		</PluginDocumentSettingPanel>
	),
	icon: null,
} );

registerPlugin( 'newspack-popups-dismiss', {
	render: withDismissButtonPreview( () => (
		<PluginDocumentSettingPanel
			name="popup-dismiss-panel"
			title={ __( 'Dismiss Button Settings', 'newspack-popups' ) }
		>
			<DismissSidebarWithData />
		</PluginDocumentSettingPanel>
	) ),
	icon: null,
} );

registerPlugin( 'newspack-popups-colors', {
	render: () => (
		<PluginDocumentSettingPanel
			name="popup-colors-panel"
			title={ __( 'Color Settings', 'newspack-popups' ) }
		>
			<ColorsSidebarWithData />
		</PluginDocumentSettingPanel>
	),
	icon: null,
} );

// Add a button in post status section
const PluginPostStatusInfoTest = () => (
	<PluginPostStatusInfo>
		<Preview />
	</PluginPostStatusInfo>
);
registerPlugin( 'newspack-popups-preview', { render: PluginPostStatusInfoTest } );
