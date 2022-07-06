/**
 * Popup frequency options.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import {
	SelectControl,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

const FrequencySidebar = ( {
	frequency,
	frequency_max,
	frequency_start,
	frequency_between,
	frequency_reset,
	onMetaFieldChange,
	utm_suppression,
} ) => {
	console.log( frequency_max, frequency_start, frequency_between, frequency_reset );
	return (
		<Fragment>
			<SelectControl
				label={ __( 'Frequency' ) }
				value={ frequency }
				onChange={ value => {
					onMetaFieldChange( 'frequency', value );

					if ( 'once' === value ) {
						onMetaFieldChange( 'frequency_max', 1 );
						onMetaFieldChange( 'frequency_start', 0 );
						onMetaFieldChange( 'frequency_between', 0 );
						onMetaFieldChange( 'frequency_reset', 'month' );
					}

					if ( 'daily' === value ) {
						onMetaFieldChange( 'frequency_max', 1 );
						onMetaFieldChange( 'frequency_start', 0 );
						onMetaFieldChange( 'frequency_between', 0 );
						onMetaFieldChange( 'frequency_reset', 'day' );
					}

					if ( 'weekly' === value ) {
						onMetaFieldChange( 'frequency_max', 1 );
						onMetaFieldChange( 'frequency_start', 0 );
						onMetaFieldChange( 'frequency_between', 0 );
						onMetaFieldChange( 'frequency_reset', 'week' );
					}

					if ( 'always' === value ) {
						onMetaFieldChange( 'frequency_max', 0 );
						onMetaFieldChange( 'frequency_start', 0 );
						onMetaFieldChange( 'frequency_between', 0 );
						onMetaFieldChange( 'frequency_reset', 'month' );
					}

					if ( 'preset_1' === value ) {
						onMetaFieldChange( 'frequency_max', 5 );
						onMetaFieldChange( 'frequency_start', 3 );
						onMetaFieldChange( 'frequency_between', 3 );
						onMetaFieldChange( 'frequency_reset', 'month' );
					}
				} }
				options={ [
					{ value: 'once', label: __( 'Once a month', 'newspack-popups' ) },
					{ value: 'weekly', label: __( 'Once a week', 'newspack-popups' ) },
					{ value: 'daily', label: __( 'Once a day', 'newspack-popups' ) },
					{
						value: 'preset_1',
						label: __( 'Every 4th pageview, up to 5x per month', 'newspack-popups' ),
					},
					{
						value: 'always',
						label: __( 'Every pageview', 'newspack-popups' ),
					},
					{ value: 'custom', label: __( 'Custom', 'newspack-popups' ) },
				] }
			/>
			{ 'custom' === frequency && (
				<>
					<NumberControl
						className="newspack-popups__frequency-number-control"
						label={ __( 'Max number of displays', 'newspack-popups' ) }
						value={ frequency_max }
						min={ 0 }
						onChange={ value => onMetaFieldChange( 'frequency_max', value ) }
					/>
					<NumberControl
						className="newspack-popups__frequency-number-control"
						label={ __( 'Start after pageview', 'newspack-popups' ) }
						value={ frequency_start }
						min={ 0 }
						onChange={ value => onMetaFieldChange( 'frequency_start', value ) }
					/>
					<NumberControl
						className="newspack-popups__frequency-number-control"
						label={ __( 'Pageviews between displays', 'newspack-popups' ) }
						value={ frequency_between }
						min={ 0 }
						onChange={ value => onMetaFieldChange( 'frequency_between', value ) }
					/>
					<SelectControl
						label={ __( 'Reset counters per:', 'newspack-popups' ) }
						value={ frequency_reset }
						options={ [
							{ value: 'month', label: __( 'Month', 'newspack-popups' ) },
							{ value: 'week', label: __( 'Week', 'newspack-popups' ) },
							{ value: 'day', label: __( 'Day', 'newspack-popups' ) },
						] }
						onChange={ value => onMetaFieldChange( 'frequency_reset', value ) }
					/>
				</>
			) }
			<hr />
			<TextControl
				label={ __( 'UTM Suppression' ) }
				help={ __(
					'Readers arriving at the site via URLs with this utm_source parameter will never be shown the prompt.'
				) }
				value={ utm_suppression }
				placeholder={ __( 'utm_campaign_name', 'newspack' ) }
				onChange={ value => onMetaFieldChange( 'utm_suppression', value ) }
			/>
		</Fragment>
	);
};

export default FrequencySidebar;
