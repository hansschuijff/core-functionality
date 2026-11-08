<?php
/**
 * Replace In Draft Ability.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing\Abilities
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.1.3
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentTypes;
use Override;
use WP_Error;

use function __;
use function count;
use function is_array;

/**
 * Class ReplaceInDraft
 *
 * Changes pieces of the content of a draft, an item pending review or a Rewrite & Republish copy,
 * so a small change does not need all of the content to be sent back. Everything around the
 * pieces stays byte for byte the same. Published items are refused, as in UpdateDraft.
 *
 * @since 1.1.3
 */
class ReplaceInDraft extends AbstractContentAbility {

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.1.3
	 * @return string The slug.
	 */
	#[Override]
	protected function get_slug(): string {
		return 'replace-in-draft';
	}

	/**
	 * Returns the translated label.
	 *
	 * @since  1.1.3
	 * @return string The label.
	 */
	#[Override]
	protected function get_label(): string {
		return __( 'Replace text in draft', 'core-functionality' );
	}

	/**
	 * Returns the translated description.
	 *
	 * @since  1.1.3
	 * @return string The description.
	 */
	#[Override]
	protected function get_description(): string {
		return __( 'Replaces pieces of the content of a draft or an item pending review, without sending all of it back. Each old text must occur exactly once in the raw content; otherwise nothing changes. Published items cannot be changed here; use start-rewrite for a copy where that is allowed.', 'core-functionality' );
	}

	/**
	 * Returns the JSON schema of the input.
	 *
	 * @since  1.1.3
	 * @return array An object schema.
	 */
	#[Override]
	protected function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id', 'replacements' ),
			'properties'           => array(
				'id'           => $this->get_id_schema(),
				'replacements' => $this->get_replacements_schema(),
			),
		);
	}

	/**
	 * Returns the schema of the list of replacements.
	 *
	 * @since  1.1.3
	 * @return array An array schema.
	 */
	private function get_replacements_schema(): array {
		return array(
			'type'        => 'array',
			'minItems'    => 1,
			'description' => 'The pieces to replace. All are looked up in the current content, so their order does not matter; they may not overlap. If one fails, nothing changes.',
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'old_text', 'new_text' ),
				'properties'           => array(
					'old_text' => array(
						'type'        => 'string',
						'minLength'   => 1,
						'description' => 'Text to replace, copied exactly from the raw content of core-functionality/get-content (block markup included). It must occur exactly once.',
					),
					'new_text' => array(
						'type'        => 'string',
						'description' => 'The text to put in its place; empty removes the old text. It may not add markup that wp_kses_post() refuses, such as scripts, event handlers or new SVG elements.',
					),
				),
			),
		);
	}

	/**
	 * Returns whether the ability only reads.
	 *
	 * @since  1.1.3
	 * @return bool False.
	 */
	#[Override]
	protected function is_readonly(): bool {
		return false;
	}

	/**
	 * Replaces the pieces in the item.
	 *
	 * @since  1.1.3
	 * @param  array $input The input.
	 * @return array|WP_Error The item, warnings and number of replacements, or the reason why it failed.
	 */
	#[Override]
	protected function run( array $input ): array|WP_Error {
		$post = $this->get_usable_post( (int) ( $input['id'] ?? 0 ), ContentTypes::LEVEL_WRITE );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$replacements = is_array( $input['replacements'] ?? null ) ? $input['replacements'] : array();

		$result = $this->describe_write( $this->services->writer->replace_in_content( $post, $replacements ) );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return $result + array( 'replaced' => count( $replacements ) );
	}
}
