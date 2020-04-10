<?php
/**
 * Newspack Popups Analytics
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Analytics for popups.
 */
final class Newspack_Popups_Analytics {

	/**
	 * Popups to add analytics for.
	 *
	 * @var array $popups An array of format 'element ID => popup object'.
	 */
	protected static $popups = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'googlesitekit_amp_gtag_opt', [ $this, 'insert_analytics' ] );
		add_filter( 'googlesitekit_gtag_opt', [ $this, 'insert_analytics' ] ); // @todo should this be here?
		add_action( 'wp_footer', [ $this, 'print_extra_analytics' ] );
	}

	/**
	 * Add GA event tracking to a popup.
	 *
	 * @param object $popup A popup object.
	 */
	public static function add_event_tracking( $popup ) {
		self::$popups[ $popup['id'] ] = $popup;
	}

	/**
	 * Add analytics for all popups registered with this class.
	 *
	 * @param array $analytics GA config.
	 * @return array Modified $analytics.
	 */
	public static function insert_analytics( $analytics ) {
		if ( Newspack_Popups::previewed_popup_id() ) {
			return $analytics;
		}

		// For non-AMP forms, the *-success handler is not fired (maybe because of missing action-xhr attribute?).
		// This might result in some false-positives, though (event fired when form not submitted successfully).
		$custom_form_submit_event = ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) ? 'amp-form-submit-success' : 'amp-form-submit';
		$event_category           = __( 'Newspack Announcement', 'newspack-popups' );

		foreach ( self::$popups as $popup ) {
			$element_id = 'lightbox-popup-' . $popup['id'];
			/* translators: %$1s: popup title %2$d popup ID */
			$event_label             = sprintf( __( 'Newspack Announcement: %1$s (%2$d)', 'newspack-popups' ), $popup['title'], $popup['id'] );
			$has_link                = preg_match( '/<a\s/', $popup['body'] ) !== 0;
			$has_form                = preg_match( '/<form\s/', $popup['body'] ) !== 0;
			$has_dismiss_form        = 'inline' !== $popup['options']['placement'];
			$has_not_interested_form = Newspack_Popups_Model::get_dismiss_text( $popup );
			$is_inline               = Newspack_Popups_Model::is_inline( $popup );

			if ( ! isset( $analytics['triggers'] ) ) {
				$analytics['triggers'] = [];
			}

			$analytics['triggers'][ 'popupVisible' . ( $is_inline ? 'Inline' : 'Modal' ) ] = [
				'on'             => 'visible',
				'request'        => 'event',
				'selector'       => esc_attr( '#' . $element_id ),
				'visibilitySpec' => [
					'totalTimeMin' => 500,
				],
				'vars'           => [
					'event_name'     => esc_html__( 'Seen', 'newspack-popups' ),
					'event_label'    => esc_html( $event_label ),
					'event_category' => esc_html( $event_category ),
				],
			];

			$analytics['triggers'][ 'popupPageLoaded' . ( $is_inline ? 'Inline' : 'Modal' ) ] = [
				'on'       => 'ini-load',
				'selector' => esc_attr( '#' . $element_id ),
				'request'  => 'event',
				'vars'     => [
					'event_name'     => esc_html__( 'Load', 'newspack-popups' ),
					'event_label'    => esc_html( $event_label ),
					'event_category' => esc_html( $event_category ),
				],
			];

			if ( $has_link ) {
				$trigger_id                           = 'popupAnchorClicks' . ( $is_inline ? 'Inline' : 'Modal' );
				$analytics['triggers'][ $trigger_id ] = [
					'selector' => esc_attr( '#' . $element_id ),
					'on'       => 'click',
					'request'  => 'event',
					'vars'     => [
						'event_name'     => esc_html__( 'Link Click', 'newspack-popups' ),
						'event_label'    => esc_html( $event_label ),
						'event_category' => esc_html( $event_category ),
					],
				];
			}

			if ( $has_form ) {
				$trigger_id                           = 'popupFormSubmitSuccess' . ( $is_inline ? 'Inline' : 'Modal' );
				$analytics['triggers'][ $trigger_id ] = [
					'on'       => $custom_form_submit_event,
					'request'  => 'event',
					'selector' => esc_attr( '#' . $element_id . ' form:not(.popup-action-form)' ),
					'vars'     => [
						'event_name'     => esc_html__( 'Form Submission', 'newspack-popups' ),
						'event_label'    => esc_html( $event_label ),
						'event_category' => esc_html( $event_category ),
					],
				];
			}

			if ( $has_dismiss_form ) {
				$trigger_id                           = 'popupDismissed' . ( $is_inline ? 'Inline' : 'Modal' );
				$analytics['triggers'][ $trigger_id ] = [
					'on'        => 'amp-form-submit-success', // @todo Should this use $custom_form_submit_event?
					'request'   => 'event',
					'selectors' => esc_attr( '#' . $element_id . ' form.popup-dismiss-form' ),
					'vars'      => [
						'event_name'     => esc_html__( 'Dismissal', 'newspack-popups' ),
						'event_label'    => esc_html( $event_label ),
						'event_category' => esc_html( $event_category ),
					],
				];
			}

			if ( $has_not_interested_form ) {
				$trigger_id                           = 'popupNotInterested' . ( $is_inline ? 'Inline' : 'Modal' );
				$analytics['triggers'][ $trigger_id ] = [
					'on'       => 'amp-form-submit-success',
					'request'  => 'event',
					'selector' => esc_attr( '#' . $element_id . ' form.popup-not-interested-form' ),
					'vars'     => [
						'event_name'     => esc_html__( 'Permanent Dismissal', 'newspack-popups' ),
						'event_label'    => esc_html( $event_label ),
						'event_category' => esc_html( $event_category ),
					],
				];
			}
		}

		return $analytics;
	}

	/**
	 * Add extra stand-alone analytics listeners.
	 */
	public static function print_extra_analytics() {
		if ( Newspack_Popups::previewed_popup_id() ) {
			return;
		}

		foreach ( self::$popups as $popup ) {
			if ( Newspack_Popups_Model::get_mailchimp_form_selector( $popup ) ) {
				self::print_extra_mailchimp_analytics( $popup );
			}

			if ( Newspack_Popups_Model::is_inline( $popup ) ) {
				self::print_extra_inline_analytics( $popup );
			}
		}
	}

	/**
	 * Print extra analytics for popups containing MailChimp forms.
	 *
	 * @param object $popup Popup object.
	 */
	protected static function print_extra_mailchimp_analytics( $popup ) {
		global $wp;

		$element_id = 'lightbox-popup-' . $popup['id'];

		// For non-AMP forms, the *-success handler is not fired (maybe because of missing action-xhr attribute?).
		// This might result in some false-positives, though (event fired when form not submitted successfully).
		$custom_form_submit_event = ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) ? 'amp-form-submit-success' : 'amp-form-submit';
		?>
		<amp-analytics>
			<script type="application/json">
				{
					"requests": {
						"event": "<?php echo esc_url( Newspack_Popups_Model::get_dismiss_endpoint() ); ?>"
					},
					"triggers": {
						"formSubmitSuccess": {
							"on": "<?php echo esc_attr( $custom_form_submit_event ); ?>",
							"request": "event",
							"selector": "<?php echo esc_attr( '#' . $element_id . ' ' . Newspack_Popups_Model::get_mailchimp_form_selector( $popup ) ); ?>",
							"extraUrlParams": {
								"popup_id": "<?php echo esc_attr( $popup['id'] ); ?>",
								"url": "<?php echo esc_url( home_url( $wp->request ) ); ?>",
								"mailing_list_status": "subscribed"
							}
						}
					},
					"transport": {
						"beacon": true,
						"xhrpost": true,
						"useBody": true,
						"image": false
					}
				}
			</script>
		</amp-analytics>
		<?php
	}

	/**
	 * Print extra analytics for inline popups.
	 *
	 * @param object $popup Popup object.
	 */
	protected static function print_extra_inline_analytics( $popup ) {
		global $wp;

		$element_id = 'lightbox-popup-' . $popup['id'];

		?>
		<amp-analytics>
			<script type="application/json">
				{
					"requests": {
						"event": "<?php echo esc_url( Newspack_Popups_Model::get_dismiss_endpoint() ); ?>"
					},
					"triggers": {
						"trackPageview": {
							"on": "visible",
							"request": "event",
							"visibilitySpec": {
								"selector": "<?php echo esc_attr( '#' . $element_id ); ?>",
								"visiblePercentageMin": 90,
								"totalTimeMin": 500,
								"continuousTimeMin": 200
							},
							"extraUrlParams": {
								"popup_id": "<?php echo ( esc_attr( $popup['id'] ) ); ?>",
								"url": "<?php echo esc_url( home_url( $wp->request ) ); ?>"
							}
						}
					},
					"transport": {
						"beacon": true,
						"xhrpost": true,
						"useBody": true,
						"image": false
					}
				}
			</script>
		</amp-analytics>	
		<?php
	}
}
$newspack_popups_api = new Newspack_Popups_Analytics();
