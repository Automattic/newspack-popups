<?php
/**
 * Newspack Popups Model
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * API endpoints
 */
final class Newspack_Popups_Model {

	/**
	 * Retrieve all Popus (first 100).
	 *
	 * @return array Array of Popup objects.
	 */
	public static function retrieve_popups() {
		$args = [
			'post_type'      => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
		];

		$popups = self::retrieve_popup_with_query( new WP_Query( $args ), true );
		foreach ( $popups as &$popup ) {
			if ( ! count( $popup['categories'] ) ) {
				$popup['sitewide_default'] = true;
				break;
			}
		}
		return $popups;
	}

	/**
	 * Set post time to now, making it the sitewide popup.
	 *
	 * @param integer $id ID of the Popup to make sitewide default.
	 */
	public static function set_sitewide_popup( $id ) {
		$popup = self::retrieve_popup_by_id( $id );
		if ( ! $popup ) {
			return new \WP_Error(
				'newspack_popups_popup_doesnt_exist',
				esc_html__( 'The Popup specified does not exist.', 'newspack-popups' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}
		$time = current_time( 'mysql' );
		wp_update_post(
			[
				'ID'            => $id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
			]
		);
	}

	/**
	 * Set categories for a Popup.
	 *
	 * @param integer $id ID of sitewide popup.
	 * @param array   $categories Array of categories to be set.
	 */
	public static function set_popup_categories( $id, $categories ) {
		$popup = self::retrieve_popup_by_id( $id );
		if ( ! $popup ) {
			return new \WP_Error(
				'newspack_popups_popup_doesnt_exist',
				esc_html__( 'The Popup specified does not exist.', 'newspack-popups' ),
				[
					'status' => 400,
					'level'  => 'fatal',
				]
			);
		}
		$category_ids = array_map(
			function( $category ) {
				return $category['id'];
			},
			$categories
		);
		return wp_set_post_categories( $id, $category_ids );
	}

	/**
	 * Retrieve popup CPT post.
	 *
	 * @param array $categories An array of categories to match.
	 * @return object Popup object
	 */
	public static function retrieve_popup( $categories = [] ) {
		$args = [
			'post_type'      => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		];

		$category_ids = array_map(
			function( $category ) {
				return $category->term_id;
			},
			$categories
		);
		if ( count( $category_ids ) ) {
			$args['category__in'] = $category_ids;
		} else {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => 'category',
					'operator' => 'NOT EXISTS',
				],
			];
		}

		$popups = self::retrieve_popup_with_query( new WP_Query( $args ) );
		return count( $popups ) > 0 ? $popups[0] : null;
	}


	/**
	 * Retrieve popup preview CPT post.
	 *
	 * @param string $post_id Post id.
	 * @return object Popup object.
	 */
	public static function retrieve_preview_popup( $post_id ) {
		// A preview is stored in an autosave.
		$autosave = wp_get_post_autosave( $post_id );
		return self::create_popup_object(
			$autosave ? $autosave : get_post( $post_id ),
			false,
			[
				'dismiss_text'            => filter_input( INPUT_GET, 'dismiss_text', FILTER_SANITIZE_STRING ),
				'frequency'               => filter_input( INPUT_GET, 'frequency', FILTER_SANITIZE_STRING ),
				'overlay_color'           => filter_input( INPUT_GET, 'overlay_color', FILTER_SANITIZE_STRING ),
				'overlay_opacity'         => filter_input( INPUT_GET, 'overlay_opacity', FILTER_SANITIZE_STRING ),
				'placement'               => filter_input( INPUT_GET, 'placement', FILTER_SANITIZE_STRING ),
				'trigger_type'            => filter_input( INPUT_GET, 'trigger_type', FILTER_SANITIZE_STRING ),
				'trigger_delay'           => filter_input( INPUT_GET, 'trigger_delay', FILTER_SANITIZE_STRING ),
				'trigger_scroll_progress' => filter_input( INPUT_GET, 'trigger_scroll_progress', FILTER_SANITIZE_STRING ),
				'utm_suppression'         => filter_input( INPUT_GET, 'utm_suppression', FILTER_SANITIZE_STRING ),
			]
		);
	}

	/**
	 * Retrieve popup CPT post by ID.
	 *
	 * @param string $post_id Post id.
	 * @return object Popup object.
	 */
	public static function retrieve_popup_by_id( $post_id ) {
		$args = [
			'post_type'      => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'p'              => $post_id,
		];

		$popups = self::retrieve_popup_with_query( new WP_Query( $args ) );
		return count( $popups ) > 0 ? $popups[0] : null;
	}

	/**
	 * Retrieve popup CPT post.
	 *
	 * @param WP_Query $query The query to use.
	 * @param boolean  $include_categories If true, returned objects will include assigned categories.
	 * @return array Popup objects array
	 */
	protected static function retrieve_popup_with_query( WP_Query $query, $include_categories = false ) {
		$popups = [];
		while ( $query->have_posts() ) {
			$query->the_post();
			$popups[] = self::create_popup_object(
				get_post( get_the_ID() ),
				$include_categories
			);
		}
		wp_reset_postdata();
		return $popups;
	}

	/**
	 * Create the popup object.
	 *
	 * @param WP_Post $post The post object.
	 * @param boolean $include_categories If true, returned objects will include assigned categories.
	 * @param object  $options Popup options to use instead of the options retrieved from the post. Used for popup previews.
	 * @return object Popup object
	 */
	protected static function create_popup_object( $post, $include_categories = false, $options = null ) {
		$blocks = parse_blocks( $post->post_content );
		$body   = '';
		foreach ( $blocks as $block ) {
			$body .= render_block( $block );
		}
		$id = $post->ID;

		$post_options = isset( $options ) ? $options : [
			'dismiss_text'            => get_post_meta( $id, 'dismiss_text', true ),
			'frequency'               => get_post_meta( $id, 'frequency', true ),
			'overlay_color'           => get_post_meta( $id, 'overlay_color', true ),
			'overlay_opacity'         => get_post_meta( $id, 'overlay_opacity', true ),
			'placement'               => get_post_meta( $id, 'placement', true ),
			'trigger_type'            => get_post_meta( $id, 'trigger_type', true ),
			'trigger_delay'           => get_post_meta( $id, 'trigger_delay', true ),
			'trigger_scroll_progress' => get_post_meta( $id, 'trigger_scroll_progress', true ),
			'utm_suppression'         => get_post_meta( $id, 'utm_suppression', true ),
		];

		$popup = [
			'id'      => $id,
			'title'   => $post->post_title,
			'body'    => $body,
			'options' => wp_parse_args(
				array_filter( $post_options ),
				[
					'dismiss_text'            => '',
					'frequency'               => 'test',
					'overlay_color'           => '#000000',
					'overlay_opacity'         => 30,
					'placement'               => 'center',
					'trigger_type'            => 'time',
					'trigger_delay'           => 0,
					'trigger_scroll_progress' => 0,
					'utm_suppression'         => null,
				]
			),
		];
		if ( $include_categories ) {
			$popup['categories'] = get_the_category( $id );
		}

		switch ( $popup['options']['trigger_type'] ) {
			case 'scroll':
				$popup['options']['trigger_delay'] = 0;
				break;
			case 'time':
			default:
				$popup['options']['trigger_scroll_progress'] = 0;
				break;
		};
		if ( ! in_array( $popup['options']['placement'], [ 'top', 'bottom' ], true ) ) {
			$popup['options']['placement'] = 'center';
		}
		$popup['markup'] = self::generate_popup( $popup );

		return $popup;
	}

	/**
	 * Generate markup and styles for popup.
	 *
	 * @param string $popup The popup object.
	 * @return string The generated markup.
	 */
	public static function generate_popup( $popup ) {
		$element_id      = 'lightbox' . rand(); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand
		$endpoint        = str_replace( 'http://', '//', get_rest_url( null, 'newspack-popups/v1/reader' ) );
		$classes         = [ 'newspack-lightbox', 'newspack-lightbox-placement-' . $popup['options']['placement'] ];
		$dismiss_text    = ! empty( $popup['options']['dismiss_text'] ) && strlen( trim( $popup['options']['dismiss_text'] ) ) > 0 ? $popup['options']['dismiss_text'] : null;
		$overlay_opacity = absint( $popup['options']['overlay_opacity'] ) / 100;
		$overlay_color   = $popup['options']['overlay_color'];

		// Add a class to indicate a preview.
		if ( Newspack_Popups::previewed_popup_id() ) {
			array_push( $classes, 'newspack-lightbox--preview' );
			// Remove the margin given to root element to account for admin bar. Admin bar is removed
			// on popup preview, but the styling persists because it's being applied prior to admin bar disabling.
			?>
			<style media="screen">
				html{margin-top: 0 !important;}
			</style>
			<?php
		}

		ob_start();
		?>
		<input
			name="url"
			type="hidden"
			value="CANONICAL_URL"
			data-amp-replace="CANONICAL_URL"
		/>
		<input
			name="popup_id"
			type="hidden"
			value="<?php echo ( esc_attr( $popup['id'] ) ); ?>"
		/>
		<input
			name="mailing_list_status"
			type="hidden"
			[value]="mailing_list_status"
		/>
		<?php
		$hidden_fields = ob_get_clean();

		ob_start();
		?>
		<div amp-access="displayPopup" amp-access-hide class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" role="button" tabindex="0" id="<?php echo esc_attr( $element_id ); ?>">
			<div class="newspack-popup-wrapper">
				<div class="newspack-popup">
					<?php if ( ! empty( $popup['title'] ) ) : ?>
						<h1 class="newspack-popup-title"><?php echo esc_html( $popup['title'] ); ?></h1>
					<?php endif; ?>
					<?php echo ( $popup['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( $dismiss_text ) : ?>
					<form class="popup-not-interested-form"
						method="POST"
						action-xhr="<?php echo esc_url( $endpoint ); ?>"
						target="_top">
						<?php echo $hidden_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input
							name="suppress_forever"
							type="hidden"
							value="1"
						/>
						<button on="tap:<?php echo esc_attr( $element_id ); ?>.hide" aria-label="<?php esc_attr( $dismiss_text ); ?>"><?php echo esc_attr( $dismiss_text ); ?></button>
					</form>
					<?php endif; ?>
					<form class="popup-dismiss-form"
						method="POST"
						action-xhr="<?php echo esc_url( $endpoint ); ?>"
						target="_top">
						<?php echo $hidden_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button on="tap:<?php echo esc_attr( $element_id ); ?>.hide" class="newspack-lightbox__close" aria-label="<?php esc_html_e( 'Close Pop-up', 'newspack-popups' ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>
						</button>
					</form>
				</div>
			</div>
			<form class="popup-dismiss-form"
				method="POST"
				action-xhr="<?php echo esc_url( $endpoint ); ?>"
				target="_top">
				<?php echo $hidden_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button style="opacity: <?php echo floatval( $overlay_opacity ); ?>;background-color:<?php echo esc_attr( $overlay_color ); ?>;" class="newspack-lightbox-shim" on="tap:<?php echo esc_attr( $element_id ); ?>.hide"></button>
			</form>
		</div>
		<div id="newspack-lightbox-marker">
			<amp-position-observer on="enter:showAnim.start;" once layout="nodisplay" />
		</div>
		<amp-animation id="showAnim" layout="nodisplay">
			<script type="application/json">
				{
					"duration": "125ms",
					"fill": "both",
					"iterations": "1",
					"direction": "alternate",
					"animations": [
						{
							"selector": ".newspack-lightbox",
							"delay": "<?php echo intval( $popup['options']['trigger_delay'] ) * 1000 + 500; ?>",
							"keyframes": {
								"opacity": ["0", "1"],
								"visibility": ["hidden", "visible"]
							}
						},
						{
								"selector": ".newspack-popup-wrapper",
								"delay": "<?php echo intval( $popup['options']['trigger_delay'] ) * 1000 + 625; ?>",
								"keyframes": {
									<?php if ( 'top' === $popup['options']['placement'] ) : ?>
										"transform": ["translateY(-100%)", "translateY(0)"]
									<?php elseif ( 'bottom' === $popup['options']['placement'] ) : ?>
										"transform": ["translateY(100%)", "translateY(0)"]
									<?php else : ?>
										"opacity": ["0", "1"]
									<?php endif; ?>
								}
						}
					]
				}
			</script>
		</amp-animation>
		<?php
		return ob_get_clean();
	}
}
