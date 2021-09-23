/**
 * External dependencies
 */
import { without } from 'lodash';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelRow, CheckboxControl } from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';

const ArchivePageTypesSidebar = ( { archive_page_types = [], placement, onMetaFieldChange } ) => {
	const availableArchivePageTypes = [
		{
			name: 'category',
			/* translators: archive page */
			label: __( 'Categories' ),
		},
		{
			name: 'tag',
			/* translators: archive page */
			label: __( 'Tags' ),
		},
		{
			name: 'author',
			/* translators: archive page */
			label: __( 'Authors' ),
		},
		{
			name: 'date',
			/* translators: archive page */
			label: __( 'Date' ),
		},
		{
			name: 'post-type',
			/* translators: archive page */
			label: __( 'Custom Post Types' ),
		},
		{
			name: 'taxonomy',
			/* translators: archive page */
			label: __( 'Taxonomies' ),
		},
	];

	return 'archives' === placement ? (
		<PluginDocumentSettingPanel
			name="post-types-panel"
			title={ __( 'Archive Page Types', 'newspack-popups' ) }
		>
			{ availableArchivePageTypes.map( ( { name, label } ) => (
				<PanelRow key={ name }>
					<CheckboxControl
						label={ label }
						checked={ archive_page_types.indexOf( name ) > -1 }
						onChange={ isIncluded => {
							onMetaFieldChange(
								'archive_page_types',
								isIncluded ? [ ...archive_page_types, name ] : without( archive_page_types, name )
							);
						} }
					/>
				</PanelRow>
			) ) }
		</PluginDocumentSettingPanel>
	) : null;
};

export default ArchivePageTypesSidebar;
