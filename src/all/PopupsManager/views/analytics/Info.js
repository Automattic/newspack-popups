/**
 * External dependencies.
 */
import classnames from 'classnames';
import { unescape } from 'lodash';
import humanNumber from 'human-number';
import { Notice } from 'newspack-components';

/**
 * WordPress dependencies.
 */
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const formatPercentage = num => `${ String( ( num * 100 ).toFixed( 2 ) ).replace( /\.00$/, '' ) }%`;

const Info = ( { keyMetrics, filtersState, labelFilters, isLoading, postEditLink } ) => {
	const nameFilter =
		filtersState.event_label_id !== '' &&
		labelFilters.find( ( { value } ) => value === filtersState.event_label_id );

	const { seen, form_submissions, link_clicks } = keyMetrics;

	const hasConversionRate = form_submissions >= 0 && seen > 0;
	const hasClickThroughRate = link_clicks >= 0 && seen > 0;
	const notApplicable = __( 'n/a', 'newspack-popups' );

	return (
		<div className="newspack-popups-manager-analytics__info">
			<h2>
				{ nameFilter ? `${ nameFilter.label }:` : __( 'All:', 'newspack-popups' ) }
				{ postEditLink && (
					<Fragment>
						{' '}
						(<a href={ unescape( postEditLink ) }>edit</a>)
					</Fragment>
				) }
			</h2>
			<div className="newspack-popups-manager-analytics__info__sections">
				{ [
					{
						label: __( 'Seen', 'newspack-popups' ),
						value: humanNumber( seen ),
						withSeparator: true,
					},
					{
						label: __( 'Conversion Rate', 'newspack-popups' ),
						value: hasConversionRate ? formatPercentage( form_submissions / seen ) : notApplicable,
					},
					{
						label: __( 'Form Submissions', 'newspack-popups' ),
						value: hasConversionRate ? form_submissions : notApplicable,
						withSeparator: true,
					},
					{
						label: __( 'Click-through Rate', 'newspack-popups' ),
						value: hasClickThroughRate ? formatPercentage( link_clicks / seen ) : notApplicable,
					},
					{
						label: __( 'Link Clicks', 'newspack-popups' ),
						value: hasClickThroughRate ? link_clicks : notApplicable,
					},
				].map( ( section, i ) => (
					<div
						className={ classnames( 'newspack-popups-manager-analytics__info__sections__section', {
							'newspack-popups-manager-analytics__info__sections__section--with-separator':
								section.withSeparator,
							'newspack-popups-manager-analytics__info__sections__section--dimmed':
								! isLoading && section.value === notApplicable,
						} ) }
						key={ i }
					>
						<h2>{ isLoading ? '-' : section.value }</h2>
						<span>{ section.label }</span>
					</div>
				) ) }
			</div>
			{ ! nameFilter && labelFilters.length !== 1 && (
				<Notice
					noticeText={ __(
						'These are aggregated metrics for multiple campaigns. Some of them might not have links or forms, which can skew the displayed rates.',
						'newspack-popups'
					) }
					isWarning
				/>
			) }
		</div>
	);
};

export default Info;
