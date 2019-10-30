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
	PanelBody,
	Path,
	RangeControl,
	RadioControl,
	SelectControl,
	SVG,
	Toolbar,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editPost';

const icon = (
	<SVG xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
		<Path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z" />
	</SVG>
);

class PopupSidebar extends Component {
	/**
	 * Render
	 */
	render() {
		const {
			frequency,
			placement,
			onMetaFieldChange,
			trigger_scroll_progress,
			trigger_delay,
			trigger_type,
		} = this.props;
		return (
			<Fragment>
				<PluginSidebarMoreMenuItem target="sidebar-name">
					{ __( 'Popup Settings' ) }
				</PluginSidebarMoreMenuItem>
				<PluginSidebar name="sidebar-name" title={ __( 'Popup Settings' ) }>
					<PanelBody title={ __( 'Popup Triggers' ) } initialOpen={ true }>
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
								{ value: 0, label: __( 'Once per user' ) },
								{ value: 5, label: __( 'Every 5 page views' ) },
								{ value: 25, label: __( 'Every 25 page views' ) },
								{ value: 100, label: __( 'Every 100 page views' ) },
							] }
						/>
						<Toolbar
							isCollapsed={ false }
							controls={ [
								{
									icon: 'align-center',
									title: __( 'Center' ),
									isActive: [ 'bottom', 'top', 'left', 'right' ].indexOf( placement ) === -1,
									onClick: () => onMetaFieldChange( 'placement', 'center' ),
								},
								{
									icon: 'arrow-down-alt',
									title: __( 'Bottom' ),
									isActive: 'bottom' === placement,
									onClick: () => onMetaFieldChange( 'placement', 'bottom' ),
								},
								{
									icon: 'arrow-up-alt',
									title: __( 'Top' ),
									isActive: 'top' === placement,
									onClick: () => onMetaFieldChange( 'placement', 'top' ),
								},
								{
									icon: 'align-left',
									title: __( 'Left' ),
									isActive: 'left' === placement,
									onClick: () => onMetaFieldChange( 'placement', 'left' ),
								},
								{
									icon: 'align-right',
									title: __( 'Right' ),
									isActive: 'right' === placement,
									onClick: () => onMetaFieldChange( 'placement', 'right' ),
								},
							] }
						/>
					</PanelBody>
				</PluginSidebar>
			</Fragment>
		);
	}
}

const popupSidebar = compose( [
	withSelect( select => {
		const { getEditedPostAttribute } = select( 'core/editor' );
		const meta = getEditedPostAttribute( 'meta' );
		const { frequency, placement, trigger_scroll_progress, trigger_delay, trigger_type } =
			meta || {};
		return {
			frequency,
			placement,
			trigger_scroll_progress,
			trigger_delay,
			trigger_type,
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

registerPlugin( 'newspack-popups', {
	icon,
	render: popupSidebar,
} );
