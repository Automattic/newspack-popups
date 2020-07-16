/**
 * WordPress dependencies.
 */
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import { withWizardScreen, ActionCardSections, Notice } from 'newspack-components';

/**
 * Internal dependencies
 */
import PopupActionCard from '../../components/popup-action-card';

/**
 * Popup group screen
 */

class PopupGroup extends Component {
	/**
	 * Construct the appropriate description for a single Pop-up based on categories and sitewide default status.
	 *
	 * @param {Object} popup object.
	 */
	descriptionForPopup = ( { categories, sitewide_default: sitewideDefault } ) => {
		if ( sitewideDefault ) {
			return __( 'Sitewide default', 'newspack-popups' );
		}
		if ( categories.length > 0 ) {
			return (
				__( 'Categories: ', 'newspack-popups' ) +
				categories.map( category => category.name ).join( ', ' )
			);
		}
		return null;
	};

	/**
	 * Render.
	 */
	render() {
		const {
			deletePopup,
			emptyMessage,
			items: { active = [], draft = [], test = [], inactive = [] },
			previewPopup,
			setCategoriesForPopup,
			setSitewideDefaultPopup,
			publishPopup,
			updatePopup,
			errorData,
		} = this.props;

		if ( errorData ) {
			return <Notice isError noticeText={ errorData.message } />;
		}

		const getCardClassName = ( { key }, { sitewide_default } ) =>
			( {
				active: sitewide_default ? 'newspack-card__is-primary' : 'newspack-card__is-supported',
				test: 'newspack-card__is-secondary',
				inactive: 'newspack-card__is-disabled',
				draft: 'newspack-card__is-disabled',
			}[ key ] );

		return (
			<ActionCardSections
				sections={ [
					{ key: 'active', label: __( 'Active', 'newspack-popups' ), items: active },
					{ key: 'draft', label: __( 'Draft', 'newspack-popups' ), items: draft },
					{ key: 'test', label: __( 'Test', 'newspack-popups' ), items: test },
					{ key: 'inactive', label: __( 'Inactive', 'newspack-popups' ), items: inactive },
				] }
				renderCard={ ( popup, section ) => (
					<PopupActionCard
						className={ getCardClassName( section, popup ) }
						deletePopup={ deletePopup }
						description={ this.descriptionForPopup( popup ) }
						key={ popup.id }
						popup={ popup }
						previewPopup={ previewPopup }
						setCategoriesForPopup={
							section.key === 'active' || section.key === 'test'
								? setCategoriesForPopup
								: () => null
						}
						setSitewideDefaultPopup={ setSitewideDefaultPopup }
						updatePopup={ updatePopup }
						publishPopup={ section.key === 'draft' ? publishPopup : undefined }
					/>
				) }
				emptyMessage={ emptyMessage }
			/>
		);
	}
}

export default withWizardScreen( PopupGroup );
