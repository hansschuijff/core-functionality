<?php
/**
 * Find Content Ability.
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
use WP_Post;
use WP_Query;

use function __;
use function current_user_can;
use function in_array;
use function sanitize_text_field;

/**
 * Class FindContent
 *
 * Searches the content an AI assistant may use, in every status, and returns short summaries.
 *
 * @since 1.0.0
 */
class FindContent extends AbstractContentAbility {

	/**
	 * The statuses that can be searched for.
	 *
	 * @var string[]
	 */
	private const STATUSES = array( 'any', 'draft', 'pending', 'publish', 'future', 'private' );

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.0.0
	 * @return string The slug.
	 */
	#[Override]
	protected function get_slug(): string {
		return 'find-content';
	}

	/**
	 * Returns the translated label.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	#[Override]
	protected function get_label(): string {
		return __( 'Find content', 'core-functionality' );
	}

	/**
	 * Returns the translated description.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	protected function get_description(): string {
		return __( 'Searches posts, pages and other content an AI assistant may work on, by type, status, text and term. Returns id, title, status, dates and links; use get-content for the full item.', 'core-functionality' );
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
				'post_type' => $this->get_post_type_schema( 'The content type. Leave out for all types.' ),
				'status'    => array(
					'type'    => 'string',
					'enum'    => self::STATUSES,
					'default' => 'any',
				),
				'search'    => array(
					'type'        => 'string',
					'description' => 'Words to look for in title and content.',
				),
				'term'      => array(
					'type'        => 'integer',
					'description' => 'Only items with this term id (category, tag or another taxonomy of the type); also give taxonomy.',
				),
				'taxonomy'  => array(
					'type'        => 'string',
					'description' => 'The taxonomy of term, for example category.',
				),
				'per_page'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 50,
					'default' => 20,
				),
				'page'      => array(
					'type'    => 'integer',
					'minimum' => 1,
					'default' => 1,
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
	 * Searches and returns the summaries the current user may edit.
	 *
	 * @since  1.0.0
	 * @param  array $input The input.
	 * @return array|WP_Error The items and the total.
	 */
	#[Override]
	protected function run( array $input ): array|WP_Error {
		$types     = $this->services->types->get_readable_post_types();
		$post_type = (string) ( $input['post_type'] ?? '' );
		if ( '' !== $post_type && ! in_array( $post_type, $types, true ) ) {
			return new WP_Error( 'cf_ai_type', __( 'This content type is not available to an AI assistant.', 'core-functionality' ) );
		}

		$status = (string) ( $input['status'] ?? 'any' );
		$args   = array(
			'post_type'      => '' !== $post_type ? $post_type : $types,
			'post_status'    => in_array( $status, self::STATUSES, true ) && 'any' !== $status ? $status : array( 'draft', 'pending', 'publish', 'future', 'private' ),
			'posts_per_page' => (int) ( $input['per_page'] ?? 20 ),
			'paged'          => (int) ( $input['page'] ?? 1 ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( isset( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $input['search'] );
		}
		if ( isset( $input['term'], $input['taxonomy'] ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One term filter, asked for on purpose.
				array(
					'taxonomy' => sanitize_text_field( (string) $input['taxonomy'] ),
					'terms'    => (int) $input['term'],
				),
			);
		}

		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = $this->services->reader->summarize( $post );
			}
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}
}
