<?php
/**
 * Content Reader of the AI Access abilities.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Post;
use WP_Term;

use function apply_filters;
use function get_edit_post_link;
use function get_permalink;
use function get_the_terms;
use function is_array;

/**
 * Class ContentReader
 *
 * Turns a post into the data the abilities return: a short summary for lists, or everything an
 * AI assistant needs to continue working on it.
 *
 * @since 1.0.0
 */
class ContentReader {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ContentTypes  $types    The content types that may be used.
	 * @param FieldHandlers $handlers The groups of extra fields.
	 */
	public function __construct(
		private readonly ContentTypes $types,
		private readonly FieldHandlers $handlers,
	) {}

	/**
	 * Returns the summary of a post, as shown in search results.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The post.
	 * @return array Id, type, status, title, dates and links.
	 */
	public function summarize( WP_Post $post ): array {
		$summary = array(
			'id'        => $post->ID,
			'type'      => $post->post_type,
			'status'    => $post->post_status,
			'title'     => $post->post_title,
			'date'      => $post->post_date,
			'modified'  => $post->post_modified,
			'link'      => (string) get_permalink( $post ),
			'edit_link' => (string) get_edit_post_link( $post, 'raw' ),
		);

		/**
		 * Filters the summary of a post that the AI Access abilities return.
		 *
		 * @since 1.0.0
		 * @param array   $summary The summary.
		 * @param WP_Post $post    The post.
		 */
		$filtered = apply_filters( 'core-functionality/ai-access-content-summary', $summary, $post );

		return is_array( $filtered ) ? $filtered : $summary;
	}

	/**
	 * Returns everything about a post: the summary plus content, excerpt, terms and extra fields.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The post.
	 * @return array The data.
	 */
	public function read( WP_Post $post ): array {
		$fields = array();
		foreach ( $this->handlers->get_for( $post->post_type ) as $id => $handler ) {
			$fields[ $id ] = $handler->read( $post );
		}

		return $this->summarize( $post ) + array(
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'terms'   => $this->read_terms( $post ),
			'fields'  => (object) $fields,
		);
	}

	/**
	 * Returns the terms of a post in the taxonomies its profile allows.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The post.
	 * @return object Taxonomy => list of id, name and slug.
	 */
	private function read_terms( WP_Post $post ): object {
		$profile = $this->types->get( $post->post_type );
		$terms   = array();

		foreach ( null !== $profile ? $profile->get_taxonomies() : array() as $taxonomy ) {
			$found = get_the_terms( $post, $taxonomy );

			$terms[ $taxonomy ] = array();
			foreach ( is_array( $found ) ? $found : array() as $term ) {
				$terms[ $taxonomy ][] = $this->describe_term( $term );
			}
		}

		return (object) $terms;
	}

	/**
	 * Returns the id, name and slug of a term.
	 *
	 * @since  1.0.0
	 * @param  WP_Term $term The term.
	 * @return array The description.
	 */
	public function describe_term( WP_Term $term ): array {
		return array(
			'id'   => $term->term_id,
			'name' => $term->name,
			'slug' => $term->slug,
		);
	}
}
