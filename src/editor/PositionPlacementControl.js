/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import { ButtonGroup, Button, Tooltip } from '@wordpress/components';

const PositionPlacementControl = ( { layout, label, help, onChange, ...props } ) => {
	/**
	 * Set layout options and padding controls for Row Blocks
	 * This will make us of existing block instead of creating new one
	 */
	const layoutOptions = [
		{
			value: 'top-left',
			/* translators: block layout */
			label: __( 'Top left' ),
		},
		{
			value: 'top',
			/* translators: block layout */
			label: __( 'Top center' ),
		},
		{
			value: 'top-right',
			/* translators: block layout */
			label: __( 'Top right' ),
		},
		{
			value: 'center-left',
			/* translators: block layout */
			label: __( 'Center left' ),
		},
		{
			value: 'center',
			/* translators: block layout */
			label: __( 'Center' ),
		},
		{
			value: 'center-right',
			/* translators: block layout */
			label: __( 'Center right' ),
		},
		{
			value: 'bottom-left',
			/* translators: block layout */
			label: __( 'Bottom left' ),
		},
		{
			value: 'bottom',
			/* translators: block layout */
			label: __( 'Bottom center' ),
		},
		{
			value: 'bottom-right',
			/* translators: block layout */
			label: __( 'Bottom right' ),
		},
	];

	return (
		<Fragment>
			<div className="newspack-popups-css-grid-selector">
				<p className="components-base-control__label">{ label }</p>
				<ButtonGroup aria-label={ __( 'Select layout', 'newspack-popups' ) } { ...props }>
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
		</Fragment>
	);
};

export default PositionPlacementControl;
