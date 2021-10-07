/**
 * Prompt display settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { RadioControl, RangeControl, SelectControl, ToggleControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { isCustomPlacement, getPlacementHelpMessage } from '../editor/utils';
import PositionPlacementControl from './PositionPlacementControl';

const Sidebar = ( {
	display_title,
	hide_border,
	frequency,
	onMetaFieldChange,
	placement,
	overlay_size,
	trigger_scroll_progress,
	trigger_delay,
	trigger_type,
	isOverlay,
	isInlinePlacement,
} ) => {
	const updatePlacement = value => {
		onMetaFieldChange( 'placement', value );
		if ( ! isInlinePlacement( value ) && frequency === 'always' ) {
			onMetaFieldChange( 'frequency', 'once' );
		}
	};
	const customPlacements = window.newspack_popups_data?.custom_placements || {};
	const popupSizeOptions = window.newspack_popups_data?.popup_size_options || {};

	return (
		<>
			<RadioControl
				className="newspack-popups__prompt-type-control"
				label={ __( 'Prompt type', 'newspack-popups' ) }
				selected={ isOverlay ? 'center' : 'inline' }
				options={ [
					{ label: __( 'Inline', 'newspack-popups' ), value: 'inline' },
					{ label: __( 'Overlay', 'newspack-popups' ), value: 'center' },
				] }
				onChange={ updatePlacement }
			/>
			{ isOverlay ? (
				<>
					<SelectControl
						label={ __( 'Size', 'newspack-popups' ) }
						value={ overlay_size }
						onChange={ size => {
							onMetaFieldChange( 'overlay_size', size );
						} }
						options={ popupSizeOptions }
					/>
					<PositionPlacementControl
						layout={ placement }
						label={ __( 'Position', 'newspack-popups' ) }
						help={ getPlacementHelpMessage( placement, trigger_scroll_progress ) }
						value={ placement }
						onChange={ updatePlacement }
						size={ overlay_size }
					/>
				</>
			) : (
				<SelectControl
					label={ __( 'Placement' ) }
					help={ getPlacementHelpMessage( placement, trigger_scroll_progress ) }
					value={ placement }
					onChange={ updatePlacement }
					options={ [
						{ value: 'inline', label: __( 'In article content', 'newspack-popups' ) },
						{ value: 'above_header', label: __( 'Above site header', 'newspack-popups' ) },
						{ value: 'manual', label: __( 'Manual only', 'newspack-popups' ) },
					].concat(
						Object.keys( customPlacements ).map( key => ( {
							value: key,
							label: customPlacements[ key ],
						} ) )
					) }
				/>
			) }

			{ isOverlay && (
				<>
					<SelectControl
						label={ __( 'Trigger', 'newspack-popups' ) }
						help={ __( 'The event to trigger the prompt.', 'newspack-popups' ) }
						selected={ trigger_type }
						options={ [
							{ label: __( 'Timer', 'newspack-popups' ), value: 'time' },
							{ label: __( 'Scroll Progress', 'newspack-popups' ), value: 'scroll' },
						] }
						onChange={ value => onMetaFieldChange( 'trigger_type', value ) }
					/>
					{ 'time' === trigger_type && (
						<RangeControl
							label={ __( 'Delay (seconds)', 'newspack-popups' ) }
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
				</>
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
				label={ __( 'Display Prompt Title', 'newspack-popups' ) }
				checked={ display_title }
				onChange={ value => onMetaFieldChange( 'display_title', value ) }
			/>
			{ ( placement === 'inline' || placement === 'manual' || isCustomPlacement( placement ) ) && (
				<ToggleControl
					label={ __( 'Hide Prompt Border', 'newspack-popups' ) }
					checked={ hide_border }
					onChange={ value => onMetaFieldChange( 'hide_border', value ) }
				/>
			) }
		</>
	);
};

export default Sidebar;
