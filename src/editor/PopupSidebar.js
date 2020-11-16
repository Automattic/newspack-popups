/**
 * Popup sisplay settings.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component, Fragment } from '@wordpress/element';
import {
	BaseControl,
	IconButton,
	PanelRow,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

class PopupSidebar extends Component {
	/**
	 * Render
	 */
	render() {
		const {
			dismiss_text,
			dismiss_text_alignment,
			display_title,
			frequency,
			onMetaFieldChange,
			placement,
			trigger_scroll_progress,
			trigger_delay,
			trigger_type,
		} = this.props;
		const isInline = 'inline' === placement;

		const updatePlacement = value => {
			onMetaFieldChange( 'placement', value );
			if ( value !== 'inline' && frequency === 'always' ) {
				onMetaFieldChange( 'frequency', 'once' );
			}
		};

		const alignmentOptions = [
			{
				icon: 'editor-alignleft',
				label: __( 'Left', 'newspack-popups' ),
				value: 'left',
			},
			{
				icon: 'editor-aligncenter',
				label: __( 'Center', 'newspack-popups' ),
				value: '',
			},
			{
				icon: 'editor-alignright',
				label: __( 'Right', 'newspack-popups' ),
				value: 'right',
			},
		];

		return (
			<Fragment>
				<ToggleControl
					label={ __( 'Inline Campaign', 'newspack-popups' ) }
					checked={ isInline }
					onChange={ value => {
						if ( value ) {
							return updatePlacement( 'inline' );
						}
						updatePlacement( 'center' );
					} }
				/>
				<ToggleControl
					label={ __( 'Display Campaign Title', 'newspack-popups' ) }
					checked={ display_title }
					onChange={ value => onMetaFieldChange( 'display_title', value ) }
				/>
				{ ! isInline && (
					<Fragment>
						<SelectControl
							label={ __( 'Placement' ) }
							help={ __( 'The location to display the campaign.', 'newspack-popups' ) }
							value={ placement }
							onChange={ updatePlacement }
							options={ [
								{ value: 'center', label: __( 'Center' ) },
								{ value: 'top', label: __( 'Top' ) },
								{ value: 'bottom', label: __( 'Bottom' ) },
							] }
						/>
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
				{ isInline && (
					<RangeControl
						label={ __( 'Approximate Position (in percent)', 'newspack-popups' ) }
						value={ trigger_scroll_progress }
						onChange={ value => onMetaFieldChange( 'trigger_scroll_progress', value ) }
						min={ 0 }
						max={ 100 }
					/>
				) }
				<TextControl
					label={ __( 'Text for Dismiss Button', 'newspack-popups' ) }
					help={ __(
						'When clicked, this button will permanently dismiss the campaign for the current reader.',
						'newspack-popups'
					) }
					value={ dismiss_text }
					onChange={ value => onMetaFieldChange( 'dismiss_text', value ) }
				/>
				<BaseControl
					label={ __( 'Dismiss Button Alignment', 'newspack-listings' ) }
					id="newspack-popups-dimiss-button-alignment"
				>
					<PanelRow>
						{ alignmentOptions.map( ( option, index ) => (
							<IconButton
								key={ index }
								icon={ option.icon }
								label={ option.label }
								onClick={ () => onMetaFieldChange( 'dismiss_text_alignment', option.value ) }
								isPrimary={ dismiss_text_alignment === option.value }
							/>
						) ) }
					</PanelRow>
				</BaseControl>
			</Fragment>
		);
	}
}

export default PopupSidebar;
