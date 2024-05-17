/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { ToggleControl, DatePicker } from '@wordpress/components';
import { isInTheFuture } from '@wordpress/date';
import { useEffect, useState } from '@wordpress/element';

const ExpirationPanel = ( {
	expiration_date = null,
	postStatus,
	onMetaFieldChange,
	createNotice,
	removeNotice,
} ) => {
	const [ noticeId, setNoticeId ] = useState( false );
	useEffect( () => {
		if ( expiration_date && ! isInTheFuture( expiration_date ) ) {
			if ( noticeId ) {
				return;
			}
			createNotice(
				'warning',
				sprintf(
					/* translators: %s is the expiration date */
					__(
						'This prompt has expired on %s and has been reverted to draft. Publishing it will reset the expiration date.',
						'newspack-plugin'
					),
					new Date( expiration_date ).toLocaleDateString()
				),
				{
					isDismissible: false,
				}
			).then( ( { notice } ) => {
				setNoticeId( notice.id );
			} );
		}
	}, [ expiration_date ] );

	useEffect( () => {
		if ( postStatus === 'publish' && noticeId ) {
			removeNotice( noticeId );
			createNotice(
				'info',
				__(
					'This prompt has been published. The expiration date has been removed.',
					'newspack-plugin'
				)
			);
			// This is just for quicked feedback, the actual meta field deletion will
			// happen on the backend.
			onMetaFieldChange( { expiration_date: null } );
		}
	}, [ postStatus ] );

	return (
		<>
			<ToggleControl
				label={ __( 'Expiration Date', 'newspack-newsletters' ) }
				checked={ !! expiration_date }
				onChange={ () => {
					if ( expiration_date ) {
						onMetaFieldChange( { expiration_date: null } );
					} else {
						onMetaFieldChange( { expiration_date: new Date() } );
					}
				} }
			/>
			{ expiration_date ? (
				<DatePicker
					currentDate={ expiration_date }
					onChange={ value => onMetaFieldChange( { expiration_date: value } ) }
					isInvalidDate={ date => ! isInTheFuture( date ) }
				/>
			) : null }
		</>
	);
};

export default ExpirationPanel;
