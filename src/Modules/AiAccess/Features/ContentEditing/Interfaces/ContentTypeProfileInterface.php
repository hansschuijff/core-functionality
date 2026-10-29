<?php
/**
 * Content Type Profile Interface.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing\Interfaces
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Interface ContentTypeProfileInterface
 *
 * Describes one post type an AI assistant may read and write: which taxonomies it may use, which
 * blocks must stay in the content, and whether a Rewrite & Republish copy is safe for it.
 * Profiles are collected on the filter core-functionality/ai-access-content-types.
 *
 * @since 1.0.0
 */
interface ContentTypeProfileInterface {

	/**
	 * Returns the post type this profile describes.
	 *
	 * @since  1.0.0
	 * @return string The post type, for example 'post'.
	 */
	public function get_post_type(): string;

	/**
	 * Returns the taxonomies whose terms may be read and set.
	 *
	 * @since  1.0.0
	 * @return string[] Taxonomy names.
	 */
	public function get_taxonomies(): array;

	/**
	 * Returns the blocks that must stay in the content once they are there, because a plugin keeps
	 * its data or structure in them (an event's date block, a course outline).
	 *
	 * @since  1.0.0
	 * @return string[] Block names, for example 'sensei-lms/course-outline'.
	 */
	public function get_protected_blocks(): array;

	/**
	 * Returns whether a Rewrite & Republish copy is known to be safe for this post type, as the
	 * default of its setting.
	 *
	 * @since  1.0.0
	 * @return bool True when copying and merging back has been tested for this type.
	 */
	public function allows_rewrite_by_default(): bool;
}
