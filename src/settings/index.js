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
				<CheckboxControl
					label={ __(
						'Suppress Newsletter campaigns if visitor is coming from email.',
						'newspack-popups'
					) }
					disabled={ inFlight }
					checked={ settings.suppress_newsletter_campaigns === '1' }
					onChange={ handleSettingChange( 'suppress_newsletter_campaigns' ) }
				/>
				<CheckboxControl
					label={ __(
						'Suppress all Newsletter campaigns if at least one Newsletter campaign was permanently dismissed.',
						'newspack-popups'
					) }
					disabled={ inFlight }
					checked={ settings.suppress_all_newsletter_campaigns_if_one_dismissed === '1' }
					onChange={ handleSettingChange( 'suppress_all_newsletter_campaigns_if_one_dismissed' ) }
				/>
				<CheckboxControl
					label={ __(
						'Suppress all donation campaigns if the reader has donated.',
						'newspack-popups'
					) }
					disabled={ inFlight }
					checked={ settings.suppress_donation_campaigns_if_donor === '1' }
					onChange={ handleSettingChange( 'suppress_donation_campaigns_if_donor' ) }
				/>
				<CheckboxControl
					label={ __( 'Enable non-interactive mode.', 'newspack-popups' ) }
					help={ __(
						'Use this setting in high traffic scenarios. No API requests will be made, reducing server load. Inline campaigns will be shown to all users without dismissal buttons, and overlay campaigns will be suppressed.',
						'newspack-popups'
					) }
					disabled={ inFlight }
					checked={ settings.newspack_newsletters_non_interative_mode === '1' }
					onChange={ handleSettingChange( 'newspack_newsletters_non_interative_mode' ) }
				/>
			</Card>
		</Grid>
	);
};

domReady( () => {
	const element = document.getElementById( 'newspack-popups-settings-root' );
	render( <App />, element );
} );
