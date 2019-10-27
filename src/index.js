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
	RangeControl,
	RadioControl,
	SVG,
	Path,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editPost';
import './style.scss';

const icon = (
	<SVG width="24px" height="24px" viewBox="0 0 32 32">
		<Path
			d="M16 32c8.836 0 16-7.164 16-16S24.836 0 16 0 0 7.164 0 16s7.163 16 16 16z"
			fill="#36F!important"
		/>
		<Path
			d="M22.988 16.622h-1.72l-1.103-1.124h2.823v1.124zm0-3.31H18.02l-1.102-1.124h6.071v1.124zm0-3.31h-8.217l-1.103-1.125h9.32v1.125zm0 13.12L9.012 8.878v4.749l.069.071h-.07v9.426h3.451v-5.98l5.867 5.98h4.66z"
			fill="#fff"
			className="newspack-popups-icon__background"
		/>
	</SVG>
);

class PopupSidebar extends Component {
	/**
	 * Render
	 */
	render() {
		const { onMetaFieldChange, trigger_scroll_progress, trigger_delay, trigger_type } = this.props;
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
		const { trigger_scroll_progress, trigger_delay, trigger_type } = meta || {};
		return {
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
