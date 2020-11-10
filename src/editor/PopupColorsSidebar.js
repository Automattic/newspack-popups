/**
 * Popup color options.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component, Fragment } from '@wordpress/element';
import { RangeControl } from '@wordpress/components';
import { ColorPaletteControl } from '@wordpress/block-editor';

const segmentsList =
	( window && window.newspack_popups_data && window.newspack_popups_data.segments ) || [];

/**
 * Internal dependencies
 */
import { updateEditorColors } from './utils';

class PopupColorsSidebar extends Component {
	componentDidMount() {
		const { background_color } = this.props;
		updateEditorColors( background_color );
	}
	componentDidUpdate( prevProps ) {
		const { background_color } = this.props;
		if ( background_color !== prevProps.background_color ) {
			updateEditorColors( background_color );
		}
	}
	/**
	 * Render
	 */
	render() {
		const {
			background_color,
			onMetaFieldChange,
			overlay_opacity,
			overlay_color,
			placement,
		} = this.props;
		const isInline = 'inline' === placement;

		return (
			<Fragment>
				<ColorPaletteControl
					value={ background_color }
					onChange={ value => onMetaFieldChange( 'background_color', value || '#FFFFFF' ) }
					label={ __( 'Background Color' ) }
				/>
				<Fragment>
					<ColorPaletteControl
						className={ isInline ? 'newspack-popups__disabled' : '' }
						value={ overlay_color }
						onChange={ value => {
							if ( isInline ) {
								return;
							}
							onMetaFieldChange( 'overlay_color', value || '#000000' );
						} }
						label={ __( 'Overlay Color' ) }
					/>
					<RangeControl
						className={ isInline ? 'newspack-popups__disabled' : '' }
						disabled={ isInline }
						label={ __( 'Overlay opacity' ) }
						help={
							isInline ? __( 'Available for overlay campaigns only.', 'newspack-popups' ) : null
						}
						value={ overlay_opacity }
						onChange={ value => {
							if ( isInline ) {
								return;
							}
							onMetaFieldChange( 'overlay_opacity', value );
						} }
						min={ 0 }
						max={ 100 }
					/>
				</Fragment>
			</Fragment>
		);
	}
}

export default PopupColorsSidebar;
