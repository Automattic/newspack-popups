/**
 * Options that appear in the "Status & Visibility" sidebar panel.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import { ToggleControl } from '@wordpress/components';

const StatusSidebar = ( {
	newspack_popups_is_sitewide_default,
	isOverlay,
	onSitewideDefaultChange,
} ) => {
	return (
		isOverlay && (
			<PluginPostStatusInfo>
				<div className="newspack-popups__status-options">
					<ToggleControl
						label={ __( 'Sitewide Default', 'newspack-popups' ) }
						help={ __( 'Sitewide default campaigns can appear on any page.', 'newspack-popups' ) }
						checked={ newspack_popups_is_sitewide_default }
						onChange={ value => onSitewideDefaultChange( value ) }
					/>
				</div>
			</PluginPostStatusInfo>
		)
	);
};

export default StatusSidebar;
