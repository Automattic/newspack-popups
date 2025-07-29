<?php
/**
 * Newspack Popups Contextual Content
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Popups Contextual Content class.
 */
final class Newspack_Popups_Contextual_Content {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
	}

	/**
	 * Register REST API endpoints.
	 */
	public function register_api_endpoints() {
		register_rest_route(
			'newspack-popups/v1',
			'/contextual-content',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_contextual_content' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'prompt_id'       => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'segment'         => [
						'required' => false,
						'type'     => ['string', 'null'],
					],
					'current_post_id' => [
						'required'          => false,
						'type'              => ['integer', 'null'],
						'sanitize_callback' => function( $value ) {
							return null === $value ? null : absint( $value );
						},
					],
					'content'         => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);
	}

	/**
	 * Get contextual content for a prompt.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response.
	 */
	public function get_contextual_content( $request ) {
		// Check if feature is enabled.
		if ( ! defined( 'NEWSPACK_ANTHROPIC_API_KEY' ) || empty( NEWSPACK_ANTHROPIC_API_KEY ) ) {
			return new \WP_Error( 'api_key_missing', 'Anthropic API key is not configured', [ 'status' => 503 ] );
		}

		// Check if feature flag is present in URL parameters or referrer.
		$has_feature_flag = false;
		
		// Check URL parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['newspack-contextual-prompt-content'] ) ) {
			$has_feature_flag = true;
		}
		
		// Check referrer URL for the feature flag.
		if ( ! $has_feature_flag && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer_params = parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_QUERY );
			if ( $referrer_params ) {
				parse_str( $referrer_params, $params );
				if ( isset( $params['newspack-contextual-prompt-content'] ) ) {
					$has_feature_flag = true;
				}
			}
		}
		
		if ( ! $has_feature_flag ) {
			return new \WP_Error( 'feature_disabled', 'Contextual prompt content feature is not enabled', [ 'status' => 403 ] );
		}

		$prompt_id       = $request->get_param( 'prompt_id' );
		$segment         = $request->get_param( 'segment' );
		$current_post_id = $request->get_param( 'current_post_id' );
		$original_content = $request->get_param( 'content' );

		// Get reader data from Newspack Reader_Data instead of JavaScript activities.
		$reader_data = [];
		if ( class_exists( '\Newspack\Reader_Data' ) && is_user_logged_in() ) {
			$reader_data = \Newspack\Reader_Data::get_data( get_current_user_id() );
		}

		// Get prompt post.
		$prompt_post = get_post( $prompt_id );
		if ( ! $prompt_post ) {
			return new \WP_Error( 'invalid_prompt', 'Prompt post not found with ID: ' . $prompt_id, [ 'status' => 404 ] );
		}
		if ( 'newspack_popups_cpt' !== $prompt_post->post_type ) {
			return new \WP_Error( 'invalid_prompt', 'Post ID ' . $prompt_id . ' is not a popup (type: ' . $prompt_post->post_type . ')', [ 'status' => 404 ] );
		}

		// Process reader data to extract insights.
		$processed_reader_data = $this->process_reader_data( $reader_data );

		// Get current page context.
		$current_context = $this->get_current_page_context( $current_post_id );

		// Generate contextual content.
		$contextual_content = $this->generate_contextual_content(
			$original_content,
			$segment,
			$processed_reader_data,
			$current_context,
			$prompt_id
		);

		if ( is_wp_error( $contextual_content ) ) {
			return $contextual_content;
		}

		return rest_ensure_response(
			[
				'content' => $contextual_content,
			] 
		);
	}

	/**
	 * Process reader data to extract meaningful insights.
	 *
	 * @param array $reader_data The reader data from Newspack Reader_Data::get_data().
	 * @return array Processed reader insights.
	 */
	private function process_reader_data( $reader_data ) {
		$insights = [
			'interests' => [],
			'behavior'  => [],
			'profile'   => [],
		];

		if ( empty( $reader_data ) ) {
			return $insights;
		}

		// Extract subscription status.
		if ( ! empty( $reader_data['is_newsletter_subscriber'] ) ) {
			$insights['profile']['newsletter_subscriber'] = true;
		}

		// Extract donation history.
		if ( ! empty( $reader_data['is_donor'] ) ) {
			$insights['profile']['is_donor'] = true;
		}

		if ( ! empty( $reader_data['is_former_donor'] ) ) {
			$insights['profile']['is_former_donor'] = true;
		}

		// Extract subscription/membership data.
		if ( ! empty( $reader_data['active_subscriptions'] ) ) {
			$subscriptions = is_string( $reader_data['active_subscriptions'] ) ? 
				json_decode( $reader_data['active_subscriptions'], true ) : 
				$reader_data['active_subscriptions'];
			$insights['profile']['has_subscriptions'] = ! empty( $subscriptions );
		}

		if ( ! empty( $reader_data['active_memberships'] ) ) {
			$memberships = is_string( $reader_data['active_memberships'] ) ? 
				json_decode( $reader_data['active_memberships'], true ) : 
				$reader_data['active_memberships'];
			$insights['profile']['has_memberships'] = ! empty( $memberships );
		}

		// Extract newsletter lists if available.
		if ( ! empty( $reader_data['newsletter_subscribed_lists'] ) ) {
			$lists = is_string( $reader_data['newsletter_subscribed_lists'] ) ? 
				json_decode( $reader_data['newsletter_subscribed_lists'], true ) : 
				$reader_data['newsletter_subscribed_lists'];
			$insights['profile']['newsletter_lists'] = $lists;
		}

		return $insights;
	}

	/**
	 * Get context for the current page with database caching.
	 *
	 * @param int $post_id The current post ID.
	 * @return array Current page context.
	 */
	private function get_current_page_context( $post_id ) {
		// Check database cache first (24 hour expiration).
		$cache_key = 'newspack_popup_page_context_' . $post_id;
		$cached_context = get_transient( $cache_key );
		
		if ( false !== $cached_context ) {
			return $cached_context;
		}

		$context = [];

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$context['title'] = get_the_title( $post );
				$context['type'] = $post->post_type;

				// Get categories.
				$categories = get_the_category( $post_id );
				if ( $categories ) {
					$context['categories'] = wp_list_pluck( $categories, 'name' );
				}

				// Get tags.
				$tags = get_the_tags( $post_id );
				if ( $tags ) {
					$context['tags'] = wp_list_pluck( $tags, 'name' );
				}

				// Get author.
				$author = get_the_author_meta( 'display_name', $post->post_author );
				if ( $author ) {
					$context['author'] = $author;
				}
			}
		}

		// Cache the result in database for 24 hours.
		set_transient( $cache_key, $context, 24 * HOUR_IN_SECONDS );

		return $context;
	}

	/**
	 * Generate contextual content using Anthropic API.
	 *
	 * @param string $original_content The original prompt content.
	 * @param string $segment The reader's segment.
	 * @param array  $reader_insights The processed reader insights.
	 * @param array  $current_context The current page context.
	 * @param int    $prompt_id The prompt ID for debugging.
	 * @return string|WP_Error The contextual content or error.
	 */
	private function generate_contextual_content( $original_content, $segment, $reader_insights, $current_context, $prompt_id = 0 ) {
		// Calculate target length constraint (250% of original).
		$original_text_content = wp_strip_all_tags( $original_content );
		$original_length = strlen( $original_text_content );
		$max_length = max( 50, intval( $original_length * 2.5 ) ); // Allow up to 250%, minimum 50 chars

		// Prepare the prompt for Anthropic.
		$system_prompt = 'You are a helpful assistant that personalizes newsletter prompt content. Rules:
1. Return ONLY the updated content with HTML structure preserved - no preambles or explanations
2. SIGNIFICANTLY enhance the original with meaningful personalization based on reader context
3. You can expand the content up to 2.5x the original length to add relevant context
4. Never mention specific article titles, author names, or reveal detailed tracking
5. Make natural, contextual references to reader interests and browsing context
6. Add value through personalization - don\'t just rephrase the same message
7. Preserve all HTML tags exactly as they appear in the original
8. Focus on ACTUAL personalization and contextualization, not just rewording
9. NEVER use placeholders like [Name], [Title], etc. - this is user-facing content
10. CRITICAL: Do NOT invent services, features, or content that doesn\'t exist. Examples of FORBIDDEN phrases: "curated updates", "fresh picks", "handpicked articles", "personalized content", "tailored recommendations", "custom selection" - these imply curation that doesn\'t exist
11. Only enhance the EXISTING message with contextual relevance - don\'t promise new functionality
12. If the original is about newsletters, keep it about newsletters. If it\'s about donations, keep it about donations. Don\'t change the fundamental purpose.';

		// Extract reading interests from reader insights.
		$interests = $this->extract_interests_from_reader_data( $reader_insights );

		$user_prompt = sprintf(
			'Transform this content into something truly personalized and contextual. You can expand up to %d characters to add meaningful context.

Original content: %s

Reader profile: %s

Current page context: %s

CRITICAL GUIDELINES:
- Keep the exact same message and purpose - just make it more contextually relevant
- DO NOT add new promises, features, or services that don\'t exist in the original
- DO NOT use phrases like "curated", "handpicked", "fresh picks", "tailored content" etc.
- If the original says "subscribe to our newsletter" - keep it about subscribing, don\'t invent curation
- If the original says "support us" - keep it about support, don\'t invent personalized content
- Only add context that makes the EXISTING message more relevant to this reader
- The personalization should feel like natural, contextual language - not new functionality',
			$max_length,
			$original_content,
			$this->format_interests_for_personalization( $interests ),
			$this->format_context_for_personalization( $current_context )
		);

		// Make API request to Anthropic.
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			[
				'headers' => [
					'x-api-key'         => NEWSPACK_ANTHROPIC_API_KEY,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				],
				'body'    => wp_json_encode(
					[
						'model'      => 'claude-3-haiku-20240307',
						'max_tokens' => 1000,
						'system'     => $system_prompt,
						'messages'   => [
							[
								'role'    => 'user',
								'content' => $user_prompt,
							],
						],
					] 
				),
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new \WP_Error( 'api_error', 'Failed to get response from Anthropic API', [ 'status' => $response_code ] );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['content'][0]['text'] ) ) {
			return new \WP_Error( 'invalid_response', 'Invalid response from Anthropic API', [ 'status' => 500 ] );
		}

		$generated_content = $data['content'][0]['text'];
		
		// Debug: Log the API response for troubleshooting.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[Contextual Content] API Response for prompt %d: Original length: %d, Generated length: %d, Content: %s',
				$prompt_id,
				strlen( wp_strip_all_tags( $original_content ) ),
				strlen( wp_strip_all_tags( $generated_content ) ),
				substr( $generated_content, 0, 200 )
			) );
		}

		return $generated_content;
	}

	/**
	 * Extract reader interests from reader data insights.
	 *
	 * @param array $reader_insights The processed reader insights.
	 * @return array Extracted interests for personalization.
	 */
	private function extract_interests_from_reader_data( $reader_insights ) {
		$interests = [
			'categories' => [],
			'tags'       => [],
			'authors'    => [],
			'profile'    => [],
		];

		// Extract profile information for personalization.
		if ( ! empty( $reader_insights['profile'] ) ) {
			$profile = $reader_insights['profile'];
			
			if ( ! empty( $profile['newsletter_subscriber'] ) ) {
				$interests['profile'][] = 'newsletter_subscriber';
			}
			
			if ( ! empty( $profile['is_donor'] ) ) {
				$interests['profile'][] = 'current_donor';
			} elseif ( ! empty( $profile['is_former_donor'] ) ) {
				$interests['profile'][] = 'former_donor';
			}
			
			if ( ! empty( $profile['has_subscriptions'] ) ) {
				$interests['profile'][] = 'subscriber';
			}
			
			if ( ! empty( $profile['has_memberships'] ) ) {
				$interests['profile'][] = 'member';
			}
		}

		// For now, we don't have historical reading data in Reader_Data format.
		// This is a simplified version focused on user profile data.
		// Future enhancement could include reading history analysis.

		return $interests;
	}

	/**
	 * Format interests for meaningful personalization.
	 *
	 * @param array $interests The extracted interests.
	 * @return string Formatted interests.
	 */
	private function format_interests_for_personalization( $interests ) {
		$interest_parts = [];

		// Format profile information.
		if ( ! empty( $interests['profile'] ) ) {
			$profile_traits = [];
			foreach ( $interests['profile'] as $trait ) {
				switch ( $trait ) {
					case 'newsletter_subscriber':
						$profile_traits[] = 'newsletter subscriber';
						break;
					case 'current_donor':
						$profile_traits[] = 'supporter';
						break;
					case 'former_donor':
						$profile_traits[] = 'former supporter';
						break;
					case 'subscriber':
						$profile_traits[] = 'paid subscriber';
						break;
					case 'member':
						$profile_traits[] = 'member';
						break;
				}
			}
			if ( ! empty( $profile_traits ) ) {
				$interest_parts[] = 'Reader profile: ' . implode( ', ', $profile_traits );
			}
		}

		// Future: Add reading history when available.
		if ( ! empty( $interests['categories'] ) ) {
			$categories = array_slice( $interests['categories'], 0, 3 );
			$interest_parts[] = 'Categories: ' . implode( ', ', $categories );
		}

		if ( ! empty( $interests['tags'] ) ) {
			$tags = array_slice( $interests['tags'], 0, 3 );
			$interest_parts[] = 'Topics: ' . implode( ', ', $tags );
		}

		if ( ! empty( $interests['authors'] ) ) {
			$authors = array_slice( $interests['authors'], 0, 2 );
			$interest_parts[] = 'Favorite authors: ' . implode( ', ', $authors );
		}

		return implode( '. ', array_filter( $interest_parts ) ) ?: 'Regular reader';
	}

	/**
	 * Format current context for meaningful personalization.
	 *
	 * @param array $current_context The current page context.
	 * @return string Formatted context.
	 */
	private function format_context_for_personalization( $current_context ) {
		$context_parts = [];

		if ( ! empty( $current_context['title'] ) ) {
			$context_parts[] = 'Reading: ' . $current_context['title'];
		}

		if ( ! empty( $current_context['categories'] ) ) {
			$context_parts[] = 'Categories: ' . implode( ', ', $current_context['categories'] );
		}

		if ( ! empty( $current_context['tags'] ) ) {
			$context_parts[] = 'Tags: ' . implode( ', ', $current_context['tags'] );
		}

		if ( ! empty( $current_context['author'] ) ) {
			$context_parts[] = 'Author: ' . $current_context['author'];
		}

		return implode( '. ', array_filter( $context_parts ) ) ?: 'General article context';
	}
}

new Newspack_Popups_Contextual_Content();

