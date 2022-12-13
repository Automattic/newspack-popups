<?php
/**
 * Campaigns Import CLI command.
 *
 * @package Newspack
 */

namespace Newspack\Campaigns\CLI;

use \WP_CLI;
use \WP_CLI_Command;
use \WP_Error;
use Newspack_Popups_Importer;

/**
 * Campaigns Import CLI command.
 */
class Import extends WP_CLI_Command {

	/**
	 * Import Campaigns, Segments and Prompts.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The name of the input file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-popups import newspack-campaigns.json
	 *
	 * @param array $args Args.
	 * @param array $assoc_args Assoc args.
	 */
	public function __invoke( $args, $assoc_args ) {

		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( 'File not found or not readable.' );
		}

		$data = wp_json_file_decode( $file, [ 'associative' => true ] ); // phpcs:ignore
		if ( empty( $data ) ) {
			WP_CLI::error( 'File is not valid JSON.' );
		}

		$importer = new Newspack_Popups_Importer( $data );

		$missing_terms = $importer->get_missing_terms_from_input();

		if ( ! empty( $missing_terms ) ) {
			foreach ( $missing_terms as $tax => $terms ) {
				foreach ( $terms as $term ) {
					$done = false;
					while ( ! $done ) {
						$question = sprintf(
							// translators: %1$s is the name of the term, %2$s is the taxonomy (category or tag).
							__( 'The "%1$s" %2$s does not exist in this site. How do you want to proceed?', 'newspack-popups' ),
							$term['name'],
							$this->tax_label( $tax )
						);
						WP_CLI::warning( $question );
						$strategy = $this->choose_term_strategy();
						if ( 3 === $strategy ) {
							WP_CLI::success( __( 'Ignoring', 'newspack-popups' ) );
							$done = true;
						} elseif ( 2 == $strategy ) {
							$new_id = $this->choose_existing_term( $tax );
							if ( ! is_wp_error( $new_id ) ) {
								WP_CLI::success( __( 'Term selected', 'newspack-popups' ) );
								$importer->add_term_mapping( $term['id'], $new_id );
								$done = true;
							}
							// else means the user aborted the selection. Dont set done and try again.
						} elseif ( 1 == $strategy ) {
							$new_id = $this->create_term( $term['name'], $tax );
							if ( is_wp_error( $new_id ) ) {
								WP_CLI::error( $new_id->get_error_message(), false );
								// Dont set done and try again.
							} else {
								WP_CLI::success( __( 'Term created', 'newspack-popups' ) );
								$done = true;
							}
						}
					}
				}
			}
		}

		$result = $importer->import();

		if ( ! empty( $result['errors'] ) && ! empty( $result['errors']['validation'] ) ) {
			WP_CLI::warning( 'JSON could not be validated.' );
			foreach ( $result['errors']['validation'] as $message ) {
				WP_CLI::warning( $message );
			}
			WP_CLI::error( 'Could not import JSON file.' );
		}

		foreach ( $result['errors'] as $error_type => $error_group ) {
			if ( empty( $error_group ) ) {
				continue;
			}
			WP_CLI::warning( $error_type );
			foreach ( $error_group as $error_id => $error ) {
				WP_CLI::warning( '-- ' . $error_id );
				foreach ( $error as $message ) {
					WP_CLI::warning( '---- ' . $message );
				}
			}
		}

		WP_CLI::success( $result['totals']['campaigns'] . ' campaigns imported.' );
		WP_CLI::success( $result['totals']['segments'] . ' segments imported.' );
		WP_CLI::success( $result['totals']['prompts'] . ' prompts imported.' );
		WP_CLI::success( 'Import complete!' );

	}

	/**
	 * Gets a taxonomy label.
	 *
	 * @param string $tax Taxonomy slug.
	 * @return string
	 */
	private function tax_label( $tax ) {
		$labels = [
			'category' => __( 'category', 'newspack-popups' ),
			'post_tag' => __( 'tag', 'newspack-popups' ),
		];
		return $labels[ $tax ];
	}

	/**
	 * Prompts the user to choose the strategy to handle a missing term.
	 *
	 * @return int The user's choice.
	 */
	private function choose_term_strategy() {
		WP_CLI::line( __( '1) Create it', 'newspack-popups' ) );
		WP_CLI::line( __( '2) Choose an existing one', 'newspack-popups' ) );
		WP_CLI::line( __( '3) Ignore', 'newspack-popups' ) );
		$prompt  = __( 'Enter your choice (1, 2 or 3):', 'newspack-popups' ) . ' ';
		$choices = [ 1, 2, 3 ];
		$choice  = 0;
		while ( ! in_array( $choice, $choices, true ) ) {
			$choice = (int) readline( $prompt );
			if ( ! in_array( $choice, $choices, true ) ) {
				WP_CLI::warning( __( 'Invalid choice. Please try again.', 'newspack-popups' ) );
			}
		}
		return $choice;
	}

	/**
	 * Prompts the user to choose an existing term
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return int|WP_Error The term ID, or a WP_Error object if the process is cancelled.
	 */
	private function choose_existing_term( $taxonomy ) {
		// translators: %s is the taxonomy (category or tag).
		$prompt  = sprintf( __( 'Enter the existing %s name you want to use (or "c" to cancel):', 'newspack-popups' ) . ' ', $this->tax_label( $taxonomy ) );
		$term_id = false;
		while ( ! $term_id ) {
			$term_name = readline( $prompt );

			if ( 'c' === strtolower( $term_name ) ) {
				return new WP_Error( 'cancelled' );
			}

			$search = get_term_by( 'name', $term_name, $taxonomy );
			if ( is_a( $search, 'WP_Term' ) ) {
				$term_id = $search->term_id;
			} else {
				WP_CLI::warning( __( 'No term found with that name. Please try again.', 'newspack-popups' ) );
			}
		}
		return $term_id;
	}

	/**
	 * Create a term.
	 *
	 * @param string $name The term name.
	 * @param string $taxonomy The term taxonomy.
	 * @return int|WP_Error The term ID or a WP_Error object.
	 */
	private function create_term( $name, $taxonomy ) {
		$created = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $created ) ) {
			if ( 'term_exists' === $created->get_error_code() ) {
				// translators: %s is the taxonomy (category or tag).
				WP_CLI::warning( sprintf( __( 'This %s already exists. Using it instead.', 'newspack-popups' ), $this->tax_label( $tax ) ) );
				return $created->get_error_data();
			} else {
				return $created;
			}
		}
		return $created['term_id'];
	}
}
