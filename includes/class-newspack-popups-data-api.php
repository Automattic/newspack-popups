<?php
/**
 * Newspack Popups Data API
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Popup Data API
 *
 * This class provides data about the prompts to be used by the Newspack Data Events API and the Google Analytics tracking.
 */
final class Newspack_Popups_Data_Api {

	/**
	 * The rendered popups data.
	 *
	 * @var array
	 */
	protected static $popups = [];

	/**
	 * Registers the hooks.
	 */
	public static function init() {
		\add_action( 'newspack_campaigns_after_campaign_render', [ __CLASS__, 'get_rendered_popups' ] );
		\add_action( 'wp_footer', [ __CLASS__, 'print_popups_data' ], 999 );
	}

	/**
	 * Get a description of a prompt's frequency settings, for analytics purposes.
	 *
	 * @param array $popup The popup object for the prompt.
	 *
	 * @return string Frequency summary.
	 */
	public static function get_frequency_summary( $popup ) {
		if ( 'custom' !== $popup['options']['frequency'] ) {
			return $popup['options']['frequency'];
		}

		$custom_settings = [];

		if ( 0 < $popup['options']['frequency_between'] ) {
			// Translators: %d is the number of pageviews in between prompt displays, if greater than 0 (every pageview).
			$custom_settings[] = sprintf( __( 'every %d pageviews', 'newspack-popups' ), $popup['options']['frequency_between'] + 1 );
		}
		if ( 0 < $popup['options']['frequency_start'] ) {
			// Translators: %d is the pageview when the prompt starts to be displayed, if greater than 0 (first pageview).
			$custom_settings[] = sprintf( __( 'starting on pageview %d', 'newspack-popups' ), $popup['options']['frequency_start'] + 1 );
		}
		if ( 0 < $popup['options']['frequency_max'] ) {
			// Translators: %d is the max number number of displays for the prompt, if greater than 0 (no max).
			$custom_settings[] = sprintf( __( 'max %d times', 'newspack-popups' ), $popup['options']['frequency_max'] );

			// Translators: %s is the time period for when the prompt can be displayed again after the max number of displays.
			$custom_settings[] = sprintf( __( 'resetting every %s', 'newspack-popups' ), $popup['options']['frequency_reset'] );
		}

		return implode( ',', $custom_settings );
	}

	/**
	 * Extract the relevant data from a popup.
	 *
	 * This method is used by the Newspack Data Events API.
	 *
	 * @param int|array $popup The popup ID or object.
	 * @return array
	 */
	public static function get_popup_metadata( $popup ) {
		if ( is_numeric( $popup ) ) {
			$popup = Newspack_Popups_Model::retrieve_popup_by_id( $popup );
		}
		$data = [];
		if ( ! $popup ) {
			return $data;
		}

		$data['prompt_id']    = $popup['id'];
		$data['prompt_title'] = $popup['title'];

		if ( isset( $popup['options'] ) ) {
			$data['prompt_frequency'] = self::get_frequency_summary( $popup );
			$data['prompt_placement'] = $popup['options']['placement'] ?? '';
		}

		$watched_blocks = [
			'registration'             => 'newspack/reader-registration',
			'donation'                 => 'newspack-blocks/donate',
			'newsletters_subscription' => 'newspack-newsletters/subscribe',
		];

		$data['prompt_blocks']    = [];
		$data['interaction_data'] = [];

		foreach ( $watched_blocks as $key => $block_name ) {
			if ( \has_block( $block_name, $popup['content'] ) ) {
				$data['prompt_blocks'][] = $key;

				// Get the suggested donation values for the donation block.
				if ( 'donation' === $key ) {
					$prompt_blocks = \parse_blocks( $popup['content'] );
					$donate_block  = array_values(
						array_filter(
							$prompt_blocks,
							function( $block ) use ( $block_name ) {
								return $block_name === $block['blockName'];
							}
						)
					)[0];

					if ( $donate_block && method_exists( '\Newspack\Donations', 'get_donation_settings' ) ) {
						$is_manual        = $donate_block['attrs']['manual'] ?? false;
						$is_layout_tiers  = isset( $donate_block['attrs']['layoutOption'] ) && 'tiers' === $donate_block['attrs']['layoutOption'];
						$default_settings = \Newspack\Donations::get_donation_settings();
						if ( ! $default_settings || is_wp_error( $default_settings ) ) {
							$default_settings = [];
						}
						$donation_settings = $is_manual ? \wp_parse_args( $donate_block['attrs'], $default_settings ) : $default_settings;
						$is_tiered         = $donation_settings['tiered'] ?? false;
						$suggested_amounts = $donation_settings['amounts'] ?? [];
						$disabled_tiers    = $donation_settings['disabledFrequencies'] ?? [];
						$suggested_summary = [];

						// The tiers block layout doesn't allow for one-time donations.
						if ( $is_layout_tiers ) {
							// So we can differentiate between standard and tiers layouts.
							$suggested_summary['l'] = __( 'tiers', 'newspack-popup' );
							$disabled_tiers['once'] = true;
						} else {
							$suggested_summary['l'] = __( 'default', 'newspack-popup' );
						}

						foreach ( $suggested_amounts as $frequency => $amounts ) {
							if ( empty( $disabled_tiers[ $frequency ] ) ) {
								if ( $is_layout_tiers ) {
									// The tiers block layout doesn't allow for "other" inputs.
									array_pop( $amounts );
								} elseif ( ! $is_tiered ) {
									// If standard layout + untiered, only show the suggested amount for "other".
									$amounts = [ end( $amounts ) ];
								}
								$suggested_summary[ substr( $frequency, 0, 1 ) ] = $amounts;
							}
						}

						if ( ! empty( $suggested_summary ) ) {
							$data['interaction_data']['donation_suggested_values'] = \wp_json_encode( $suggested_summary );
						}
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Store the rendered popups data.
	 *
	 * @param array $popup The popup array representation.
	 * @return void
	 */
	public static function get_rendered_popups( $popup ) {
		$data = self::get_popup_metadata( $popup );
		if ( ! empty( $data['prompt_id'] ) ) {
			self::$popups[ $data['prompt_id'] ] = $data;
		}
	}

	/**
	 * Sanitizes the popup params to be sent as params for GA events
	 *
	 * All params in GA events must be strings, so we need to make the array flat and convert all values to strings.
	 *
	 * This method is also used by the Newspack Data Events API.
	 *
	 * @param array $popup_params The popup params as they are returned by Newspack_Popups_Data_Api::get_popup_metadata and by the prompt_interaction data.
	 * @return array
	 */
	public static function prepare_popup_params_for_ga( $popup_params ) {
		// Invalid input.
		if ( ! is_array( $popup_params ) || ! isset( $popup_params['prompt_id'] ) ) {
			return [];
		}

		$sanitized = $popup_params;

		unset( $sanitized['interaction_data'] );
		$sanitized = array_merge( $sanitized, $popup_params['interaction_data'] );

		unset( $sanitized['prompt_blocks'] );
		foreach ( $popup_params['prompt_blocks'] as $block ) {
			$sanitized[ 'prompt_has_' . $block ] = 1;
		}

		// @TODO: How to handle prompts with more than one block?
		$action_type = 'undefined';
		if ( 1 === count( $popup_params['prompt_blocks'] ) ) {
			$action_type = $popup_params['prompt_blocks'][0];
		}
		$sanitized['action_type'] = $action_type;

		return $sanitized;
	}

	/**
	 * Output the rendered popups data as a JS variable.
	 *
	 * @return void
	 */
	public static function print_popups_data() {
		if ( empty( self::$popups ) ) {
			return;
		}
		$popups = array_map( [ __CLASS__, 'prepare_popup_params_for_ga' ], self::$popups );
		?>
		<script>
			var newspackPopupsData = <?php echo \wp_json_encode( $popups ); ?>;
		</script>
		<?php
	}
}

Newspack_Popups_Data_Api::init();
