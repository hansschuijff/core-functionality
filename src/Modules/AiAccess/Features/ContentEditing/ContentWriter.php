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
use function wp_unslash;
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
	 * @param ContentTypes      $types        The content types that may be used.
	 * @param FieldHandlers     $handlers     The groups of extra fields.
	 * @param ContentReplacer   $replacer     Replaces pieces of text.
	 * @param UnsafeMarkupCheck $markup_check Finds unsafe markup that a change adds.
	 */
	public function __construct(
		private readonly ContentTypes $types,
		private readonly FieldHandlers $handlers,
		private readonly ContentReplacer $replacer,
		private readonly UnsafeMarkupCheck $markup_check,
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
		if ( null === $profile || ! $this->types->can_write( $post->post_type ) ) {
			return new WP_Error( 'cf_ai_type', __( 'An AI assistant may not make drafts of this content type.', 'core-functionality' ) );
		}

		if ( ! $this->is_editable( $post ) ) {
			return $this->not_editable_error( $post );
		}

		$terms = $this->validate_terms( $profile, $input['terms'] ?? array() );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$removed = $this->check_removed_markup( $input );
		if ( is_wp_error( $removed ) ) {
			return $removed;
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
	 * Refuses new content with markup that wp_kses_post() would remove, such as inline SVG, so it
	 * is not lost without anyone noticing.
	 *
	 * @since  1.1.3
	 * @param  array $input The input.
	 * @return true|WP_Error True, or the markup that would be removed.
	 */
	private function check_removed_markup( array $input ): true|WP_Error {
		if ( ! isset( $input['content'] ) ) {
			return true;
		}

		$unsafe = $this->markup_check->find_all( (string) $input['content'] );
		if ( array() === $unsafe ) {
			return true;
		}

		return new WP_Error(
			'cf_ai_removed_markup',
			/* translators: %s: the markup that would be removed, separated by commas. */
			sprintf( __( 'Nothing was changed. Saving this content would remove this markup: %s. To change existing content, use core-functionality/replace-in-draft; new markup of this kind is for the site owner to add.', 'core-functionality' ), implode( ', ', $unsafe ) )
		);
	}

	/**
	 * Replaces pieces of the content of a draft or a post pending review. All pieces apply or
	 * none does, and everything around them stays byte for byte the same. That is why the content
	 * does not go through wp_kses_post() here (it would remove inline SVG); check_replacement()
	 * refuses what that function would have refused in the new text.
	 *
	 * @since  1.1.3
	 * @param  WP_Post $post         The post.
	 * @param  array   $replacements List of arrays with old_text and new_text.
	 * @return array|WP_Error The post and warnings, or the reason why nothing was saved.
	 */
	public function replace_in_content( WP_Post $post, array $replacements ): array|WP_Error {
		$profile = $this->types->get( $post->post_type );
		if ( null === $profile || ! $this->types->can_write( $post->post_type ) ) {
			return new WP_Error( 'cf_ai_type', __( 'An AI assistant may not make drafts of this content type.', 'core-functionality' ) );
		}

		if ( ! $this->is_editable( $post ) ) {
			return $this->not_editable_error( $post );
		}

		$new_content = $this->replacer->replace( $post->post_content, $replacements );
		if ( is_wp_error( $new_content ) ) {
			return $new_content;
		}

		$refused = $this->check_replacement( $profile, $post->post_content, $new_content );
		if ( is_wp_error( $refused ) ) {
			return $refused;
		}

		$saved = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post->ID,
					'post_content' => $new_content,
				)
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return $this->add_stored_content_warning( $this->finish( $post->ID, array(), array() ), $new_content );
	}

	/**
	 * Refuses new content that loses a protected block, adds unsafe markup or would not survive
	 * saving as it is.
	 *
	 * @since  1.1.3
	 * @param  ContentTypeProfileInterface $profile     The profile of the post type.
	 * @param  string                      $old_content The current content.
	 * @param  string                      $new_content The new content.
	 * @return true|WP_Error True, or the reason why it is refused.
	 */
	private function check_replacement( ContentTypeProfileInterface $profile, string $old_content, string $new_content ): true|WP_Error {
		$missing = $this->check_protected_blocks( $profile, $old_content, $new_content );
		if ( is_wp_error( $missing ) ) {
			return $missing;
		}

		$added = $this->markup_check->find_added( $old_content, $new_content );
		if ( array() !== $added ) {
			return new WP_Error(
				'cf_ai_unsafe_markup',
				/* translators: %s: the markup that is refused, separated by commas. */
				sprintf( __( 'Nothing was changed. The new text adds markup an AI assistant may not add: %s. The site owner can add it.', 'core-functionality' ), implode( ', ', $added ) )
			);
		}

		if ( $this->apply_save_filters( $new_content ) !== $new_content ) {
			return new WP_Error(
				'cf_ai_save_filters',
				__( 'Nothing was changed. Saving would also change other parts of the content, for example because this user may not save unfiltered HTML.', 'core-functionality' )
			);
		}

		return true;
	}

	/**
	 * Returns the content as the filters of wp_insert_post() would save it.
	 *
	 * @since  1.1.3
	 * @param  string $content The content.
	 * @return string The content after the filters.
	 */
	private function apply_save_filters( string $content ): string {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own filter.
		$filtered = apply_filters( 'pre_post_content', wp_slash( $content ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own filter.
		return wp_unslash( apply_filters( 'content_save_pre', $filtered ) );
	}

	/**
	 * Adds a warning when the stored content is not what was meant to be saved, for example because
	 * a plugin changed it while saving. The revision of the save makes it possible to go back.
	 *
	 * @since  1.1.3
	 * @param  array|WP_Error $result  The post and warnings.
	 * @param  string         $content The content that was meant to be saved.
	 * @return array|WP_Error The post and warnings.
	 */
	private function add_stored_content_warning( array|WP_Error $result, string $content ): array|WP_Error {
		if ( is_wp_error( $result ) || $result['post']->post_content === $content ) {
			return $result;
		}

		$result['warnings'][] = __( 'The stored content differs from the result of the replacements: something changed it while saving. Check it with get-content.', 'core-functionality' );

		return $result;
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
