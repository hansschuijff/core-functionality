<?php
/**
 * Get Content Ability.
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

use function __;

/**
 * Class GetContent
 *
 * Returns one item in full: content as block markup, excerpt, terms and extra fields, plus
 * whether an AI assistant may change it now.
 *
 * @since 1.0.0
 */
class GetContent extends AbstractContentAbility {

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.0.0
	 * @return string The slug.
	 */
	#[Override]
	protected function get_slug(): string {
		return 'get-content';
	}

	/**
	 * Returns the translated label.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	#[Override]
	protected function get_label(): string {
		return __( 'Get content', 'core-functionality' );
	}

	/**
	 * Returns the translated description.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	protected function get_description(): string {
		return __( 'Returns one item in full: content as block markup, excerpt, terms and extra fields, and whether it can be changed now.', 'core-functionality' );
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
			'required'             => array( 'id' ),
			'properties'           => array(
				'id' => $this->get_id_schema(),
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
	 * Returns the item.
	 *
	 * @since  1.0.0
	 * @param  array $input The input.
	 * @return array|WP_Error The item, or the reason why it cannot be read.
	 */
	#[Override]
	protected function run( array $input ): array|WP_Error {
		$post = $this->get_usable_post( (int) ( $input['id'] ?? 0 ) );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		// Editable now: drafts are switched on for the type and the item is in an editable status.
		return $this->services->reader->read( $post ) + array(
			'editable' => $this->services->types->can_write( $post->post_type ) && $this->services->writer->is_editable( $post ),
		);
	}
}
