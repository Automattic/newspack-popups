/**
 * Popup frequency options.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component, Fragment } from '@wordpress/element';
import { SelectControl, TextControl } from '@wordpress/components';

class PopupFrequencySidebar extends Component {
	/**
	 * Render
	 */
	render() {
		const { frequency, onMetaFieldChange, placement, utm_suppression } = this.props;

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
