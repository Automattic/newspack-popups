/**
 * Popup Custom Post Type
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { Component, render, Fragment } from '@wordpress/element';
import {
	Path,
	RangeControl,
	RadioControl,
	SelectControl,
	TextControl,
	SVG,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel, PluginPostStatusInfo } from '@wordpress/edit-post';
import { ColorPaletteControl } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { optionsFieldsSelector } from './utils';
import PopupPreview from './PopupPreview';

class PopupSidebar extends Component {
	/**
	 * Render
	 */
	render() {
		const {
			dismiss_text,
			frequency,
			onMetaFieldChange,
			overlay_opacity,
			overlay_color,
			placement,
			trigger_scroll_progress,
			trigger_delay,
			trigger_type,
			utm_suppression,
		} = this.props;
		return (
			<Fragment>
				<RadioControl
					label={ __( 'Trigger' ) }
					help={ __( 'The event to trigger the popup' ) }
					selected={ trigger_type }
					options={ [
						{ label: __( 'Timer' ), value: 'time' },
						{ label: __( 'Scroll Progress' ), value: 'scroll' },
					] }
					onChange={ value => onMetaFieldChange( 'trigger_type', value ) }
				/>
				{ 'time' === trigger_type && (
					<RangeControl
						label={ __( 'Delay (seconds)' ) }
						value={ trigger_delay }
						onChange={ value => onMetaFieldChange( 'trigger_delay', value ) }
						min={ 0 }
						max={ 60 }
					/>
				) }
				{ 'scroll' === trigger_type && (
					<RangeControl
						label={ __( 'Scroll Progress (percent) ' ) }
						value={ trigger_scroll_progress }
						onChange={ value => onMetaFieldChange( 'trigger_scroll_progress', value ) }
						min={ 1 }
						max={ 100 }
					/>
				) }
				<SelectControl
					label={ __( 'Frequency' ) }
					value={ frequency }
					onChange={ value => onMetaFieldChange( 'frequency', value ) }
					options={ [
						{ value: 'test', label: __( 'Test Mode', 'newspack-popups' ) },
						{ value: 'never', label: __( 'Never', 'newspack-popups' ) },
						{ value: 'once', label: __( 'Once', 'newspack-popups' ) },
						{ value: 'daily', label: __( 'Once a day', 'newspack-popups' ) },
					] }
					help={ __(
						'In "Test Mode" logged-in admins will see the Pop-up every time, and non-admins will never see them.',
						'newspack-popups'
					) }
				/>
				<SelectControl
					label={ __( 'Placement' ) }
					value={ placement }
					onChange={ value => onMetaFieldChange( 'placement', value ) }
					options={ [
						{ value: 'center', label: __( 'Center' ) },
						{ value: 'top', label: __( 'Top' ) },
						{ value: 'bottom', label: __( 'Bottom' ) },
					] }
				/>
				<TextControl
					label={ __( 'UTM Suppression' ) }
					help={ __(
						'Users arriving at the site from URLs with this utm_source will never be shown the pop-up.'
					) }
					value={ utm_suppression }
					onChange={ value => onMetaFieldChange( 'utm_suppression', value ) }
				/>
				<ColorPaletteControl
					value={ overlay_color }
					onChange={ value => onMetaFieldChange( 'overlay_color', value ) }
					label={ __( 'Overlay Color' ) }
				/>
				<RangeControl
					label={ __( 'Overlay opacity' ) }
					value={ overlay_opacity }
					onChange={ value => onMetaFieldChange( 'overlay_opacity', value ) }
					min={ 0 }
					max={ 100 }
				/>
				<TextControl
					label={ __( 'Text for "Not Interested" button' ) }
					value={ dismiss_text }
					onChange={ value => onMetaFieldChange( 'dismiss_text', value ) }
				/>
			</Fragment>
		);
	}
}

const PopupSidebarWithData = compose( [
	withSelect( optionsFieldsSelector ),
	withDispatch( dispatch => {
		return {
			onMetaFieldChange: ( key, value ) => {
				dispatch( 'core/editor' ).editPost( { meta: { [ key ]: value } } );
			},
		};
	} ),
] )( PopupSidebar );

const PluginDocumentSettingPanelDemo = () => (
	<PluginDocumentSettingPanel name="popup-settings-panel" title={ __( ' Pop-up Settings' ) }>
		<PopupSidebarWithData />
	</PluginDocumentSettingPanel>
);
registerPlugin( 'newspack-popups', {
	render: PluginDocumentSettingPanelDemo,
	icon: null,
} );

// Add a button in post status section
const PluginPostStatusInfoTest = () => (
	<PluginPostStatusInfo>
		<PopupPreview />
	</PluginPostStatusInfo>
);
registerPlugin( 'newspack-popups-preview', { render: PluginPostStatusInfoTest } );
