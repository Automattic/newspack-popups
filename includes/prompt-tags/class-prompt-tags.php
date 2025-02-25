<?php
/**
 * Newspack Popups Prompt Tags
 *
 * @package Newspack
 */

namespace Newspack\Campaigns;

defined( 'ABSPATH' ) || exit;

/**
 * Prompt Tags class.
 */
class Prompt_Tags {
	/**
	 * Registered prompt tags.
	 *
	 * @var Prompt_Tag[]
	 */
	protected static $tags = [];

	/**
	 * Initialize hooks.
	 */
	public static function init_hooks() {
		if (
			( ! defined( 'IS_TEST_ENV' ) || ! IS_TEST_ENV ) ||
			( ! defined( 'NEWSPACK_PROMPT_TAGS' ) || ! NEWSPACK_PROMPT_TAGS )
		) {
			return;
		}
		add_action( 'init', [ __CLASS__, 'register_default_tags' ] );
		add_filter( 'newspack_popups_popup_content', [ __CLASS__, 'parse_tags' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ], 11 );
	}

	/**
	 * Registers default prompt tags.
	 *
	 * @return void
	 */
	public static function register_default_tags() {
		self::register_tag(
			'site_name',
			[
				'title'       => __( 'Site Name', 'newspack-popups' ),
				'description' => __( 'The name of the site.', 'newspack-popups' ),
				'callback'    => function() {
					return get_bloginfo( 'name' );
				},
			]
		);
		self::register_tag(
			'site_description',
			[
				'title'       => __( 'Site Description', 'newspack-popups' ),
				'description' => __( 'The description of the site.', 'newspack-popups' ),
				'callback'    => function() {
					return get_bloginfo( 'description' );
				},
			]
		);
		self::register_tag(
			'articles_read',
			[
				'title'       => __( 'Articles Read', 'newspack-popups' ),
				'description' => __( 'Number of articles read in the last 30 day period.', 'newspack-popups' ),
				'criteria'    => 'articles_read',
			]
		);
		self::register_tag(
			'articles_read_in_session',
			[
				'title'       => __( 'Articles Read in Session', 'newspack-popups' ),
				'description' => __( 'Number of articles recently read before 30 minutes of inactivity.', 'newspack-popups' ),
				'criteria'    => 'articles_read_in_session',
			]
		);
	}

	/**
	 * Registers a prompt tag.
	 *
	 * @param string $tag  Tag name.
	 * @param array  $args Tag arguments.
	 */
	public static function register_tag( $tag, $args = [] ) {
		self::$tags[ $tag ] = new Prompt_Tag( $tag, $args );
	}

	/**
	 * Parses prompt tags in a string.
	 *
	 * @param string $string String to parse.
	 */
	public static function parse_tags( $string ) {
		$tags = self::$tags;

		$pattern = '/\{(' . implode( '|', array_keys( $tags ) ) . ')\}/';
		preg_match_all( $pattern, $string, $matches );

		foreach ( $matches[1] as $match ) {
			$tag = $tags[ $match ];
			$string = str_replace( '{' . $match . '}', $tag->get_content(), $string );
		}

		return $string;
	}

	/**
	 * Enqueue block editor assets.
	 */
	public static function enqueue_block_editor_assets() {
		$tags = array_values( self::$tags );
		wp_localize_script(
			'newspack-popups',
			'newspack_popups_prompt_tags',
			[
				'tags' => array_map(
					function( $tag ) {
						return $tag->to_array();
					},
					$tags
				),
			]
		);
	}
}
Prompt_Tags::init_hooks();
