/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, ExternalLink, Notice, Placeholder, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { addQueryArgs } from '@wordpress/url';

/**
 * External dependencies.
 */
import CallToActionIcon from '@material-ui/icons/CallToAction';
import { AutocompleteWithSuggestions } from 'newspack-components';

export const SinglePromptEditor = ( { attributes, setAttributes } ) => {
	const [ loading, setLoading ] = useState( false );
	const [ prompt, setPrompt ] = useState( null );
	const [ error, setError ] = useState( null );
	const { promptId } = attributes;
	const { endpoint } = window?.newspack_popups_blocks_data;

	const getPrompt = async () => {
		setError( null );
		setLoading( true );

		try {
			const response = await apiFetch( {
				path: addQueryArgs( '/wp/v2/' + endpoint, {
					per_page: 1,
					include: promptId,
					_fields: 'meta,title,content',
				} ),
			} );

			setPrompt( response.shift() );
			setLoading( false );
		} catch ( e ) {
			setError(
				e.message ||
					sprintf(
						__( 'There was an error fetching prompt with ID %s.', 'newspack-popups' ),
						promptId
					)
			);
			setLoading( false );
		}
	};

	useEffect(() => {
		if ( promptId ) {
			getPrompt();
		}
	}, [ promptId ]);

	if ( ! loading && promptId && prompt ) {
		return (
			<div className="newspack-popups__single-prompt">
				<h4 className="newspack-popups__single-prompt-title">
					{ prompt.title?.rendered || __( '(no title)', 'newspack-popups' ) }{' '}
					<ExternalLink href={ `/wp-admin/post.php?post=${ promptId }&action=edit` }>
						{ __( 'edit', 'newspack-popups' ) }
					</ExternalLink>
				</h4>
				<div
					className="newspack-popup newspack-inline-popup"
					dangerouslySetInnerHTML={ { __html: prompt.content?.rendered } }
				/>
				<Button
					isSecondary
					onClick={ () => {
						setAttributes( { promptId: 0 } );
						setPrompt( null );
					} }
				>
					{ __( 'Clear prompt' ) }
				</Button>
			</div>
		);
	}

	return (
		<Placeholder
			className="newspack-popups__single-prompt-placeholder"
			label={ __( 'Prompt', 'newspack-popups' ) }
			icon={ <CallToActionIcon style={ { color: '#36f' } } /> }
		>
			{ loading && <Spinner /> }

			{ error && (
				<div className="newspack-popups__single-prompt">
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				</div>
			) }

			{ ! error && ! loading && ! promptId && (
				<AutocompleteWithSuggestions
					label={ __( 'Search for a prompt', 'newspack' ) }
					help={ __(
						'Begin typing prompt title, click autocomplete result to select.',
						'newspack'
					) }
					fetchSavedPosts={ async postIDs => {
						const posts = await apiFetch( {
							path: addQueryArgs( '/wp/v2/' + endpoint, {
								per_page: 100,
								include: postIDs.join( ',' ),
								_fields: 'id,title',
							} ),
						} );

						return posts.map( post => ( {
							value: post.id,
							label: decodeEntities( post.title ) || __( '(no title)', 'newspack' ),
						} ) );
					} }
					fetchSuggestions={ async search => {
						const posts = await apiFetch( {
							path: addQueryArgs( '/wp/v2/' + endpoint, {
								search,
								per_page: 10,
								_fields: 'id,title',
							} ),
						} );

						// Format suggestions for FormTokenField display.
						const result = posts.reduce( ( acc, post ) => {
							acc.push( {
								value: post.id,
								label: decodeEntities( post.title.rendered ) || __( '(no title)', 'newspack' ),
							} );

							return acc;
						}, [] );
						return result;
					} }
					postType={ endpoint }
					postTypeLabel={ 'prompt' }
					maxLength={ 1 }
					onChange={ _value => setAttributes( { promptId: parseInt( _value ) } ) }
					selectedPost={ null }
				/>
			) }
		</Placeholder>
	);
};
