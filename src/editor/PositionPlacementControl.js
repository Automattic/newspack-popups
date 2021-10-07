/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { ButtonGroup, Button, Tooltip } from '@wordpress/components';

const PositionPlacementControl = ( { layout, label, help, onChange, ...props } ) => {
	/**
	 * Set layout options and padding controls for Row Blocks
	 * This will make us of existing block instead of creating new one
	 */
	const layoutOptions = [
		{
			value: 'top-left',
			/* translators: Overlay Prompt Position */
			label: __( 'Top Left', 'newspack-popups' ),
		},
		{
			value: 'top',
			/* translators: Overlay Prompt Position */
			label: __( 'Top Center', 'newspack-popups' ),
		},
		{
			value: 'top-right',
			/* translators: Overlay Prompt Position */
			label: __( 'Top Right', 'newspack-popups' ),
		},
		{
			value: 'center-left',
			/* translators: Overlay Prompt Position */
			label: __( 'Center Left', 'newspack-popups' ),
		},
		{
			value: 'center',
			/* translators: Overlay Prompt Position */
			label: __( 'Center', 'newspack-popups' ),
		},
		{
			value: 'center-right',
			/* translators: Overlay Prompt Position */
			label: __( 'Center Right', 'newspack-popups' ),
		},
		{
			value: 'bottom-left',
			/* translators: Overlay Prompt Position */
			label: __( 'Bottom Left', 'newspack-popups' ),
		},
		{
			value: 'bottom',
			/* translators: Overlay Prompt Position */
			label: __( 'Bottom Center', 'newspack-popups' ),
		},
		{
			value: 'bottom-right',
			/* translators: Overlay Prompt Position */
			label: __( 'Bottom Right', 'newspack-popups' ),
		},
	];

	return (
		<div className="newspack-popups-css-grid-selector">
			<p className="components-base-control__label">{ label }</p>
			<ButtonGroup aria-label={ __( 'Select Position', 'newspack-popups' ) } { ...props }>
				{ layoutOptions.map( ( { label: layoutLabel, value }, index ) => {
					return (
						<Tooltip text={ layoutLabel } key={ `grid-tooltip-${ index }` }>
							<div className={ value === layout ? 'is-selected' : null }>
								<Button
									isSmall
									isSecondary={ value !== layout }
									isPrimary={ value === layout }
									onClick={ () => {
										onChange( value );
									} }
								/>
							</div>
						</Tooltip>
					);
				} ) }
			</ButtonGroup>
			<p className="components-base-control__help">{ help }</p>
		</div>
	);
};

export default PositionPlacementControl;
