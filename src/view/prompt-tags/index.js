import { domReady } from '../utils';
import { getCriteria } from '../../criteria/utils';

window.newspackRAS = window.newspackRAS || [];
window.newspackRAS.push( ras => {

	function attachCriteria( promptTag ) {
		const criteria = getCriteria( promptTag.dataset.criteria );
		if ( ! criteria ) {
			return;
		}
		promptTag.innerHTML = criteria.getValue( ras );
		ras.on( 'data', () => {
			promptTag.innerHTML = criteria.getValue( ras );
		} );
	}

	domReady( () => {
		const promptTags = document.querySelectorAll( '.prompt-tag' );
		if ( ! promptTags.length ) {
			return;
		}
		for ( const promptTag of promptTags ) {
			if ( ! promptTag.dataset.criteria ) {
				continue;
			}
			attachCriteria( promptTag );
		}
	} );

} );
