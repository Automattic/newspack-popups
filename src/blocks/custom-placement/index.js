/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';

/**
 * External dependencies.
 */
import CallToActionIcon from '@material-ui/icons/CallToAction';

/**
 * Internal dependencies.
 */
import metadata from './block.json';
const { attributes, category, name } = metadata;
import { CustomPlacementEditor } from './edit';

export const registerCustomPlacementBlock = () => {
	registerBlockType( name, {
		title: __( 'Newspack Campaigns: Custom Placement', 'newspack-listing' ),
		icon: <CallToActionIcon />,
		category,
		keywords: [
			__( 'newspack', 'newspack-popups' ),
			__( 'campaigns', 'newspack-popups' ),
			__( 'campaign', 'newspack-popups' ),
			__( 'prompt', 'newspack-popups' ),
			__( 'custom', 'newspack-popups' ),
			__( 'placement', 'newspack-popups' ),
		],

		attributes,

		edit: CustomPlacementEditor,
		save: () => null, // uses view.php
	} );
};
