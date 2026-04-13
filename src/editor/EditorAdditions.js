/**
 * Popup-related editor changes.
 */

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getEditorDocument, isOverlayPlacement, updateEditorColors } from './utils';

const EditorAdditions = () => {
	const meta = useSelect( select => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) );
	const { background_color, overlay_size, placement } = meta;

	// Keep a ref so the mount effect always has the current background color.
	const backgroundColorRef = useRef( background_color );
	useEffect( () => {
		backgroundColorRef.current = background_color;
	}, [ background_color ] );

	// Update editor colors when the color picker changes.
	useEffect( () => {
		updateEditorColors( background_color );
	}, [ background_color ] );

	// Apply the initial color once the editor canvas is ready. In WP 7.0+ the
	// canvas is an iframe that may not be loaded when the component first mounts,
	// so the color-picker effect above fires before the elements exist.
	useEffect( () => {
		const applyColors = () => updateEditorColors( backgroundColorRef.current );

		// TODO: Remove this when WP 6.9 is no longer supported, and the iframe is always used for the editor.
		if ( getEditorDocument().querySelector( '.editor-styles-wrapper' ) ) {
			applyColors();
			return;
		}

		const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
		if ( iframe ) {
			if ( iframe.contentDocument?.readyState === 'complete' ) {
				// Iframe is already loaded — apply immediately.
				applyColors();
				return;
			}
			// Iframe exists but hasn't finished loading.
			iframe.addEventListener( 'load', applyColors, { once: true } );
			return () => iframe.removeEventListener( 'load', applyColors );
		}

		// Iframe hasn't been inserted yet — watch for it.
		let capturedIframe = null;
		const observer = new MutationObserver( () => {
			const newIframe = document.querySelector( 'iframe[name="editor-canvas"]' );
			if ( newIframe ) {
				observer.disconnect();
				capturedIframe = newIframe;
				newIframe.addEventListener( 'load', applyColors, { once: true } );
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
		return () => {
			observer.disconnect();
			if ( capturedIframe ) {
				capturedIframe.removeEventListener( 'load', applyColors );
			}
		};
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Setting editor size as per the popup size.
	useEffect( () => {
		const applySize = () => {
			const blockEditor = getEditorDocument().querySelector( '.block-editor-block-list__layout' );
			if ( blockEditor ) {
				blockEditor.classList.forEach( className => {
					if ( className.startsWith( 'is-size-' ) ) {
						blockEditor.classList.remove( className );
					}
				} );

				if ( isOverlayPlacement( placement ) ) {
					blockEditor.classList.add( `is-size-${ overlay_size }` );
				}
			}
		};

		applySize();

		// In WP 7.0+, the block list lives inside the editor iframe and may not
		// exist yet on the first run. Reapply when the iframe finishes loading.
		const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
		if ( iframe && iframe.contentDocument?.readyState !== 'complete' ) {
			iframe.addEventListener( 'load', applySize, { once: true } );
			return () => iframe.removeEventListener( 'load', applySize );
		}
	}, [ overlay_size, placement ] );
	return null;
};

export default EditorAdditions;
