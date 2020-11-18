/**
 * Options that appear in the "Status & Visibility" sidebar panel.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import { useEffect } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';

const StatusSidebar = ( {
	createNotice,
	frequency,
	newspack_popups_is_sitewide_default,
	onMetaFieldChange,
	placement,
	removeNotice,
	onSitewideDefaultChange,
} ) => {
	const isTest = 'test' === frequency;
	const isInline = 'inline' === placement;

	const createTestNotice = () => {
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
	};

	useEffect(() => {
		if ( isTest ) {
			createTestNotice();
		} else {
			removeNotice( 'newspack-popups__test-mode' );
		}
	}, [ isTest ]);

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
							return onMetaFieldChange( 'frequency', 'test' );
						}
						onMetaFieldChange( 'frequency', 'once' );
					} }
				/>
				{ ! isInline && (
					<ToggleControl
						label={ __( 'Sitewide Default', 'newspack-popups' ) }
						help={ __( 'Sitewide default campaigns can appear on any page.', 'newspack-popups' ) }
						checked={ ! isInline && newspack_popups_is_sitewide_default }
						onChange={ value => onSitewideDefaultChange( value ) }
					/>
				) }
			</div>
		</PluginPostStatusInfo>
	);
};

export default StatusSidebar;
