<?php
/**
 * Content Type Profile.
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
use Override;

/**
 * Class ContentTypeProfile
 *
 * A profile set up with plain values, enough for most post types. A post type that needs more
 * (its own checks) can bring its own class implementing ContentTypeProfileInterface.
 *
 * @since 1.0.0
 */
class ContentTypeProfile implements ContentTypeProfileInterface {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string   $post_type         The post type.
	 * @param string[] $taxonomies        Taxonomies whose terms may be read and set.
	 * @param string[] $protected_blocks  Blocks that must stay in the content once they are there.
	 * @param bool     $allows_rewrite    Whether a Rewrite & Republish copy is safe by default.
	 */
	public function __construct(
		private readonly string $post_type,
		private readonly array $taxonomies = array(),
		private readonly array $protected_blocks = array(),
		private readonly bool $allows_rewrite = false,
	) {}

	/**
	 * Returns the post type this profile describes.
	 *
	 * @since  1.0.0
	 * @return string The post type.
	 */
	#[Override]
	public function get_post_type(): string {
		return $this->post_type;
	}

	/**
	 * Returns the taxonomies whose terms may be read and set.
	 *
	 * @since  1.0.0
	 * @return string[] Taxonomy names.
	 */
	#[Override]
	public function get_taxonomies(): array {
		return $this->taxonomies;
	}

	/**
	 * Returns the blocks that must stay in the content once they are there.
	 *
	 * @since  1.0.0
	 * @return string[] Block names.
	 */
	#[Override]
	public function get_protected_blocks(): array {
		return $this->protected_blocks;
	}

	/**
	 * Returns whether a Rewrite & Republish copy is safe by default.
	 *
	 * @since  1.0.0
	 * @return bool True when it is.
	 */
	#[Override]
	public function allows_rewrite_by_default(): bool {
		return $this->allows_rewrite;
	}
}
