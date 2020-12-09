/**
 * Popup sisplay settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import { RangeControl, SelectControl, ToggleControl } from '@wordpress/components';

const Sidebar = ( {
	display_title,
	frequency,
	onMetaFieldChange,
	placement,
	trigger_scroll_progress,
	trigger_delay,
	trigger_type,
	isOverlay,
	isInline,
} ) => {
	const updatePlacement = value => {
		onMetaFieldChange( 'placement', value );
		if ( ! isInline( value ) && frequency === 'always' ) {
			onMetaFieldChange( 'frequency', 'once' );
		}
	};

	return (
		<Fragment>
			<ToggleControl
				label={ __( 'Inline Campaign', 'newspack-popups' ) }
				checked={ ! isOverlay }
				onChange={ value => updatePlacement( value ? 'inline' : 'center' ) }
			/>
			<SelectControl
				label={ __( 'Placement' ) }
				help={
					isOverlay
						? __( 'The location to display the overlay campaign.', 'newspack-popups' )
						: __( 'The location to insert the campaign.', 'newspack-popups' )
				}
				value={ placement }
				onChange={ updatePlacement }
				options={
					isOverlay
						? [
								{ value: 'center', label: __( 'Center' ) },
								{ value: 'top', label: __( 'Top' ) },
								{ value: 'bottom', label: __( 'Bottom' ) },
						  ]
						: [
								{ value: 'inline', label: __( 'In article content' ) },
								{ value: 'above_header', label: __( 'Above site header' ) },
						  ]
				}
			/>
			{ isOverlay && (
				<Fragment>
					<SelectControl
						label={ __( 'Trigger' ) }
						help={ __( 'The event to trigger the campaign.', 'newspack-popups' ) }
						selected={ trigger_type }
						options={ [
							{ label: __( 'Timer' ), value: 'time' },
							{ label: __( 'Scroll Progress' ), value: 'scroll' },
						] }
						onChange={ value => onMetaFieldChange( 'trigger_type', value ) }
					/>
					{ 'time' === trigger_type && (
						<RangeControl
							label={ __( 'Delay (seconds)' ) }
							value={ trigger_delay }
							onChange={ value => onMetaFieldChange( 'trigger_delay', value ) }
							min={ 0 }
							max={ 60 }
						/>
					) }
					{ 'scroll' === trigger_type && (
						<RangeControl
							label={ __( 'Scroll Progress (percent)', 'newspack-popups' ) }
							value={ trigger_scroll_progress }
							onChange={ value => onMetaFieldChange( 'trigger_scroll_progress', value ) }
							min={ 1 }
							max={ 100 }
						/>
					) }
				</Fragment>
			) }
			{ placement === 'inline' && (
				<RangeControl
					label={ __( 'Approximate Position (in percent)', 'newspack-popups' ) }
					value={ trigger_scroll_progress }
					onChange={ value => onMetaFieldChange( 'trigger_scroll_progress', value ) }
					min={ 0 }
					max={ 100 }
				/>
			) }
			<ToggleControl
				label={ __( 'Display Campaign Title', 'newspack-popups' ) }
				checked={ display_title }
				onChange={ value => onMetaFieldChange( 'display_title', value ) }
			/>
		</Fragment>
	);
};

export default Sidebar;
