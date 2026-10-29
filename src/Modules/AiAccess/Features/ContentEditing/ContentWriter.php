<?php
/**
 * Content Writer of the AI Access abilities.
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

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\ContentTypeProfileInterface;
use WP_Error;
use WP_Post;

use function __;
use function apply_filters;
use function array_map;
use function get_current_user_id;
use function get_post;
use function has_block;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function is_wp_error;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sprintf;
use function term_exists;
use function wp_insert_post;
use function wp_kses_post;
use function wp_set_object_terms;
use function wp_slash;
use function wp_update_post;

/**
 * Class ContentWriter
 *
 * Saves what an AI assistant writes, within one fixed rule: it never publishes, schedules,
 * makes private, trashes or deletes. A new post is always a draft; an existing post can only be
 * changed while it is a draft or pending review (a Rewrite & Republish copy is a draft too).
 *
 * @since 1.0.0
 */
class ContentWriter {

	/**
	 * The statuses in which an AI assistant may change a post.
	 *
	 * @var string[]
	 */
	private const EDITABLE_STATUSES = array( 'draft', 'pending' );

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
	 * Returns whether a post is in a status an AI assistant may change.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The post.
	 * @return bool True when it may be changed.
	 */
	public function is_editable( WP_Post $post ): bool {
		/**
		 * Filters the statuses in which an AI assistant may change a post. Publishing statuses
		 * are always left out, whatever this filter returns.
		 *
		 * @since 1.0.0
		 * @param string[] $statuses The statuses.
		 * @param WP_Post  $post     The post.
		 */
		$statuses = apply_filters( 'core-functionality/ai-access-editable-statuses', self::EDITABLE_STATUSES, $post );
		$statuses = is_array( $statuses ) ? $statuses : self::EDITABLE_STATUSES;

		$never = array( 'publish', 'future', 'private', 'trash' );

		return in_array( $post->post_status, $statuses, true ) && ! in_array( $post->post_status, $never, true );
	}

	/**
	 * Creates a new draft.
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface $profile The profile of the post type.
	 * @param  array                       $input   Title, content, excerpt, terms and fields.
	 * @return array|WP_Error The post and warnings, or the reason why nothing was saved.
	 */
	public function create( ContentTypeProfileInterface $profile, array $input ): array|WP_Error {
		$terms = $this->validate_terms( $profile, $input['terms'] ?? array() );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $profile->get_post_type(),
					'post_status'  => 'draft',
					'post_author'  => get_current_user_id(),
					'post_title'   => sanitize_text_field( (string) ( $input['title'] ?? '' ) ),
					'post_content' => wp_kses_post( (string) ( $input['content'] ?? '' ) ),
					'post_excerpt' => sanitize_textarea_field( (string) ( $input['excerpt'] ?? '' ) ),
				)
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return $this->finish( $post_id, $terms, $input['fields'] ?? array() );
	}

	/**
	 * Changes a draft or a post pending review. Only the keys that are given change.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post  The post.
	 * @param  array   $input Title, content, excerpt, terms and fields.
	 * @return array|WP_Error The post and warnings, or the reason why nothing was saved.
	 */
	public function update( WP_Post $post, array $input ): array|WP_Error {
		$profile = $this->types->get( $post->post_type );
		if ( null === $profile ) {
			return new WP_Error( 'cf_ai_type', __( 'This content type is not available to an AI assistant.', 'core-functionality' ) );
		}

		if ( ! $this->is_editable( $post ) ) {
			return $this->not_editable_error( $post );
		}

		$terms = $this->validate_terms( $profile, $input['terms'] ?? array() );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$postarr = $this->get_changed_fields( $input );
		if ( isset( $postarr['post_content'] ) ) {
			$missing = $this->check_protected_blocks( $profile, $post->post_content, $postarr['post_content'] );
			if ( is_wp_error( $missing ) ) {
				return $missing;
			}
		}

		if ( array() !== $postarr ) {
			$result = wp_update_post( wp_slash( array( 'ID' => $post->ID ) + $postarr ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $this->finish( $post->ID, $terms, $input['fields'] ?? array() );
	}

	/**
	 * Puts a draft up for review, so the site owner sees it is ready.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The post.
	 * @return array|WP_Error The post and warnings, or the reason why it failed.
	 */
	public function submit_for_review( WP_Post $post ): array|WP_Error {
		if ( 'draft' !== $post->post_status ) {
			return new WP_Error(
				'cf_ai_status',
				/* translators: %s: the current post status. */
				sprintf( __( 'Only a draft can be submitted for review; this one has the status "%s".', 'core-functionality' ), $post->post_status )
			);
		}

		$result = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'pending',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->finish( $post->ID, array(), array() );
	}

	/**
	 * Returns the error for a post that may not be changed, with what to do instead.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The post.
	 * @return WP_Error The error.
	 */
	public function not_editable_error( WP_Post $post ): WP_Error {
		$hint = $this->types->allows_rewrite( $post->post_type )
			? __( 'Use core-functionality/start-rewrite to work in a copy that replaces the original once it is published.', 'core-functionality' )
			: __( 'Ask the site owner to set it back to draft.', 'core-functionality' );

		return new WP_Error(
			'cf_ai_not_editable',
			/* translators: 1: the post status, 2: what to do instead. */
			sprintf( __( 'This item has the status "%1$s" and cannot be changed by an AI assistant. %2$s', 'core-functionality' ), $post->post_status, $hint )
		);
	}

	/**
	 * Returns the post fields from the input that are given, cleaned up.
	 *
	 * @since  1.0.0
	 * @param  array $input The input.
	 * @return array Post fields for wp_update_post().
	 */
	private function get_changed_fields( array $input ): array {
		$postarr = array();

		if ( isset( $input['title'] ) ) {
			$postarr['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$postarr['post_content'] = wp_kses_post( (string) $input['content'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_textarea_field( (string) $input['excerpt'] );
		}

		return $postarr;
	}

	/**
	 * Refuses new content that drops a block a plugin keeps its data or structure in.
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface $profile     The profile of the post type.
	 * @param  string                      $old_content The current content.
	 * @param  string                      $new_content The new content.
	 * @return true|WP_Error True, or the blocks that would be lost.
	 */
	private function check_protected_blocks( ContentTypeProfileInterface $profile, string $old_content, string $new_content ): true|WP_Error {
		$missing = array();
		foreach ( $profile->get_protected_blocks() as $block ) {
			if ( has_block( $block, $old_content ) && ! has_block( $block, $new_content ) ) {
				$missing[] = $block;
			}
		}

		if ( array() === $missing ) {
			return true;
		}

		return new WP_Error(
			'cf_ai_protected_blocks',
			/* translators: %s: block names, separated by commas. */
			sprintf( __( 'The new content leaves out blocks that must stay: %s. Keep them as they are.', 'core-functionality' ), implode( ', ', $missing ) )
		);
	}

	/**
	 * Checks the terms from the input: only allowed taxonomies and existing terms.
	 *
	 * @since  1.0.0
	 * @param  ContentTypeProfileInterface $profile The profile of the post type.
	 * @param  mixed                       $terms   Taxonomy => list of term ids.
	 * @return array|WP_Error Taxonomy => term ids, or the reason why they are refused.
	 */
	private function validate_terms( ContentTypeProfileInterface $profile, mixed $terms ): array|WP_Error {
		$valid = array();

		foreach ( is_array( $terms ) ? $terms : array() as $taxonomy => $ids ) {
			if ( ! is_string( $taxonomy ) || ! in_array( $taxonomy, $profile->get_taxonomies(), true ) ) {
				return new WP_Error(
					'cf_ai_taxonomy',
					/* translators: %s: taxonomy name. */
					sprintf( __( 'The taxonomy "%s" cannot be used for this content type.', 'core-functionality' ), (string) $taxonomy )
				);
			}

			$ids = array_map( 'intval', is_array( $ids ) ? $ids : array() );
			foreach ( $ids as $id ) {
				if ( ! term_exists( $id, $taxonomy ) ) {
					return new WP_Error(
						'cf_ai_term',
						/* translators: 1: term id, 2: taxonomy name. */
						sprintf( __( 'There is no term %1$d in "%2$s". Use core-functionality/list-terms to find existing terms.', 'core-functionality' ), $id, $taxonomy )
					);
				}
			}

			$valid[ $taxonomy ] = $ids;
		}

		return $valid;
	}

	/**
	 * Saves the terms and extra fields of a saved post and returns it with any warnings.
	 *
	 * @since  1.0.0
	 * @param  int   $post_id The post id.
	 * @param  array $terms   Taxonomy => term ids, already checked.
	 * @param  mixed $fields  Group id => values.
	 * @return array|WP_Error The post and warnings.
	 */
	private function finish( int $post_id, array $terms, mixed $fields ): array|WP_Error {
		foreach ( $terms as $taxonomy => $ids ) {
			wp_set_object_terms( $post_id, $ids, $taxonomy );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'cf_ai_missing', __( 'The item could not be found after saving.', 'core-functionality' ) );
		}

		$warnings = array();
		$handlers = $this->handlers->get_for( $post->post_type );
		foreach ( is_array( $fields ) ? $fields : array() as $id => $values ) {
			if ( ! isset( $handlers[ $id ] ) || ! is_array( $values ) ) {
				/* translators: %s: the id of a group of fields. */
				$warnings[] = sprintf( __( 'The fields "%s" do not apply to this content type and were ignored.', 'core-functionality' ), (string) $id );
				continue;
			}

			$result = $handlers[ $id ]->write( $post, $values );
			if ( is_wp_error( $result ) ) {
				$warnings[] = $result->get_error_message();
			}
		}

		return array(
			'post'     => get_post( $post_id ),
			'warnings' => $warnings,
		);
	}
}
