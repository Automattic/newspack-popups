/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { Fragment, useEffect, useState, createPortal } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { optionsFieldsSelector } from './utils';
import './editor.scss';

const PopupPreview = ( { title, body, options, setShowPreview } ) => {
	const [ domEl, setDomEl ] = useState();
	const [ popupMarkup, setPopupMarkup ] = useState();
	const [ fontFamily, setFontFamily ] = useState();

	useEffect(() => {
		const domParentEl = document.querySelector( '.editor-post-title' );

		// retrieve the possibly custom font family for headings etc.
		setFontFamily(
			window
				.getComputedStyle( document.querySelector( '.editor-post-title__input' ), null )
				.getPropertyValue( 'font-family' )
		);

		if ( ! domEl && domParentEl ) {
			apiFetch( {
				path: '/newspack-popups/v1/preview',
				method: 'POST',
				data: { title, body, options },
			} ).then( data => {
				setPopupMarkup( data.markup );

				const _domEl = document.createElement( 'div' );
				domParentEl.appendChild( _domEl );
				setDomEl( _domEl );

				// attach click handlers to buttons on the popup element
				const hidePreviewButtons = document.getElementsByClassName(
					'newspack-lightbox__close--preview'
				);
				[ ...hidePreviewButtons ].map( el => {
					el.addEventListener( 'click', () => setShowPreview( false ) );
				} );
			} );
		}
	}, [ domEl ]);

	return domEl && popupMarkup && fontFamily
		? ReactDOM.createPortal(
				<div
					// custom font styles and popup markup
					dangerouslySetInnerHTML={ {
						__html: `
      <style>
        .newspack-lightbox h1,
        .newspack-lightbox h2,
        .newspack-lightbox h3,
        .newspack-lightbox h4,
        .newspack-lightbox h5,
        .newspack-lightbox h6,
        .newspack-lightbox blockquote,
        .newspack-lightbox cite,
        .newspack-lightbox button,
        .newspack-lightbox input {
          font-family: ${ fontFamily } !important;
        }
      </style>
      ${ popupMarkup }`,
					} }
				/>,
				domEl
		  )
		: null;
};

const PopupPreviewConnected = withSelect( select => {
	const { getEditedPostContent, getEditedPostAttribute } = select( 'core/editor' );
	return {
		body: getEditedPostContent(),
		title: getEditedPostAttribute( 'title' ),
		options: optionsFieldsSelector( select ),
	};
} )( PopupPreview );

const PopupPreviewSetting = ( { content, options } ) => {
	const [ showPreview, setShowPreview ] = useState();

	return (
		<Fragment>
			<Button
				onClick={ () => setShowPreview( ! showPreview ) }
				isPrimary
				style={ { marginBottom: '17px' } }
			>
				{ __( 'Preview' ) }
			</Button>
			{ showPreview && <PopupPreviewConnected setShowPreview={ setShowPreview } /> }
		</Fragment>
	);
};

export default PopupPreviewSetting;
