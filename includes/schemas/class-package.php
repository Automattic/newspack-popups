<?php
/**
 * The Campaigns package Schema
 *
 * @package Newspack
 */

namespace Newspack\Campaigns\Schemas;

use \Newspack\Campaigns\Schema;

/**
 * The Campaigns Schema
 */
class Package extends Schema {

	/**
	 * Get the schema.
	 *
	 * @return array The schema.
	 */
	public function get_schema() {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'prompts'   => [
					'name'     => 'prompts',
					'type'     => 'array',
					'required' => true,
					'items'    => Prompts::get_schema(),
				],
				'segments'  => [
					'name'     => 'segments',
					'type'     => 'array',
					'required' => true,
					'items'    => Segments::get_schema(),
				],
				'campaigns' => [
					'name'     => 'campaigns',
					'type'     => 'array',
					'required' => true,
					'items'    => Campaigns::get_schema(),
				],

			],
		];
	}
}
