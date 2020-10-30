/* globals newspack_popups_settings */

/**
 * WordPress dependencies
 */
import { render, useState } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * External dependencies
 */
import { Card, Grid, FormattedHeader, CheckboxControl } from 'newspack-components';
import HeaderIcon from '@material-ui/icons/Settings';

/**
 * Internal dependencies
 */
import './style.scss';

const App = () => {
	const [ inFlight, setInFlight ] = useState( false );
	const [ settings, setSettings ] = useState( newspack_popups_settings );
	const handleSettingChange = option_name => option_value => {
		setInFlight( true );
		apiFetch( {
			path: '/newspack-popups/v1/settings/',
			method: 'POST',
			data: { option_name, option_value },
		} ).then( response => {
			setSettings( response );
			setInFlight( false );
		} );
	};

	return (
		<Grid>
			<FormattedHeader
				headerIcon={ <HeaderIcon /> }
				headerText={ __( 'Campaigns Settings', 'newspack-popups' ) }
			/>
			<Card>
				{ settings.map( setting => (
					<CheckboxControl
						key={ setting.key }
						label={ setting.label }
						help={ setting.help }
						disabled={ inFlight }
						checked={ setting.value === '1' }
						onChange={ handleSettingChange( setting.key ) }
					/>
				) ) }
			</Card>
		</Grid>
	);
};

domReady( () => {
	const element = document.getElementById( 'newspack-popups-settings-root' );
	render( <App />, element );
} );
