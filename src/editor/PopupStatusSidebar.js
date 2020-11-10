/**
 * Options that appear in the "Status & Visibility" sidebar panel.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import { Component } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';

class PopupStatusSidebar extends Component {
	/**
	 * Render
	 */
	render() {
		const {
			createNotice,
			frequency,
			newspack_popups_is_sitewide_default,
			onMetaFieldChange,
			placement,
			removeNotice,
			onSitewideDefaultChange,
		} = this.props;
		const isTest = 'test' === frequency;
		const isInline = 'inline' === placement;

		return (
			<PluginPostStatusInfo>
				<div className="newspack-popups__status-options">
					<ToggleControl
						checked={ isTest }
						label={ __( 'Test Mode', 'newspack-popups' ) }
						help={ __(
							'In "Test Mode" logged-in admins will see the campaign every time, and non-admins will never see them.',
							'newspack-popups'
						) }
						onChange={ value => {
							if ( value ) {
								createNotice(
									'warning',
									__(
										'Test Mode Enabled: In "Test Mode" logged-in admins will see the campaign every time, and non-admins will never see them.',
										'newspack-popups'
									),
									{
										id: 'newspack-popups__test-mode',
										isDismissible: true,
										type: 'default',
									}
								);
								return onMetaFieldChange( 'frequency', 'test' );
							}
							removeNotice( 'newspack-popups__test-mode' );
							onMetaFieldChange( 'frequency', 'once' );
						} }
					/>
					<ToggleControl
						className={ isInline ? 'newspack-popups__disabled' : '' }
						label={ __( 'Sitewide Default', 'newspack-popups' ) }
						help={
							isInline
								? __( 'Available for overlay campaigns only.', 'newspack-popups' )
								: __( 'Sitewide default campaigns can appear on any page.', 'newspack-popups' )
						}
						checked={ ! isInline && newspack_popups_is_sitewide_default }
						onChange={ value => {
							if ( isInline ) {
								return;
							}

							onSitewideDefaultChange( value );
						} }
					/>
				</div>
			</PluginPostStatusInfo>
		);
	}
}

export default PopupStatusSidebar;
