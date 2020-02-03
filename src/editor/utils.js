/**
 * Data selector for popup options (stored in post meta)
 */
export const optionsFieldsSelector = select => {
	const { getEditedPostAttribute } = select( 'core/editor' );
	const meta = getEditedPostAttribute( 'meta' );
	const {
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
		dismiss_text,
		display_title,
		frequency,
		overlay_color,
		overlay_opacity,
		placement,
		trigger_scroll_progress,
		trigger_delay,
		trigger_type,
		utm_suppression,
	};
};
