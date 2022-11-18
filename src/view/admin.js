/**
 * Internal dependencies
 */
import './admin.scss';

const toggle = document.querySelectorAll( '.newspack-campaigns-preview-toggle' );
const isHidden = parseInt( localStorage.getItem( 'newspackPopupsHide' ) );

if ( isHidden ) {
	document.body.classList.add( 'newspack-popups-hide-prompts' );
}

const toggleHandler = e => {
	e.preventDefault();
	document.body.classList.toggle( 'newspack-popups-hide-prompts' );
	localStorage.setItem( 'newspackPopupsHide', isHidden ? 0 : 1 );
};

for ( let i = 0; i < toggle.length; i++ ) {
	const thisToggle = toggle[ i ];
	thisToggle.addEventListener( 'click', toggleHandler );
}
