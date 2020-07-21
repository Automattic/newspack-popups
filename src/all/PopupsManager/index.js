/* global newspack_popups_frontend_data */

/**
 * WordPress dependencies.
 */
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies.
 */
import HeaderIcon from '@material-ui/icons/NewReleases';
import { stringify } from 'qs';
import { withWizard, WebPreview, Router } from 'newspack-components';

/**
 * Internal dependencies.
 */
import { PopupGroup, Analytics } from './views';

const { HashRouter, Redirect, Route, Switch } = Router;

const headerText = __( 'Campaigns', 'newspack-popups' );
const subHeaderText = __( 'Reach your readers with configurable campaigns.', 'newspack-popups' );

const tabbedNavigation = [
	{
		label: __( 'Overlay', 'newpack' ),
		path: '/overlay',
		exact: true,
	},
	{
		label: __( 'Inline', 'newpack' ),
		path: '/inline',
		exact: true,
	},
	{
		label: __( 'Analytics', 'newpack' ),
		path: '/analytics',
		exact: true,
	},
];

class PopupsManager extends Component {
	constructor( props ) {
		super( props );
		this.state = {
			popups: this.sortPopups( newspack_popups_frontend_data.all_popups ),
			previewUrl: null,
		};
	}

	/**
	 * Designate which popup should be the sitewide default.
	 *
	 * @param {number} popupId ID of the Popup to become sitewide default.
	 * @param {boolean} isSitewideDefault is sitewide default
	 */
	setSitewideDefaultPopup = ( popupId, isSitewideDefault ) => {
		const { setError, wizardApiFetch } = this.props;
		return wizardApiFetch( {
			path: `/newspack/v1/wizard/newspack-popups-wizard/sitewide-popup/${ popupId }`,
			method: isSitewideDefault ? 'POST' : 'DELETE',
		} )
			.then( ( { popups } ) => this.setState( { popups: this.sortPopups( popups ) } ) )
			.catch( error => setError( error ) );
	};

	/**
	 * Set categories for a Popup.
	 *
	 * @param {number} popupId ID of the Popup to alter.
	 * @param {Array} categories Array of categories to assign to the Popup.
	 */
	setCategoriesForPopup = ( popupId, categories ) => {
		const { setError, wizardApiFetch } = this.props;
		return wizardApiFetch( {
			path: `/newspack/v1/wizard/newspack-popups-wizard/popup-categories/${ popupId }`,
			method: 'POST',
			data: {
				categories,
			},
		} )
			.then( ( { popups } ) => this.setState( { popups: this.sortPopups( popups ) } ) )
			.catch( error => setError( error ) );
	};

	updatePopup = ( popupId, options ) => {
		const { setError, wizardApiFetch } = this.props;
		return wizardApiFetch( {
			path: `/newspack-popups/v1/${ popupId }`,
			method: 'POST',
			data: { options },
		} )
			.then( ( { popups } ) => this.setState( { popups: this.sortPopups( popups ) } ) )
			.catch( error => setError( error ) );
	};

	/**
	 * Delete a popup.
	 *
	 * @param {number} popupId ID of the Popup to alter.
	 */
	deletePopup = popupId => {
		const { setError, wizardApiFetch } = this.props;
		return wizardApiFetch( {
			path: `/newspack-popups/v1/${ popupId }`,
			method: 'DELETE',
		} )
			.then( ( { popups } ) => this.setState( { popups: this.sortPopups( popups ) } ) )
			.catch( error => setError( error ) );
	};

	/**
	 * Publish a popup.
	 *
	 * @param {number} popupId ID of the Popup to alter.
	 */
	publishPopup = popupId => {
		const { setError, wizardApiFetch } = this.props;
		return wizardApiFetch( {
			path: `/newspack/v1/wizard/newspack-popups-wizard/${ popupId }/publish`,
			method: 'POST',
		} )
			.then( ( { popups } ) => this.setState( { popups: this.sortPopups( popups ) } ) )
			.catch( error => setError( error ) );
	};

	/**
	 * Sort Pop-ups into categories.
	 *
	 * @param {[Object]} popups array of popup objects
	 */
	sortPopups = popups => {
		const overlay = this.sortPopupGroup(
			popups.filter( ( { options } ) => 'inline' !== options.placement )
		);
		const inline = this.sortPopupGroup(
			popups.filter( ( { options } ) => 'inline' === options.placement )
		);
		return { overlay, inline };
	};

	/**
	 * Sort Pop-up groups into categories.
	 *
	 * @param {[Object]} popups array of popup objects
	 */
	sortPopupGroup = popups => {
		const test = popups.filter(
			( { options, status } ) => 'publish' === status && 'test' === options.frequency
		);
		const draft = popups.filter( ( { status } ) => 'draft' === status );
		const active = popups.filter(
			( { categories, options, sitewide_default: sitewideDefault, status } ) =>
				'inline' === options.placement
					? 'test' !== options.frequency && 'never' !== options.frequency && 'publish' === status
					: 'test' !== options.frequency &&
					  ( sitewideDefault || categories.length ) &&
					  'publish' === status
		);
		const activeWithSitewideDefaultFirst = [
			...active.filter( ( { sitewide_default: sitewideDefault } ) => sitewideDefault ),
			...active.filter( ( { sitewide_default: sitewideDefault } ) => ! sitewideDefault ),
		];
		const inactive = popups.filter(
			( { categories, options, sitewide_default: sitewideDefault, status } ) =>
				'inline' === options.placement
					? 'never' === options.frequency && 'publish' === status
					: 'test' !== options.frequency &&
					  ( ! sitewideDefault && ! categories.length ) &&
					  'publish' === status
		);
		return { draft, test, active: activeWithSitewideDefaultFirst, inactive };
	};

	previewUrlForPopup = ( { options, id } ) => {
		const { placement, trigger_type: triggerType } = options;
		const previewURL =
			'inline' === placement || 'scroll' === triggerType
				? newspack_popups_frontend_data.preview_post
				: '/';
		return `${ previewURL }?${ stringify( { ...options, newspack_popups_preview_id: id } ) }`;
	};

	render() {
		const { setError, isLoading, startLoading, doneLoading, errorData } = this.props;
		const { popups, previewUrl } = this.state;
		const { inline, overlay } = popups;
		return (
			<WebPreview
				url={ previewUrl }
				renderButton={ ( { showPreview } ) => {
					const sharedProps = {
						headerIcon: <HeaderIcon />,
						headerText,
						subHeaderText,
						tabbedNavigation,
						setError,
						isLoading,
						startLoading,
						doneLoading,
						errorData,
					};
					const popupManagementSharedProps = {
						...sharedProps,
						setSitewideDefaultPopup: this.setSitewideDefaultPopup,
						setCategoriesForPopup: this.setCategoriesForPopup,
						updatePopup: this.updatePopup,
						deletePopup: this.deletePopup,
						previewPopup: popup =>
							this.setState( { previewUrl: this.previewUrlForPopup( popup ) }, () =>
								showPreview()
							),
						publishPopup: this.publishPopup,
					};
					return (
						<HashRouter hashType="slash">
							<Switch>
								<Route
									path="/overlay"
									render={ () => (
										<PopupGroup
											{ ...popupManagementSharedProps }
											items={ overlay }
											buttonText={ __( 'Add new Overlay Campaign', 'newspack-popups' ) }
											buttonAction="/wp-admin/post-new.php?post_type=newspack_popups_cpt"
											emptyMessage={
												isLoading
													? ''
													: __( 'No Overlay Campaigns have been created yet.', 'newspack-popups' )
											}
										/>
									) }
								/>
								<Route
									path="/inline"
									render={ () => (
										<PopupGroup
											{ ...popupManagementSharedProps }
											items={ inline }
											buttonText={ __( 'Add new Inline Campaign', 'newspack-popups' ) }
											buttonAction="/wp-admin/post-new.php?post_type=newspack_popups_cpt&placement=inline"
											emptyMessage={
												isLoading
													? ''
													: __( 'No Inline Campaigns have been created yet.', 'newspack-popups' )
											}
										/>
									) }
								/>
								<Route path="/analytics" render={ () => <Analytics { ...sharedProps } isWide /> } />
								<Redirect to="/overlay" />
							</Switch>
						</HashRouter>
					);
				} }
			/>
		);
	}
}

const PopupsManagerWizard = withWizard( PopupsManager );

export default () => <PopupsManagerWizard />;
