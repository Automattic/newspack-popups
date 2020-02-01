/**
 * Data selector for popup options (stored in post meta)
 */
export const optionsFieldsSelector = select => {
	const { getEditedPostAttribute, getCurrentPostId, isSavingPost, isCurrentPostPublished } = select(
		'core/editor'
	);
	const meta = getEditedPostAttribute( 'meta' );
	const {
		background_color,
		frequency,
		dismiss_text,
		display_title,
		overlay_color,
		overlay_opacity,
		placement,
		trigger_scroll_progress,
		trigger_delay,
		trigger_type,
		utm_suppression,
	} = meta || {};
	return {
		background_color,
		dismiss_text,
		display_title,
		frequency,
		id: getCurrentPostId(),
		overlay_color,
		overlay_opacity,
		newspack_popups_is_sitewide_default: getEditedPostAttribute(
			'newspack_popups_is_sitewide_default'
		),
		placement,
		trigger_scroll_progress,
		trigger_delay,
		trigger_type,
		utm_suppression,
		isSavingPost: isSavingPost(),
		isCurrentPostPublished: isCurrentPostPublished(),
	};
};
