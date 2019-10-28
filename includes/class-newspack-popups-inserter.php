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

	const NEWSPACK_POPUPS_VIEW_LIMIT = 1;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'the_content', [ $this, 'popup' ] );
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
		if ( is_user_logged_in() || ! is_single() ) {
			return $content;
		}
		/* End */
		$popup = self::retrieve_popup();
		if ( $popup ) {
			$markup  = self::generate_popup( $popup );
			$content = self::insert_popup( $content, $popup );
			wp_enqueue_script( 'amp-animation' );
			wp_enqueue_script( 'amp-position-observer' );
			\wp_register_style(
				'newspack-popups-view',
				plugins_url( '../dist/view.css', __FILE__ ),
				null,
				filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/view.css' )
			);
			\wp_style_add_data( 'newspack-popups-view', 'rtl', 'replace' );
			\wp_enqueue_style( 'newspack-popups-view' );
		}
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
	 * @return object Popup object
	 */
	public static function retrieve_popup() {
		$popup = null;

		$args = [
			'post_type'      => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		];

		$query = new WP_Query( $args );
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
						'frequency'               => get_post_meta( get_the_ID(), 'frequency', true ),
						'trigger_type'            => get_post_meta( get_the_ID(), 'trigger_type', true ),
						'trigger_delay'           => get_post_meta( get_the_ID(), 'trigger_delay', true ),
						'trigger_scroll_progress' => get_post_meta( get_the_ID(), 'trigger_scroll_progress', true ),
					],
					[
						'frequency'               => 0,
						'trigger_type'            => 'time',
						'trigger_delay'           => 0,
						'trigger_scroll_progress' => 0,
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
		$element_id = 'lightbox' . rand(); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand
		ob_start();
		?>
		<div amp-access="displayPopup" amp-access-hide class="newspack-lightbox" role="button" tabindex="0" id="<?php echo esc_attr( $element_id ); ?>">
			<div class="newspack-popup">
				<?php if ( ! empty( $popup['title'] ) ) : ?>
					<h1><?php echo esc_html( $popup['title'] ); ?></h1>
				<?php endif; ?>
				<?php echo ( $popup['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<button on="tap:<?php echo esc_attr( $element_id ); ?>.hide" class="newspack-lightbox__close" aria-label="<?php esc_html_e( 'Close Pop-up', 'newspack-popups' ) ?>">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" d="M0 0h24v24H0V0z"/><path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>
			</button>
		</div>
		<div id="newspack-lightbox-marker">
			<amp-position-observer on="enter:showAnim.start;" once layout="nodisplay" />
		</div>
		<amp-animation id="showAnim" layout="nodisplay">
			<script type="application/json">
				{
					"duration": 125,
					"fill": "both",
					"iterations": "1",
					"direction": "alternate",
					"animations": [{
						"selector": ".newspack-lightbox",
						"delay": "<?php echo intval( $popup['options']['trigger_delay'] ) * 1000; ?>",
						"keyframes": [{
							"opacity": 1,
							"transform": "translateX( 0 )",
							"visibility": "visible"
						}]
					}]
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
		$popup = self::retrieve_popup();
		if ( ! $popup ) {
			return null;
		}
		$endpoint = str_replace( 'http://', '//', get_rest_url( null, 'newspack-popups/v1/reader' ) );
		?>
		<script id="amp-access" type="application/json">
			{
				"authorization": "<?php echo esc_url( $endpoint ); ?>?popup_id=<?php echo ( esc_attr( $popup['id'] ) ); ?>&rid=READER_ID&url=CANONICAL_URL&RANDOM",
				"pingback": "<?php echo esc_url( $endpoint ); ?>?popup_id=<?php echo ( esc_attr( $popup['id'] ) ); ?>&rid=READER_ID&url=CANONICAL_URL&RANDOM",
				"authorizationFallbackResponse": {
					"displayPopup": true
				}
			}
		</script>
		<?php
		wp_enqueue_script( 'amp-access' );
	}
}
$newspack_popups_inserter = new Newspack_Popups_Inserter();
