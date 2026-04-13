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
			// Iframe exists but may not have finished loading.
			iframe.addEventListener( 'load', applyColors );
			return () => iframe.removeEventListener( 'load', applyColors );
		}

		// Iframe hasn't been inserted yet — watch for it.
		const observer = new MutationObserver( () => {
			const newIframe = document.querySelector( 'iframe[name="editor-canvas"]' );
			if ( newIframe ) {
				observer.disconnect();
				newIframe.addEventListener( 'load', applyColors );
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
		return () => observer.disconnect();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Setting editor size as per the popup size.
	useEffect( () => {
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
	}, [ overlay_size, placement ] );
	return null;
};

export default EditorAdditions;
