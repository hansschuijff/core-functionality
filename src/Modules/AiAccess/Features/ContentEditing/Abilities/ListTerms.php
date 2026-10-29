<?php
/**
 * List Terms Ability.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing\Abilities
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Override;
use WP_Error;
use WP_Term;

use function __;
use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function get_terms;
use function in_array;
use function is_array;

/**
 * Class ListTerms
 *
 * Lists the existing terms (categories, tags and other taxonomies of the content types), so an
 * AI assistant can choose them. It does not create terms.
 *
 * @since 1.0.0
 */
class ListTerms extends AbstractContentAbility {

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.0.0
	 * @return string The slug.
	 */
	#[Override]
	protected function get_slug(): string {
		return 'list-terms';
	}

	/**
	 * Returns the translated label.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	#[Override]
	protected function get_label(): string {
		return __( 'List terms', 'core-functionality' );
	}

	/**
	 * Returns the translated description.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	protected function get_description(): string {
		return __( 'Lists the existing categories, tags and other terms that can be given to content, with their ids. New terms are not created.', 'core-functionality' );
	}

	/**
	 * Returns the JSON schema of the input.
	 *
	 * @since  1.0.0
	 * @return array An object schema.
	 */
	#[Override]
	protected function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'taxonomy' => array_filter(
					array(
						'type'        => 'string',
						'enum'        => $this->get_taxonomies(),
						'description' => 'Only this taxonomy. Leave out for all of them.',
					)
				),
				'search'   => array(
					'type'        => 'string',
					'description' => 'Part of the name.',
				),
			),
		);
	}

	/**
	 * Returns whether the ability only reads.
	 *
	 * @since  1.0.0
	 * @return bool True.
	 */
	#[Override]
	protected function is_readonly(): bool {
		return true;
	}

	/**
	 * Returns the terms per taxonomy.
	 *
	 * @since  1.0.0
	 * @param  array $input The input.
	 * @return array|WP_Error Taxonomy => terms, and which content types use it.
	 */
	#[Override]
	protected function run( array $input ): array|WP_Error {
		$taxonomies = $this->get_taxonomies();
		$wanted     = (string) ( $input['taxonomy'] ?? '' );
		if ( '' !== $wanted ) {
			if ( ! in_array( $wanted, $taxonomies, true ) ) {
				return new WP_Error( 'cf_ai_taxonomy', __( 'This taxonomy is not available to an AI assistant.', 'core-functionality' ) );
			}
			$taxonomies = array( $wanted );
		}

		$result = array();
		foreach ( $taxonomies as $taxonomy ) {
			$args = array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 500,
			);
			if ( isset( $input['search'] ) ) {
				$args['search'] = (string) $input['search'];
			}

			$terms = get_terms( $args );

			$result[ $taxonomy ] = array();
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( $term instanceof WP_Term ) {
					$result[ $taxonomy ][] = $this->services->reader->describe_term( $term ) + array( 'parent' => $term->parent );
				}
			}
		}

		return array( 'terms' => (object) $result );
	}

	/**
	 * Returns the taxonomies of all content types that are switched on.
	 *
	 * @since  1.0.0
	 * @return string[] Taxonomy names.
	 */
	private function get_taxonomies(): array {
		$taxonomies = array();
		foreach ( $this->services->types->get_readable() as $profile ) {
			$taxonomies = array_merge( $taxonomies, $profile->get_taxonomies() );
		}

		return array_values( array_unique( $taxonomies ) );
	}
}
