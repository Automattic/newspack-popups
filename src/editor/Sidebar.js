/**
 * Prompt display settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import {
	/* eslint-disable @wordpress/no-unsafe-wp-apis */
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
	/* eslint-enable @wordpress/no-unsafe-wp-apis */
	RangeControl,
	SelectControl,
	ToggleControl,
	CheckboxControl,
} from '@wordpress/components';
import { stretchFullWidth } from '@wordpress/icons';

/**
 * External dependencies
 */
import { without } from 'lodash';

/**
 * Internal dependencies
 */
import { getPlacementHelpMessage } from './utils';
import PositionPlacementControl from './PositionPlacementControl';

const Sidebar = props => {
	const {
		onMetaFieldChange,
		placement,
		overlay_size,
		trigger_type,
		trigger_delay,
		trigger_scroll_progress,
		trigger_blocks_count,
		archive_insertion_posts_count,
		archive_insertion_is_repeating,
		isOverlay,
		archive_page_types = [],
	} = props;
	const overlayTriggerType = 'time' === trigger_type || 'scroll' === trigger_type ? trigger_type : 'time';
	const inlineTriggerType = 'blocks_count' === trigger_type || 'scroll' === trigger_type ? trigger_type : 'scroll';
	const updatePlacement = value => {
		onMetaFieldChange( { placement: value } );
	};
	const updatePlacementWhenPopupIsFullWidth = () => {
		switch ( placement ) {
			case 'top_left':
			case 'top_right':
				onMetaFieldChange( { placement: 'top' } );
				break;
			case 'center_left':
			case 'center_right':
				onMetaFieldChange( { placement: 'center' } );
				break;
			case 'bottom_left':
			case 'bottom_right':
				onMetaFieldChange( { placement: 'bottom' } );
				break;
		}
	};
	const updateSize = size => {
		onMetaFieldChange( { overlay_size: size } );
		if ( 'full-width' === size ) {
			updatePlacementWhenPopupIsFullWidth();
		}
	};
	const customPlacements = window.newspack_popups_data?.custom_placements || {};
	const popupSizeOptions = window.newspack_popups_data?.popup_size_options || [];
	const availableArchivePageTypes = window.newspack_popups_data?.available_archive_page_types || [];

	const helpMessage = getPlacementHelpMessage( props );

	return (
		<>
			<ToggleGroupControl
				__next40pxDefaultSize
				className="newspack-popups__prompt-type-control"
				isBlock
				label={ __( 'Prompt type', 'newspack-popups' ) }
				value={ isOverlay ? 'center' : 'inline' }
				onChange={ updatePlacement }
			>
				<ToggleGroupControlOption label={ __( 'Inline', 'newspack-popups' ) } value="inline" />
				<ToggleGroupControlOption label={ __( 'Overlay', 'newspack-popups' ) } value="center" />
			</ToggleGroupControl>
			{ isOverlay ? (
				<>
					<ToggleGroupControl
						__next40pxDefaultSize
						isBlock
						label={ __( 'Size', 'newspack-popups' ) }
						value={ overlay_size }
						onChange={ updateSize }
					>
						{ popupSizeOptions.map( ( { value, label, shortname } ) =>
							value === 'full-width' ? (
								<ToggleGroupControlOptionIcon key={ value } icon={ stretchFullWidth } label={ label } value={ value } />
							) : (
								<ToggleGroupControlOption key={ value } aria-label={ label } label={ shortname } value={ value } />
							)
						) }
					</ToggleGroupControl>
					<PositionPlacementControl
						layout={ placement }
						label={ __( 'Position', 'newspack-popups' ) }
						help={ helpMessage }
						value={ placement }
						onChange={ updatePlacement }
						size={ overlay_size }
					/>
				</>
			) : (
				<SelectControl
					__next40pxDefaultSize
					label={ __( 'Placement' ) }
					help={ helpMessage }
					value={ placement }
					onChange={ updatePlacement }
					options={ [
						{ value: 'inline', label: __( 'In article content', 'newspack-popups' ) },
						{ value: 'archives', label: __( 'In archive pages' ) },
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
					<ToggleGroupControl
						__next40pxDefaultSize
						isBlock
						label={ __( 'Trigger', 'newspack-popups' ) }
						help={ __( 'The event to trigger the prompt.', 'newspack-popups' ) }
						value={ overlayTriggerType }
						onChange={ value => onMetaFieldChange( { trigger_type: value } ) }
					>
						<ToggleGroupControlOption label={ __( 'Timer', 'newspack-popups' ) } value="time" />
						<ToggleGroupControlOption label={ __( 'Scroll Progress', 'newspack-popups' ) } value="scroll" />
					</ToggleGroupControl>
					{ 'scroll' === overlayTriggerType ? (
						<RangeControl
							__next40pxDefaultSize
							label={ __( 'Scroll Progress (percent)', 'newspack-popups' ) }
							value={ trigger_scroll_progress }
							onChange={ value => onMetaFieldChange( { trigger_scroll_progress: value } ) }
							min={ 1 }
							max={ 100 }
						/>
					) : (
						<RangeControl
							__next40pxDefaultSize
							label={ __( 'Delay (seconds)', 'newspack-popups' ) }
							value={ trigger_delay }
							onChange={ value => onMetaFieldChange( { trigger_delay: value } ) }
							min={ 0 }
							max={ 60 }
						/>
					) }
				</>
			) }
			{ placement === 'inline' && (
				<>
					<ToggleGroupControl
						__next40pxDefaultSize
						isBlock
						label={ __( 'Insertion position', 'newspack-popups' ) }
						help={ __( 'The position at which to insert the prompt.', 'newspack-popups' ) }
						value={ inlineTriggerType }
						onChange={ value => onMetaFieldChange( { trigger_type: value } ) }
					>
						<ToggleGroupControlOption label={ __( 'Percentage', 'newspack-popups' ) } value="scroll" />
						<ToggleGroupControlOption label={ __( 'Blocks Count', 'newspack-popups' ) } value="blocks_count" />
					</ToggleGroupControl>
					{ 'blocks_count' === inlineTriggerType ? (
						<RangeControl
							__next40pxDefaultSize
							label={ __( 'Number of blocks before the prompt', 'newspack-popups' ) }
							value={ trigger_blocks_count }
							onChange={ value => onMetaFieldChange( { trigger_blocks_count: value } ) }
							min={ 0 }
						/>
					) : (
						<RangeControl
							__next40pxDefaultSize
							label={ __( 'Approximate Position (in percent)', 'newspack-popups' ) }
							value={ trigger_scroll_progress }
							onChange={ value => onMetaFieldChange( { trigger_scroll_progress: value } ) }
							min={ 0 }
							max={ 100 }
						/>
					) }
				</>
			) }
			{ placement === 'archives' && (
				<Fragment>
					<RangeControl
						__next40pxDefaultSize
						label={ __( 'Number of articles before prompt', 'newspack-popups' ) }
						value={ archive_insertion_posts_count }
						onChange={ value => onMetaFieldChange( { archive_insertion_posts_count: value } ) }
						min={ 1 }
						max={ 20 }
					/>

					<div className="newspack-popups__prompt-type-control">
						<p
							className="components-base-control__label"
							style={ { fontSize: '11px', fontWeight: 500, lineHeight: 1.4, textTransform: 'uppercase' } }
						>
							{ __( 'Archive Page Types', 'newspack-popups' ) }
						</p>
						{ availableArchivePageTypes.map( ( { name, label } ) => (
							<CheckboxControl
								key={ name }
								label={ label }
								checked={ archive_page_types.indexOf( name ) > -1 }
								onChange={ isIncluded => {
									onMetaFieldChange( {
										archive_page_types: isIncluded ? [ ...archive_page_types, name ] : without( archive_page_types, name ),
									} );
								} }
							/>
						) ) }
					</div>

					<ToggleControl
						label={ __( 'Repeat prompt', 'newspack-popups' ) }
						checked={ archive_insertion_is_repeating }
						onChange={ value => onMetaFieldChange( { archive_insertion_is_repeating: value } ) }
					/>
				</Fragment>
			) }
		</>
	);
};

export default Sidebar;
