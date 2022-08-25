/**
 * External dependencies
 */
import 'intersection-observer';

/**
 * Internal dependencies
 */
import { shouldPolyfillAMPModule } from './utils';
import * as store from './store';
import { runAnimationById } from './animation';

const parsePositionObserverElement = positionObserverElement => {
	const onHandlers = positionObserverElement.getAttribute( 'on' ).split( ';' );

	const on = onHandlers
		.filter( Boolean )
		.map(
			onHandler => /(?<action>\w*):(?<animationId>\w*)\.(?<method>.*)/.exec( onHandler ).groups
		);
	return {
		target: positionObserverElement.getAttribute( 'target' ),
		on,
		once: positionObserverElement.hasAttribute( 'once' ),
	};
};

const runEffect = effect => {
	if ( effect.animationId ) {
		runAnimationById( effect.animationId );
	}
};

const runById = id => {
	if ( store.get( id ) ) {
		const { on } = store.get( id );
		on.forEach( effect => {
			if ( effect.action === 'enter' ) {
				runEffect( effect );
			}
		} );
	}
};

const visibilityObserver = new IntersectionObserver( entries => {
	entries.forEach( observerEntry => {
		if ( observerEntry.isIntersecting ) {
			runById( observerEntry.target.getAttribute( 'id' ) );
			visibilityObserver.unobserve( observerEntry.target );
		}
	} );
} );

const managePositionObserver = positionObserverElement => {
	let config;
	try {
		config = parsePositionObserverElement( positionObserverElement );
	} catch ( error ) {
		return;
	}
	let targetElement = positionObserverElement.parentElement;
	if ( config.target ) {
		targetElement = document.getElementById( config.target );
	}
	store.set( targetElement.getAttribute( 'id' ), config );
	visibilityObserver.observe( targetElement );
};

export const managePositionObservers = () => {
	if ( ! shouldPolyfillAMPModule( 'position-observer' ) ) {
		return;
	}
	[ ...document.querySelectorAll( 'amp-position-observer' ) ].map( managePositionObserver );
};
