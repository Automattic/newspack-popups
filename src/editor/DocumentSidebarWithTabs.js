/**
 * Single document panel with Settings and Styles tabs.
 * Panels with group="styles" render under the Styles tab; others under Settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { cog, styles } from '@wordpress/icons';
import { TabPanel, PanelBody } from '@wordpress/components';

const NEWSPACK_POPUPS_TABS_CLASS = 'newspack-popups__tabs';

const DocumentSidebarWithTabs = ( { panels } ) => {
	const settingsPanels = panels.filter( p => p.group !== 'styles' );
	const stylesPanels = panels.filter( p => p.group === 'styles' );

	return (
		<TabPanel
			className={ NEWSPACK_POPUPS_TABS_CLASS }
			tabs={ [
				{ name: 'settings', title: __( 'Settings', 'newspack-popups' ), icon: cog, className: 'newspack-popups__tab-settings' },
				{ name: 'styles', title: __( 'Styles', 'newspack-popups' ), icon: styles, className: 'newspack-popups__tab-styles' },
			] }
		>
			{ tab => {
				const items = tab.name === 'settings' ? settingsPanels : stylesPanels;
				return (
					<div className={ `${ NEWSPACK_POPUPS_TABS_CLASS }__tab-content` }>
						{ items.map( ( { name, title, Component, initialOpen = true } ) => (
							<PanelBody key={ name } title={ title } initialOpen={ initialOpen }>
								<Component />
							</PanelBody>
						) ) }
					</div>
				);
			} }
		</TabPanel>
	);
};

export default DocumentSidebarWithTabs;
