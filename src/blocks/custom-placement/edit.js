/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { ExternalLink, Notice, Placeholder, SelectControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';

/**
 * External dependencies.
 */
import CallToActionIcon from '@material-ui/icons/CallToAction';

export const CustomPlacementEditor = ( { attributes, setAttributes } ) => {
	const [ loading, setLoading ] = useState( false );
	const [ prompts, setPrompts ] = useState( null );
	const [ error, setError ] = useState( null );
	const { customPlacement } = attributes;
	const customPlacements = window.newspack_popups_blocks_data?.custom_placements || {};
	const customPlacementOptions = [
		{
			label: __( 'Choose a custom placement', 'newspack-popups' ),
			value: '',
			disabled: true,
		},
	].concat(
		Object.keys( customPlacements ).map( key => ( {
			value: key,
			label: customPlacements[ key ],
		} ) )
	);

	const getPrompts = async () => {
		setError( null );
		setLoading( true );

		try {
			const response = await apiFetch( {
				path: addQueryArgs( '/newspack-popups/v1/custom-placement/', {
					custom_placement: customPlacement,
				} ),
				method: 'GET',
			} );

			setPrompts( response );
			setLoading( false );
		} catch ( e ) {
			setError(
				e.message ||
					__( 'There was an error fetching prompts for this custom placement.', 'newspack-popups' )
			);
			setLoading( false );
		}
	};

	useEffect(() => {
		if ( customPlacement ) {
			getPrompts();
		}
	}, [ customPlacement ]);

	return (
		<Placeholder
			className="newspack-popups__custom-placement-placeholder"
			label={ __( 'Newspack Campaigns: Custom Placement', 'newspack-popups' ) }
			icon={ <CallToActionIcon /> }
		>
			<SelectControl
				id="newspack-popups__custom-placement-select"
				onChange={ _customPlacement => setAttributes( { customPlacement: _customPlacement } ) }
				value={
					-1 < Object.keys( customPlacements ).indexOf( customPlacement ) ? customPlacement : ''
				}
				options={ customPlacementOptions }
			/>

			{ loading && <Spinner /> }

			{ error && (
				<div className="newspack-popups__custom-placement-prompts">
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				</div>
			) }

			{ ! loading && ! error && Array.isArray( prompts ) && (
				<div className="newspack-popups__custom-placement-prompts">
					{ 0 === prompts.length && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'No prompts found for this custom placement.', 'newspack-popups' ) }
						</Notice>
					) }
					{ 0 < prompts.length && (
						<>
							<p>
								{ sprintf(
									__(
										'This custom placement contains the following active prompt%s:',
										'newspack-popups'
									),
									1 < prompts.length ? 's' : ''
								) }
							</p>
							<ul>
								{ prompts.map( prompt => (
									<li key={ prompt.id }>
										<ExternalLink href={ `/wp-admin/post.php?post=${ prompt.id }&action=edit` }>
											{ prompt.title }
										</ExternalLink>
									</li>
								) ) }
							</ul>
						</>
					) }
				</div>
			) }
		</Placeholder>
	);
};
