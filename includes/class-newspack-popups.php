<?php
/**
 * Newspack Popups set up
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack Popups Class.
 */
final class Newspack_Popups {

	const NEWSPACK_PLUGINS_CPT = 'newspack_plugins_cpt';

	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Ads
	 */
	protected static $instance = null;

	/**
	 * Main Newspack Ads Instance.
	 * Ensures only one instance of Newspack Ads is loaded or can be loaded.
	 *
	 * @return Newspack Ads - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ __CLASS__, 'register_cpt' ] );
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
		add_action( 'the_content', [ __CLASS__, 'popup' ] );
		add_action( 'wp_head', [ __CLASS__, 'popup_access' ] );
		include_once dirname( __FILE__ ) . '/class-newspack-popups-api.php';
	}

	/**
	 * Register the custom post type.
	 */
	public static function register_cpt() {
		$cpt_args = [
			'label'        => __( 'Pop-ups' ),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'smiley',
			'show_in_rest' => true,
			'supports'     => [ 'editor', 'title', 'custom-fields' ],
			'menu_icon'    => 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjE4cHgiIGhlaWdodD0iNjE4cHgiIHZpZXdCb3g9IjAgMCA2MTggNjE4IiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPGcgaWQ9IlBhZ2UtMSIgc3Ryb2tlPSJub25lIiBzdHJva2Utd2lkdGg9IjEiIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+CiAgICAgICAgPHBhdGggZD0iTTMwOSwwIEM0NzkuNjU2NDk1LDAgNjE4LDEzOC4zNDQyOTMgNjE4LDMwOS4wMDE3NTkgQzYxOCw0NzkuNjU5MjI2IDQ3OS42NTY0OTUsNjE4IDMwOSw2MTggQzEzOC4zNDM1MDUsNjE4IDAsNDc5LjY1OTIyNiAwLDMwOS4wMDE3NTkgQzAsMTM4LjM0NDI5MyAxMzguMzQzNTA1LDAgMzA5LDAgWiBNMTc0LDE3MSBMMTc0LDI2Mi42NzEzNTYgTDE3NS4zMDUsMjY0IEwxNzQsMjY0IEwxNzQsNDQ2IEwyNDEsNDQ2IEwyNDEsMzMwLjkxMyBMMzUzLjk5Mjk2Miw0NDYgTDQ0NCw0NDYgTDE3NCwxNzEgWiBNNDQ0LDI5OSBMMzg5LDI5OSBMNDEwLjQ3NzYxLDMyMSBMNDQ0LDMyMSBMNDQ0LDI5OSBaIE00NDQsMjM1IEwzMjcsMjM1IEwzNDguMjQ1OTE5LDI1NyBMNDQ0LDI1NyBMNDQ0LDIzNSBaIE00NDQsMTcxIEwyNjQsMTcxIEwyODUuMjkwNTEyLDE5MyBMNDQ0LDE5MyBMNDQ0LDE3MSBaIiBpZD0iQ29tYmluZWQtU2hhcGUiIGZpbGw9IiMyQTdERTEiPjwvcGF0aD4KICAgIDwvZz4KPC9zdmc+',
		];
		\register_post_type( self::NEWSPACK_PLUGINS_CPT, $cpt_args );
	}

	/**
	 * Register custom fields.
	 */
	public static function register_meta() {
		\register_meta(
			'post',
			'trigger_type',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'string',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);
		\register_meta(
			'post',
			'trigger_scroll_progress',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'integer',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);
		\register_meta(
			'post',
			'trigger_delay',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'integer',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);
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
			'post_type'      => self::NEWSPACK_PLUGINS_CPT,
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
				'title'   => get_the_title(),
				'body'    => $body,
				'options' => wp_parse_args(
					[
						'trigger_type'            => get_post_meta( get_the_ID(), 'trigger_type', true ),
						'trigger_delay'           => get_post_meta( get_the_ID(), 'trigger_delay', true ),
						'trigger_scroll_progress' => get_post_meta( get_the_ID(), 'trigger_scroll_progress', true ),
					],
					[
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
		<style>
			.newspack-popup {
				background: white;
				padding: 2em;
				min-width: 50%;
			}
			.newspack-lightbox {
				background: rgba(0,0,0,.8);
				width: 100%;
				height: 100%;
				position: absolute;
				display: flex;
				align-items: center;
				justify-content: center;
				position: fixed;
				z-index: 99999;
				top: 0;
				left: 0;
				transform: translateX( -99999px );
				visibility: hidden;
			}
			.newspack-lightbox__close {
				position: absolute;
				right: 1em;
				top: 1em;
			}
		</style>
		<div amp-access="displayPopup" amp-access-hide class="newspack-lightbox" role="button" tabindex="0" id="<?php echo esc_attr( $element_id ); ?>">
			<div class="newspack-popup">
				<?php if ( ! empty( $popup['title'] ) ) : ?>
					<h1><?php echo esc_html( $popup['title'] ); ?></h1>
				<?php endif; ?>
				<?php echo ( $popup['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<button on="tap:<?php echo esc_attr( $element_id ); ?>.hide" class="newspack-lightbox__close">x</button>
		</div>
		<div id="newspack-lightbox-marker">
			<amp-position-observer on="enter:showAnim.start;" once layout="nodisplay" />
		</div>
		<amp-animation id="showAnim" layout="nodisplay">
			<script type="application/json">
				{
					"duration": "0",
					"fill": "both",
					"iterations": "1",
					"direction": "alternate",
					"animations": [{
						"selector": ".newspack-lightbox",
						"delay": "<?php echo intval( $popup['options']['trigger_delay'] ) * 1000; ?>",
						"keyframes": [{
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
	 * Load up common JS/CSS for wizards.
	 */
	public static function enqueue_block_editor_assets() {
		$screen = get_current_screen();
		if ( 'newspack_plugins_cpt' !== $screen->post_type ) {
			return;
		}

		\wp_enqueue_script(
			'newspack-popups',
			plugins_url( '../dist/editor.js', __FILE__ ),
			[ 'wp-components' ],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/editor.js' ),
			true
		);

		\wp_register_style(
			'newspack-popups',
			plugins_url( '../dist/editor.css', __FILE__ ),
			[ 'wp-components' ],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/editor.css' )
		);
		\wp_style_add_data( 'newspack-popups', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-popups' );
	}

	/**
	 * Add amp-access header code.
	 */
	public static function popup_access() {
		$endpoint = str_replace( 'http://', '//', get_rest_url( null, 'newspack-popups/v1/reader' ) );
		?>
		<script id="amp-access" type="application/json">
			{
				"authorization": "<?php echo esc_url( $endpoint ); ?>?rid=READER_ID&url=CANONICAL_URL&RANDOM",
				"pingback": "<?php echo esc_url( $endpoint ); ?>?rid=READER_ID&url=CANONICAL_URL&RANDOM",
				"authorizationFallbackResponse": {
					"displayPopup": true
				}
			}
		</script>
		<?php
		wp_enqueue_script( 'amp-access' );
		wp_enqueue_script( 'amp-analytics' );
	}
}
Newspack_Popups::instance();
