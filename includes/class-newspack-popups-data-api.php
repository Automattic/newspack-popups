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
		add_action( 'newspack_campaigns_after_campaign_render', [ __CLASS__, 'get_rendered_popups' ] );
		add_action( 'wp_footer', [ __CLASS__, 'print_popups_data' ], 999 );
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
			$data['prompt_frequency'] = $popup['options']['frequency'] ?? '';
			$data['prompt_placement'] = $popup['options']['placement'] ?? '';
		}

		$watched_blocks = [
			'registration'             => 'newspack/reader-registration',
			'donation'                 => 'newspack-blocks/donate',
			'newsletters_subscription' => 'newspack-newsletters/subscribe',
		];

		$data['prompt_blocks'] = [];

		foreach ( $watched_blocks as $key => $block_name ) {
			if ( has_block( $block_name, $popup['content'] ) ) {
				$data['prompt_blocks'][] = $key;
			}
		}

		$data['interaction_data'] = [];

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
		$santized = $popup_params;

		unset( $santized['interaction_data'] );
		$santized = array_merge( $santized, $popup_params['interaction_data'] );

		unset( $santized['prompt_blocks'] );
		foreach ( $popup_params['prompt_blocks'] as $block ) {
			$santized[ 'prompt_has_' . $block ] = 1;
		}

		// @TODO: How to handle prompts with more than one block?
		$action_type = 'undefined';
		if ( 1 === count( $popup_params['prompt_blocks'] ) ) {
			$action_type = $popup_params['prompt_blocks'][0];
		}
		$santized['action_type'] = $action_type;

		return $santized;
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
			var newspackPopupsData = <?php echo wp_json_encode( $popups ); ?>;
		</script>
		<?php
	}
}

Newspack_Popups_Data_Api::init();
