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
	 * The popup objects to display.
	 *
	 * @var array
	 */
	protected static $popups = [];

	/**
	 * Retrieve the appropriate popups for the current post.
	 *
	 * @return array Popup objects.
	 */
	public static function popups_for_post() {
		if ( ! empty( self::$popups ) ) {
			return self::$popups;
		}

		// Get the previewed popup and return early if there's one.
		if ( Newspack_Popups::previewed_popup_id() ) {
			return [ Newspack_Popups_Model::retrieve_preview_popup( Newspack_Popups::previewed_popup_id() ) ];
		}

		// 1. Get all inline popups in there first.
		$popups_to_display = Newspack_Popups_Model::retrieve_inline_popups();

		// 2. Get the overlay popup.

		// Check if there's an overlay popup with matching category.
		$category_overlay_popup = Newspack_Popups_Model::retrieve_category_overlay_popup();
		if ( $category_overlay_popup && static::should_display( $category_overlay_popup ) ) {
			array_push(
				$popups_to_display,
				$category_overlay_popup
			);
		} else {
			// If there's no category-matching popup, get the sitewide pop-up.
			$sitewide_default = get_option( Newspack_Popups::NEWSPACK_POPUPS_SITEWIDE_DEFAULT, null );
			if ( $sitewide_default ) {
				$found_popup = Newspack_Popups_Model::retrieve_popup_by_id( $sitewide_default );
				if (
					$found_popup &&
					static::should_display( $found_popup ) &&
					// Prevent inline sitewide default from being added - all inline popups are there.
					'inline' !== $found_popup['options']['placement']
				) {
					array_push(
						$popups_to_display,
						$found_popup
					);
				}
			}
		}

		if ( ! empty( $popups_to_display ) ) {
			return $popups_to_display;
		}

		return null;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'the_content', [ $this, 'insert_popups_in_content' ], 1 );
		add_shortcode( 'newspack-popup', [ $this, 'popup_shortcode' ] );
		add_action( 'after_header', [ $this, 'insert_popups_after_header' ] ); // This is a Newspack theme hook. When used with other themes, popups won't be inserted on archive pages.
		add_action( 'wp_head', [ $this, 'insert_popups_amp_access' ] );
		add_action( 'wp_head', [ $this, 'register_amp_scripts' ] );
	}

	/**
	 * Process popups and insert into post and page content if needed.
	 *
	 * @param string $content The content of the post.
	 */
	public static function insert_popups_in_content( $content = '' ) {
		if ( is_admin() || ! is_singular() ) {
			return $content;
		}

		$popups = self::popups_for_post();

		if ( empty( $popups ) ) {
			return $content;
		}

		// First needs to check if there any inline popups, to handle SCAIP.
		$has_an_inline_popup = count(
			array_filter(
				$popups,
				function( $p ) {
					return 'inline' === $p['options']['placement'];
				}
			)
		);

		if ( $has_an_inline_popup && function_exists( 'scaip_maybe_insert_shortcode' ) ) {
			// Prevent default SCAIP insertion.
			remove_filter( 'the_content', 'scaip_maybe_insert_shortcode', 10 );

			// In order to prevent the SCAIP ad being inserted mid-popup, let's insert the ads
			// manually. SCAI begins by checking if there are any ads already inserted and bails
			// if there are, to allow for manual ads placement.
			$content = scaip_maybe_insert_shortcode( $content );
		}

		// Now insert the popups.
		foreach ( $popups as $popup ) {
			$is_inline = 'inline' === $popup['options']['placement'];
			$content   = $is_inline ? self::insert_inline_popup_shortcode( $content, $popup ) : self::insert_popup( $content, $popup );
		}

		self::enqueue_popup_assets();

		return $content;
	}

	/**
	 * Process popups and insert into archive pages if needed. Applies to Newspack Theme only.
	 */
	public static function insert_popups_after_header() {
		/* Posts and pages are covered by the_content hook */
		if ( is_singular() ) {
			return;
		}

		$popups = self::popups_for_post();

		if ( ! empty( $popups ) ) {
			foreach ( $popups as $popup ) {
				echo $popup['markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			self::enqueue_popup_assets();
		}
	}

	/**
	 * Enqueue the assets needed to display the popups.
	 */
	public static function enqueue_popup_assets() {
		\wp_register_style(
			'newspack-popups-view',
			plugins_url( '../dist/view.css', __FILE__ ),
			null,
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/view.css' )
		);
		\wp_style_add_data( 'newspack-popups-view', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-popups-view' );
	}

	/**
	 * Insert Popup markup into content.
	 *
	 * @param string $content The content of the post.
	 * @param object $popup The popup object to insert.
	 * @return string The content with popup inserted.
	 */
	public static function insert_popup( $content = '', $popup = [] ) {
		if ( 0 === $popup['options']['trigger_scroll_progress'] || Newspack_Popups::previewed_popup_id() ) {
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
	 * Insert inline Popup shortcode into content.
	 *
	 * @param string $content The content of the post.
	 * @param object $popup The popup object to insert.
	 * @return string The content with popup shortcode inserted.
	 */
	public static function insert_inline_popup_shortcode( $content = '', $popup = [] ) {
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
		return $before_popup . '[newspack-popup id="' . $popup['id'] . '"]' . $after_popup;
	}

	/**
	 * The popup shortcode function.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return HTML
	 */
	public static function popup_shortcode( $atts = array() ) {
		return Newspack_Popups_Model::retrieve_popup_by_id( $atts['id'] )['markup'];
	}

	/**
	 * Add amp-access header code.
	 *
	 * @param object $popups The popup objects to handle.
	 */
	public static function insert_popups_amp_access( $popups ) {
		if ( is_admin() ) {
			return;
		}

		$popups                  = self::popups_for_post();
		$endpoint                = str_replace( 'http://', '//', get_rest_url( null, 'newspack-popups/v1/reader' ) );
		$popups_access_providers = [];

		foreach ( $popups as $popup ) {
			// In test frequency cases (logged in site editor) and when previewing,
			// fallback to authorization of true to avoid possible amp-access timeouts.
			$authorization_fallback_response = (
				( 'test' === $popup['options']['frequency'] || Newspack_Popups::previewed_popup_id() ) &&
				is_user_logged_in() &&
				current_user_can( 'edit_others_pages' )
			);

			$amp_access_provider = array(
				'namespace'                     => 'popup_' . $popup['id'],
				'authorization'                 => esc_url( $endpoint ) . '?popup_id=' . esc_attr( $popup['id'] ) . '&rid=READER_ID&url=CANONICAL_URL&RANDOM',
				'noPingback'                    => true,
				'authorizationFallbackResponse' => array(
					'displayPopup' => $authorization_fallback_response,
				),
			);

			array_push(
				$popups_access_providers,
				$amp_access_provider
			);
		}

		if ( ! empty( $popups_access_providers ) ) {
			?>
			<script id="amp-access" type="application/json">
				<?php echo wp_json_encode( $popups_access_providers ); ?>
			</script>
			<?php
		}
	}

	/**
	 * Register and enqueue all required AMP scripts, if needed.
	 */
	public static function register_amp_scripts() {
		if ( ! is_admin() && ! wp_script_is( 'amp-runtime', 'registered' ) ) {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_register_script(
				'amp-runtime',
				'https://cdn.ampproject.org/v0.js',
				null,
				null,
				true
			);
		}
		$scripts = [ 'amp-access', 'amp-analytics', 'amp-animation', 'amp-form', 'amp-bind', 'amp-position-observer' ];
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

	/**
	 * Some popups can only appear on Posts.
	 *
	 * @param object $popup The popup to assess.
	 * @return bool Should popup be shown.
	 */
	public static function assess_is_post( $popup ) {
		if (
			// Inline Pop-ups can only appear in Posts.
			'inline' === $popup['options']['placement'] ||
			// Pop-ups triggered by scroll position can only appear on Posts.
			'scroll' === $popup['options']['trigger_type']
		) {
			return is_single();
		}
		return true;
	}

	/**
	 * If Pop-up Frequency is "Test Mode," assess whether it should be shown.
	 *
	 * @param object $popup The popup to assess.
	 * @return bool Should popup be shown based on Test Mode assessment.
	 */
	public static function assess_test_mode( $popup ) {
		if ( 'test' === $popup['options']['frequency'] ) {
			return is_user_logged_in() && ( current_user_can( 'edit_others_pages' ) || Newspack_Popups::previewed_popup_id() );
		}
		return true;
	}

	/**
	 * If Pop-up has categories, it should only be shown on posts/pages with those.
	 *
	 * @param object $popup The popup to assess.
	 * @return bool Should popup be shown based on categories it has.
	 */
	public static function assess_categories_filter( $popup ) {
		$post_categories  = get_the_category();
		$popup_categories = get_the_category( $popup['id'] );
		if ( $popup_categories && count( $popup_categories ) ) {
			return array_intersect(
				array_column( $post_categories, 'term_id' ),
				array_column( $popup_categories, 'term_id' )
			);
		}
		return true;
	}

	/**
	 * Should Popup be displayed, based on universal conditions.
	 *
	 * @param object $popup The popup to assess.
	 * @return bool Should popup be shown.
	 */
	public static function should_display( $popup ) {
		return self::assess_is_post( $popup ) && self::assess_test_mode( $popup ) && self::assess_categories_filter( $popup );
	}
}
$newspack_popups_inserter = new Newspack_Popups_Inserter();
