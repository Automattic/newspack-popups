/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

const PromptTagsPanel = ( { tags } ) => {
	return (
		<>
			<p>
				{ __( 'Use the following tags to render dynamic content in your prompt:', 'newspack-popups' ) }
			</p>
			<ul>
				{ tags.map( ( tag ) => (
					<li key={ tag.name }>
						<code>{ `{${tag.name}}` }</code>
						{ tag.description && (
							<>
								<br />
								<span>{ tag.description }</span>
							</>
						) }
					</li>
				) ) }
			</ul>
		</>
	)
};

export default PromptTagsPanel;
