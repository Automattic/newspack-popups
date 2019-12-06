<?php
/**
 * Newspack Popups Inserters
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * API endpoints
 */
final class Newspack_Popups_Inserter {

	/**
	 * The popup object to display.
	 *
	 * @var object
	 */
	protected static $popup = null;

	/**
	 * Retrieve the best popup for the current post.
	 *
	 * @return object Popup object.
	 */
	public static function popup_for_post() {
		if ( self::$popup ) {
			return self::$popup;
		}
		// First try for pop-up with category filtering.
		self::$popup = self::retrieve_popup( get_the_category() );

		// If nothing found, try for sitewide pop-up.
		if ( ! self::$popup ) {
			self::$popup = self::retrieve_popup();
		}
		return self::$popup;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'the_content', [ $this, 'popup' ] );
		add_action( 'wp_head', [ __CLASS__, 'popup_access' ] );
		add_action( 'wp_head', [ __CLASS__, 'popup_access' ] );
	}

	/**
	 * Process popup and insert into content if needed.
	 *
	 * @param string $content The content of the post.
	 * @return string The content with popup inserted.
	 */
	public static function popup( $content = '' ) {
		/* From https://github.com/Automattic/newspack-blocks/pull/115 */
		if ( is_user_logged_in() ) {
			return $content;
		}
		$popup = self::popup_for_post();

		if ( ! $popup ) {
			return $content;
		}

		// Pop-ups triggered by scroll position can only appear on Posts.
		if ( 'scroll' === $popup['options']['trigger_type'] && ! is_single() ) {
			return $content;
		}

		$content = self::insert_popup( $content, $popup );
		\wp_register_style(
			'newspack-popups-view',
			plugins_url( '../dist/view.css', __FILE__ ),
			null,
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/view.css' )
		);
		\wp_style_add_data( 'newspack-popups-view', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-popups-view' );
		return $content;
	}

	/**
	 * Insert Popup markup into content
	 *
	 * @param string $content The content of the post.
	 * @param object $popup The popup object to insert.
	 * @return string The content with popup inserted.
	 */
	public static function insert_popup( $content = '', $popup = [] ) {
		if ( 0 === $popup['options']['trigger_scroll_progress'] ) {
			return $popup['markup'] . $content;
		}

		$position  = 0;
		$positions = [];
		$close_tag = '</p>';
		while ( stripos( $content, $close_tag, $position ) !== false ) {
			$position    = stripos( $content, '</p>', $position ) + strlen( $close_tag );
			$positions[] = $position;
		}
		$total_length       = strlen( $content );
		$percentage         = intval( $popup['options']['trigger_scroll_progress'] ) / 100;
		$precise_position   = $total_length * $percentage;
		$insertion_position = $total_length;
		foreach ( $positions as $position ) {
			if ( $position >= $precise_position ) {
				$insertion_position = $position;
				break;
			}
		}
		$before_popup = substr( $content, 0, $insertion_position );
		$after_popup  = substr( $content, $insertion_position );
		return $before_popup . $popup['markup'] . $after_popup;
	}

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
			$popup = [
				'id'      => get_the_ID(),
				'title'   => get_the_title(),
				'body'    => $body,
				'options' => wp_parse_args(
					[
						'dismiss_text'            => get_post_meta( get_the_ID(), 'dismiss_text', true ),
						'frequency'               => get_post_meta( get_the_ID(), 'frequency', true ),
						'overlay_color'           => get_post_meta( get_the_ID(), 'overlay_color', true ),
						'overlay_opacity'         => get_post_meta( get_the_ID(), 'overlay_opacity', true ),
						'placement'               => get_post_meta( get_the_ID(), 'placement', true ),
						'trigger_type'            => get_post_meta( get_the_ID(), 'trigger_type', true ),
						'trigger_delay'           => get_post_meta( get_the_ID(), 'trigger_delay', true ),
						'trigger_scroll_progress' => get_post_meta( get_the_ID(), 'trigger_scroll_progress', true ),
						'utm_suppression'         => get_post_meta( get_the_ID(), 'utm_suppression', true ),
					],
					[
						'dismiss_text'            => '',
						'frequency'               => 0,
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
			$popup['markup'] = self::generate_popup( $popup );
		}
		wp_reset_postdata();
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

	/**
	 * Add amp-access header code.
	 */
	public static function popup_access() {
		if ( is_user_logged_in() ) {
			return;
		}
		$popup = self::popup_for_post();
		if ( ! $popup ) {
			return;
		}
		// Pop-ups triggered by scroll position can only appear on Posts.
		if ( 'scroll' === $popup['options']['trigger_type'] && ! is_single() ) {
			return;
		}
		$endpoint = str_replace( 'http://', '//', get_rest_url( null, 'newspack-popups/v1/reader' ) );
		?>
		<script id="amp-access" type="application/json">
			{
				"authorization": "<?php echo esc_url( $endpoint ); ?>?popup_id=<?php echo ( esc_attr( $popup['id'] ) ); ?>&rid=READER_ID&url=CANONICAL_URL&RANDOM",
				"noPingback": true,
				"authorizationFallbackResponse": {
					"displayPopup": false
				}
			}
		</script>
		<?php
		static::register_amp_scripts();
	}

	/**
	 * Register and enqueue all required AMP scripts, if needed.
	 */
	public static function register_amp_scripts() {
		if ( ! wp_script_is( 'amp-runtime', 'registered' ) ) {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_register_script(
				'amp-runtime',
				'https://cdn.ampproject.org/v0.js',
				null,
				null,
				true
			);
		}
		$scripts = [ 'amp-access', 'amp-animation', 'amp-form', 'amp-bind', 'amp-position-observer' ];
		foreach ( $scripts as $script ) {
			if ( ! wp_script_is( $script, 'registered' ) ) {
				$path = "https://cdn.ampproject.org/v0/{$script}-latest.js";
				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_register_script(
					$script,
					$path,
					array( 'amp-runtime' ),
					null,
					true
				);
			}
			wp_enqueue_script( $script );
		}
	}
}
$newspack_popups_inserter = new Newspack_Popups_Inserter();
