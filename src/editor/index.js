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
import { PluginDocumentSettingPanel } from '@wordpress/editPost';

class PopupSidebar extends Component {
	/**
	 * Render
	 */
	render() {
		const {
			frequency,
			onMetaFieldChange,
			overlay_opacity,
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
						{ value: 'never', label: __( 'Never' ) },
						{ value: 'once', label: __( 'Once' ) },
						{ value: 'always', label: __( 'Every page view' ) },
						{ value: 'daily', label: __( 'Once a day' ) },
					] }
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
				<RangeControl
					label={ __( 'Overlay opacity' ) }
					value={ overlay_opacity }
					onChange={ value => onMetaFieldChange( 'overlay_opacity', value ) }
					min={ 0 }
					max={ 100 }
				/>
			</Fragment>
		);
	}
}

const PopupSidebarWithData = compose( [
	withSelect( select => {
		const { getEditedPostAttribute } = select( 'core/editor' );
		const meta = getEditedPostAttribute( 'meta' );
		const {
			frequency,
			overlay_opacity,
			placement,
			trigger_scroll_progress,
			trigger_delay,
			trigger_type,
			utm_suppression,
		} = meta || {};
		return {
			frequency,
			overlay_opacity,
			placement,
			trigger_scroll_progress,
			trigger_delay,
			trigger_type,
			utm_suppression,
		};
	} ),
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
