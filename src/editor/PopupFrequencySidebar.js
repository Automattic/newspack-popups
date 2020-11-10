/**
 * Popup frequency options.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component, Fragment } from '@wordpress/element';
import { SelectControl, TextControl } from '@wordpress/components';

const segmentsList =
	( window && window.newspack_popups_data && window.newspack_popups_data.segments ) || [];

class PopupFrequencySidebar extends Component {
	/**
	 * Render
	 */
	render() {
		const {
			frequency,
			onMetaFieldChange,
			placement,
			selected_segment_id,
			utm_suppression,
		} = this.props;

		return (
			<Fragment>
				<SelectControl
					label={ __( 'Frequency' ) }
					value={ frequency }
					onChange={ value => onMetaFieldChange( 'frequency', value ) }
					options={ [
						{ value: 'never', label: __( 'Never', 'newspack-popups' ) },
						{ value: 'once', label: __( 'Once', 'newspack-popups' ) },
						{ value: 'daily', label: __( 'Once a day', 'newspack-popups' ) },
						{
							value: 'always',
							label: __( 'Every page', 'newspack-popups' ),
							disabled: 'inline' !== placement,
						},
					] }
				/>

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

				<TextControl
					label={ __( 'UTM Suppression' ) }
					help={ __(
						'Readers arriving at the site via URLs with this utm_source parameter will never be shown the campaign.'
					) }
					value={ utm_suppression }
					onChange={ value => onMetaFieldChange( 'utm_suppression', value ) }
				/>
			</Fragment>
		);
	}
}

export default PopupFrequencySidebar;
