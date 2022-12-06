<?php
/**
 * Newspack Popups Importer
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/schemas/schemas-loader.php';

/**
 * Importer
 */
class Newspack_Popups_Importer {

	/**
	 * Stores the total number of items exported.
	 *
	 * @var int[]
	 */
	private $totals;

	/**
	 * Store the errors of items that could not be exported.
	 *
	 * @var int[]
	 */
	private $errors;

	/**
	 * Store the mapping between the ID of the terms in the input data and the ID of the created terms.
	 *
	 * @var int[]
	 */
	private $terms_mapping;

	/**
	 * Store the mapping between the ID of the segments in the input data and the ID of the created segments.
	 *
	 * @var string[]
	 */
	private $segments_mapping;

	/**
	 * Constructor.
	 *
	 * @param array $input_data Data to import.
	 */
	public function __construct( $input_data ) {
		$this->reset_results();
		$this->input = $input_data;
	}

	/**
	 * Export prompts, segments and campaigns.
	 *
	 * @return array Exported data.
	 */
	public function import() {
		$this->reset_results();

		$validation = new Newspack\Campaigns\Schemas\Package( $this->input );
		if ( ! $validation->is_valid() ) {
			return [
				'errors' => [
					'validation' => $validation->get_errors(),
				],
			];
		}

		$this->process_campaigns();
		$this->process_segments();
		$this->process_prompts();

		return [
			'totals' => $this->totals,
			'errors' => $this->errors,
		];
	}

	/**
	 * Reset results for a new export
	 *
	 * @return void
	 */
	private function reset_results() {
		$this->totals           = [
			'prompts'   => 0,
			'segments'  => 0,
			'campaigns' => 0,
		];
		$this->errors           = [
			'prompts'    => [],
			'segments'   => [],
			'campaigns'  => [],
			'terms'      => [],
			'validation' => [],
		];
		$this->terms_mapping    = [];
		$this->segments_mapping = [];
	}

	/**
	 * Process campaigns
	 *
	 * Create the campaigns and store the mapping between the old and new IDs.
	 *
	 * @return void
	 */
	private function process_campaigns() {
		$campaigns = $this->input['campaigns'];
		foreach ( $campaigns as $campaign ) {
			$created                                = Newspack_Popups::create_campaign( $campaign['name'] );
			$this->terms_mapping[ $campaign['id'] ] = $created;
			$this->totals['campaigns']++;
		}
	}

	/**
	 * Process segments
	 *
	 * Create the segments and store the mapping between the old and new IDs.
	 *
	 * @return void
	 */
	private function process_segments() {
		$segments = $this->input['segments'];
		$segments = $this->pre_process_segments_terms( $segments );
		foreach ( $segments as $segment ) {
			$stored_segments                          = Newspack_Popups_Segmentation::create_segment( $segment );
			$created                                  = end( $stored_segments );
			$this->segments_mapping[ $segment['id'] ] = $created['id'];
			$this->totals['segments']++;
		}
	}

	/**
	 * Pre process terms in segments
	 *
	 * @param array $segments The segments from input.
	 * @return array The segments with the terms pre processed.
	 */
	private function pre_process_segments_terms( $segments ) {
		foreach ( $segments as $segment_index => $segment ) {
			if ( ! empty( $segment['configuration']['favorite_categories'] ) ) {
				$segments[ $segment_index ]['configuration']['favorite_categories'] = $this->pre_process_terms( $segment['configuration']['favorite_categories'] );
			}
		}
		return $segments;
	}

	/**
	 * Process prompts
	 *
	 * @return void
	 */
	private function process_prompts() {
		$prompts = $this->input['prompts'];
		$prompts = $this->pre_process_prompt_segments( $prompts );
		$prompts = $this->pre_process_prompts_terms( $prompts );

		foreach ( $prompts as $prompt ) {
			$post_data = [
				'post_title'   => $prompt['title'],
				'post_content' => $prompt['content'],
				'post_status'  => $prompt['status'],
				'post_type'    => Newspack_Popups::NEWSPACK_POPUPS_CPT,
			];
			$new_post  = wp_insert_post( $post_data );
			if ( is_wp_error( $new_post ) ) {
				$this->errors['prompts'][] = $new_post->get_error_message();
				continue;
			}

			$this->totals['prompts']++;

			Newspack_Popups_Model::set_popup_options( $new_post, $prompt['options'] );

			if ( ! empty( $prompt['campaign_groups'] ) ) {
				Newspack_Popups_Model::set_popup_terms( $new_post, $prompt['campaign_groups'], Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY );
			}
			if ( ! empty( $prompt['categories'] ) ) {
				Newspack_Popups_Model::set_popup_terms( $new_post, $prompt['categories'], 'category' );
			}
			if ( ! empty( $prompt['tags'] ) ) {
				Newspack_Popups_Model::set_popup_terms( $new_post, $prompt['tags'], 'post_tag' );
			}
		}
	}

	/**
	 * Pre-process prompt segments
	 *
	 * If there are segments set, replace the IDs with the IDs from the segment mapping
	 *
	 * @param array $prompts The prompts to process.
	 * @return array The processed prompts.
	 */
	private function pre_process_prompt_segments( $prompts ) {
		foreach ( $prompts as $prompt_index => $prompt ) {
			if ( ! empty( $prompt['options']['selected_segment_id'] ) ) {
				$segments = explode( ',', $prompt['options']['selected_segment_id'] );
				foreach ( $segments as $key => $segment ) {
					if ( isset( $this->segments_mapping[ $segment ] ) ) {
						$segments[ $key ] = $this->segments_mapping[ $segment ];
					} else {
						unset( $segments[ $key ] );
					}
				}
				$prompts[ $prompt_index ]['options']['selected_segment_id'] = implode( ',', $segments );
			}
		}
		return array_values( $prompts );
	}

	/**
	 * Pre process terms in prompts
	 *
	 * @TODO: We are not handling tags and categories yet. They need to be handled beforehand and we need to decide what to do with them.
	 * The best thing to do would be to let the user decide if they want to map them to an existing term or to create a new term.
	 * For now, we are dropping them from the input and not importing them.
	 *
	 * @param array $prompts The prompts from input.
	 * @return array The prompts with the terms pre processed.
	 */
	private function pre_process_prompts_terms( $prompts ) {
		foreach ( $prompts as $prompt_index => $prompt ) {

			if ( ! empty( $prompt['campaign_groups'] ) ) {
				$prompts[ $prompt_index ]['campaign_groups'] = $this->pre_process_terms( $prompt['campaign_groups'] );
			}
			if ( ! empty( $prompt['categories'] ) ) {
				$prompts[ $prompt_index ]['categories'] = $this->pre_process_terms( $prompt['categories'] );
			}
			if ( ! empty( $prompt['tags'] ) ) {
				$prompts[ $prompt_index ]['tags'] = $this->pre_process_terms( $prompt['tags'] );
			}
			if ( ! empty( $prompt['options']['excluded_categories'] ) ) {
				$prompts[ $prompt_index ]['options']['excluded_categories'] = $this->pre_process_terms( $prompt['options']['excluded_categories'] );
			}
			if ( ! empty( $prompt['options']['excluded_tags'] ) ) {
				$prompts[ $prompt_index ]['options']['excluded_tags'] = $this->pre_process_terms( $prompt['options']['excluded_tags'] );
			}
		}
		return array_values( $prompts );
	}

	/**
	 * Pre process terms
	 *
	 * Check if terms mapping were defined and update their ids, otherwise remove them from the array.
	 *
	 * @param array $terms The terms array in which each item is and array with id and name.
	 * @return array $terms The processed terms.
	 */
	private function pre_process_terms( $terms ) {
		foreach ( $terms as $term_index => $term ) {
			if ( isset( $this->terms_mapping[ $term['id'] ] ) ) {
				$terms[ $term_index ]['id'] = $this->terms_mapping[ $term['id'] ];
			} else {
				unset( $terms[ $term_index ] );
			}
		}
		return array_values( $terms );
	}


}
