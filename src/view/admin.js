/**
 * Internal dependencies
 */
import './admin.scss';

const toggle = document.querySelectorAll( '.newspack-campaigns-preview-toggle' );

const toggleHandler = e => {
	e.preventDefault();
	document.body.classList.toggle( 'newspack-popups-hide-prompts' );
};

for ( let i = 0; i < toggle.length; i++ ) {
	const thisToggle = toggle[ i ];
	thisToggle.addEventListener( 'click', toggleHandler );
}
