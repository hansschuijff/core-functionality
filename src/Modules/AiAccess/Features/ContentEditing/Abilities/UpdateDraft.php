<?php
/**
 * Update Draft Ability.
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
 * Class UpdateDraft
 *
 * Changes a draft, an item pending review or a Rewrite & Republish copy. Published items are
 * refused, with a hint to work in a copy where that is allowed.
 *
 * @since 1.0.0
 */
class UpdateDraft extends AbstractContentAbility {

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.0.0
	 * @return string The slug.
	 */
	#[Override]
	protected function get_slug(): string {
		return 'update-draft';
	}

	/**
	 * Returns the translated label.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	#[Override]
	protected function get_label(): string {
		return __( 'Update draft', 'core-functionality' );
	}

	/**
	 * Returns the translated description.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	protected function get_description(): string {
		return __( 'Changes a draft or an item pending review: only the fields that are given. Published items cannot be changed here; use start-rewrite for a copy where that is allowed.', 'core-functionality' );
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
			) + $this->get_writable_properties(),
		);
	}

	/**
	 * Returns whether the ability only reads.
	 *
	 * @since  1.0.0
	 * @return bool False.
	 */
	#[Override]
	protected function is_readonly(): bool {
		return false;
	}

	/**
	 * Changes the item.
	 *
	 * @since  1.0.0
	 * @param  array $input The input.
	 * @return array|WP_Error The item and warnings, or the reason why it failed.
	 */
	#[Override]
	protected function run( array $input ): array|WP_Error {
		$post = $this->get_usable_post( (int) ( $input['id'] ?? 0 ) );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		return $this->describe_write( $this->services->writer->update( $post, $input ) );
	}
}
