/**
 * Popup frequency options.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import { ExternalLink, SelectControl } from '@wordpress/components';

const segmentsList =
	( window && window.newspack_popups_data && window.newspack_popups_data.segments ) || [];

const SegmentationSidebar = ( { onMetaFieldChange, selected_segment_id } ) => {
	return (
		<Fragment>
			<SelectControl
				label={ __( 'Segment' ) }
				help={
					! selected_segment_id
						? __( 'The campaign will be shown to all readers.', 'newspack-popups' )
						: __(
								'The campaign will be shown only to readers who match the selected segment.',
								'newspack-popups'
						  )
				}
				value={ selected_segment_id }
				onChange={ value => onMetaFieldChange( 'selected_segment_id', value ) }
				options={ [
					{
						value: '',
						label: __( 'All readers', 'newspack-popups' ),
					},
					...segmentsList.map( segment => ( {
						value: segment.id,
						label: segment.name,
					} ) ),
				] }
			/>
			<ExternalLink
				href="/wp-admin/admin.php?page=newspack-popups-wizard#/segmentation"
				key="segmentation-link"
			>
				{ __( 'Manage your campaign segments' ) }
			</ExternalLink>
		</Fragment>
	);
};

export default SegmentationSidebar;
