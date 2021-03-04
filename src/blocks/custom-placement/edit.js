/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Placeholder, SelectControl } from '@wordpress/components';

/**
 * External dependencies.
 */
import CallToActionIcon from '@material-ui/icons/CallToAction';

/**
 * Internal dependencies
 */

export const CustomPlacementEditor = ( { attributes, setAttributes } ) => {
	const { customPlacement } = attributes;
	const customPlacements = window.newspack_popups_blocks_data?.custom_placements || {};
	const customPlacementOptions = [
		{
			label: __( 'Choose a custom placement', 'newspack-popups' ),
			value: '',
			disabled: true,
		},
	].concat(
		Object.keys( customPlacements ).map( key => ( {
			value: key,
			label: customPlacements[ key ],
		} ) )
	);

	return (
		<Placeholder
			className="newspack-popups__custom-placement-placeholder"
			label={ __( 'Newspack Campaigns: Custom Placement', 'newspack-popups' ) }
			icon={ <CallToActionIcon /> }
		>
			<SelectControl
				id="newspack-popups__custom-placement-select"
				onChange={ _customPlacement => setAttributes( { _customPlacement } ) }
				value={
					-1 < Object.keys( customPlacements ).indexOf( customPlacement ) ? customPlacement : ''
				}
				options={ customPlacementOptions }
			/>
		</Placeholder>
	);
};
