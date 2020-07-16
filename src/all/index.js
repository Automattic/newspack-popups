/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import PopupsManager from './PopupsManager';
import './style.scss';

domReady( () => {
	const element = document.getElementById( 'newspack-popups-all-popups-admin' );
	render( <PopupsManager />, element );
} );
