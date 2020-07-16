/**
 * Popup Action Card
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Component, Fragment } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { Tooltip } from '@wordpress/components';

/**
 * External dependencies.
 */
import FilterListIcon from '@material-ui/icons/FilterList';
import MoreVertIcon from '@material-ui/icons/MoreVert';
import { ActionCard, Button, CategoryAutocomplete } from 'newspack-components';

/**
 * Internal dependencies.
 */
import PopupPopover from '../popup-popover';
import './style.scss';

class PopupActionCard extends Component {
	state = {
		categoriesVisibility: false,
		popoverVisibility: false,
	};

	/**
	 * Render.
	 */
	render = () => {
		const { categoriesVisibility, popoverVisibility } = this.state;
		const {
			className,
			description,
			deletePopup,
			popup,
			previewPopup,
			setCategoriesForPopup,
			setSitewideDefaultPopup,
			publishPopup,
			updatePopup,
		} = this.props;
		const { id, categories, title, sitewide_default: sitewideDefault, status } = popup;
		return (
			<ActionCard
				isSmall
				className={ className }
				title={ title.length ? decodeEntities( title ) : __( '(no title)', 'newspack-popups' ) }
				key={ id }
				description={ description }
				actionText={
					<Fragment>
						{ ! sitewideDefault && (
							<Tooltip text={ __( 'Category filtering', 'newspack-popups' ) }>
								<Button
									className="icon-only"
									onClick={ () =>
										this.setState( { categoriesVisibility: ! categoriesVisibility } )
									}
								>
									<FilterListIcon />
								</Button>
							</Tooltip>
						) }
						<Tooltip text={ __( 'More options', 'newspack-popups' ) }>
							<Button
								className="icon-only"
								onClick={ () => this.setState( { popoverVisibility: ! popoverVisibility } ) }
							>
								<MoreVertIcon />
							</Button>
						</Tooltip>
						{ popoverVisibility && (
							<PopupPopover
								deletePopup={ deletePopup }
								onFocusOutside={ () => this.setState( { popoverVisibility: false } ) }
								popup={ popup }
								setSitewideDefaultPopup={ setSitewideDefaultPopup }
								updatePopup={ updatePopup }
								previewPopup={ previewPopup }
								publishPopup={ 'publish' !== status ? publishPopup : null }
							/>
						) }
					</Fragment>
				}
			>
				{ categoriesVisibility && (
					<CategoryAutocomplete
						value={ categories || [] }
						onChange={ tokens => setCategoriesForPopup( id, tokens ) }
						label={ __( 'Category filtering', 'newspack ' ) }
						disabled={ sitewideDefault }
					/>
				) }
			</ActionCard>
		);
	};
}

PopupActionCard.defaultProps = {
	popup: {},
	deletePopup: () => null,
	setCategoriesForPopup: () => null,
	setSitewideDefaultPopup: () => null,
};

export default PopupActionCard;
