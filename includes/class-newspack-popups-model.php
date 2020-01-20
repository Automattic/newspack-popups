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
	 * Retrieve popup CPT post.
	 *
	 * @param array $categories An array of categories to match.
	 * @return object Popup object
	 */
	public static function retrieve_popup( $categories = [] ) {
		$popup = null;

		$args = [
			'post_type'      => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
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
		return self::retrieve_popup_with_query( new WP_Query( $args ) );
	}

	/**
	 * Retrieve popup CPT post by ID.
	 *
	 * @param string $post_id An array of categories to match.
	 * @return object Popup object
	 */
	public static function retrieve_popup_by_id( $post_id ) {
		$popup = null;

		$args = [
			'post_type'      => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'p'              => $post_id,
		];

		return self::retrieve_popup_with_query( new WP_Query( $args ) );
	}

	/**
	 * Retrieve popup CPT post.
	 *
	 * @param WP_Query $query The query to use.
	 * @return object Popup object
	 */
	protected static function retrieve_popup_with_query( WP_Query $query ) {
		$popup = null;
		while ( $query->have_posts() ) {
			$query->the_post();
			$blocks = parse_blocks( get_the_content() );
			$body   = '';
			foreach ( $blocks as $block ) {
				$body .= render_block( $block );
			}
			$popup = self::generate_popup(
				get_the_ID(),
				get_the_title(),
				$body,
				[],
				false
			);
		}
		wp_reset_postdata();
		return $popup;
	}

	/**
	 * Generate a popup object
	 *
	 * @param int $id post id to fetch popup data by (can be null)
	 * @param string $title
	 * @param string $body
	 * @param array $options
	 * @param bool $is_preview generate as preview - without AMP interactivity bits
	 * @return object The popup object.
	 */
	public static function generate_popup($id, $title, $body, $options, $is_preview) {
		$popup = [
			'id'      => $id,
			'title'   => $title,
			'body'    => $body,
			'options' => wp_parse_args(
				array_filter([
					'dismiss_text'            => $id ? get_post_meta( $id, 'dismiss_text', true ): $options['dismiss_text'],
					'frequency'               => $id ? get_post_meta( $id, 'frequency', true ): $options['frequency'],
					'overlay_color'           => $id ? get_post_meta( $id, 'overlay_color', true ): $options['overlay_color'],
					'overlay_opacity'         => $id ? get_post_meta( $id, 'overlay_opacity', true ): $options['overlay_opacity'],
					'placement'               => $id ? get_post_meta( $id, 'placement', true ): $options['placement'],
					'trigger_type'            => $id ? get_post_meta( $id, 'trigger_type', true ): $options['trigger_type'],
					'trigger_delay'           => $id ? get_post_meta( $id, 'trigger_delay', true ): $options['trigger_delay'],
					'trigger_scroll_progress' => $id ? get_post_meta( $id, 'trigger_scroll_progress', true ): $options['trigger_scroll_progress'],
					'utm_suppression'         => $id ? get_post_meta( $id, 'utm_suppression', true ): $options['utm_suppression'],
				]),
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

		switch ( $popup['options']['trigger_type'] ) {
			case 'scroll':
				$popup['options']['trigger_delay'] = 0;
				break;
			case 'time':
			default:
				$popup['options']['trigger_scroll_progress'] = 0;
				break;
		};
		if ( ! in_array( $popup['options']['placement'], [ 'top', 'bottom' ] ) ) {
			$popup['options']['placement'] = 'center';
		}
		$popup['markup'] = self::generate_popup_markup( $popup, $is_preview );
		return $popup;
	}

	/**
	 * Generate markup and styles for popup.
	 *
	 * @param string $popup The popup object.
	 * @return string The generated markup.
	 */
	public static function generate_popup_markup( $popup, $is_preview ) {
		$element_id      = 'lightbox' . rand(); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand
		$endpoint        = str_replace( 'http://', '//', get_rest_url( null, 'newspack-popups/v1/reader' ) );
		$classes         = [ 'newspack-lightbox', 'newspack-lightbox-placement-' . $popup['options']['placement'] ];
		$dismiss_text    = ! empty( $popup['options']['dismiss_text'] ) && strlen( trim( $popup['options']['dismiss_text'] ) ) > 0 ? $popup['options']['dismiss_text'] : null;
		$overlay_opacity = absint( $popup['options']['overlay_opacity'] ) / 100;
		$overlay_color   = $popup['options']['overlay_color'];

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

		$close_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>';

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
						<?php if ( $is_preview ) : ?>
							<div class="popup-not-interested-form">
								<button class="newspack-lightbox__close--preview">
									<?php echo esc_attr( $dismiss_text ); ?>
								</button>
							</div>
						<?php else : ?>
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
					<?php endif; ?>
					<?php if ( $is_preview ) : ?>
						<button class="newspack-lightbox__close newspack-lightbox__close--preview">
							<?php echo $close_icon_svg; ?>
						</button>
					<?php else : ?>
						<form class="popup-dismiss-form"
							method="POST"
							action-xhr="<?php echo esc_url( $endpoint ); ?>"
							target="_top">
							<?php echo $hidden_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<button on="tap:<?php echo esc_attr( $element_id ); ?>.hide" class="newspack-lightbox__close" aria-label="<?php esc_html_e( 'Close Pop-up', 'newspack-popups' ); ?>">
								<?php echo $close_icon_svg; ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $is_preview ) : ?>
				<div class="popup-dismiss-form">
					<button
						style="opacity: <?php echo floatval( $overlay_opacity ); ?>;background-color:<?php echo esc_attr( $overlay_color ); ?>;"
						class="newspack-lightbox-shim newspack-lightbox__close--preview"
						></button>
				</div>
			<?php else : ?>
				<form class="popup-dismiss-form"
					method="POST"
					action-xhr="<?php echo esc_url( $endpoint ); ?>"
					target="_top">
					<?php echo $hidden_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<button
						style="opacity: <?php echo floatval( $overlay_opacity ); ?>;background-color:<?php echo esc_attr( $overlay_color ); ?>;"
						class="newspack-lightbox-shim" on="tap:<?php echo esc_attr( $element_id ); ?>.hide"
					></button>
				</form>
			<?php endif; ?>
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
