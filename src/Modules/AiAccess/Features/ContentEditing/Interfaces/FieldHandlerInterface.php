<?php
/**
 * Field Handler Interface.
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

use WP_Error;
use WP_Post;

/**
 * Interface FieldHandlerInterface
 *
 * A group of extra fields on top of title, content, excerpt and terms, such as SEO fields or the
 * dates of an event. It shows up under its id in "fields" when reading and writing. Handlers are
 * collected on the filter core-functionality/ai-access-field-handlers.
 *
 * @since 1.0.0
 */
interface FieldHandlerInterface {

	/**
	 * Returns the key of this group under "fields".
	 *
	 * @since  1.0.0
	 * @return string The id, for example 'seo'.
	 */
	public function get_id(): string;

	/**
	 * Returns whether this group belongs to a post type.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return bool True when the fields apply.
	 */
	public function applies_to( string $post_type ): bool;

	/**
	 * Returns the JSON schema of the values this group accepts.
	 *
	 * @since  1.0.0
	 * @return array An object schema.
	 */
	public function get_input_schema(): array;

	/**
	 * Returns the current values of a post.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The post.
	 * @return array The values.
	 */
	public function read( WP_Post $post ): array;

	/**
	 * Saves the given values on a post. Keys that are left out stay as they are.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post   The post, already saved.
	 * @param  array   $values The values from the input.
	 * @return true|WP_Error True, or the reason why it failed.
	 */
	public function write( WP_Post $post, array $values ): true|WP_Error;
}
