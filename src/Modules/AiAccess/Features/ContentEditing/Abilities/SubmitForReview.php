<?php
/**
 * Submit For Review Ability.
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

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentTypes;
use Override;
use WP_Error;

use function __;

/**
 * Class SubmitForReview
 *
 * Sets a draft to "pending review", so the site owner sees it is ready to look at.
 *
 * @since 1.0.0
 */
class SubmitForReview extends AbstractContentAbility {

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.0.0
	 * @return string The slug.
	 */
	#[Override]
	protected function get_slug(): string {
		return 'submit-for-review';
	}

	/**
	 * Returns the translated label.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	#[Override]
	protected function get_label(): string {
		return __( 'Submit for review', 'core-functionality' );
	}

	/**
	 * Returns the translated description.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	protected function get_description(): string {
		return __( 'Sets a draft to "pending review", so the site owner sees it is ready to look at. It can still be changed afterwards.', 'core-functionality' );
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
	 * @return bool False.
	 */
	#[Override]
	protected function is_readonly(): bool {
		return false;
	}

	/**
	 * Submits the draft.
	 *
	 * @since  1.0.0
	 * @param  array $input The input.
	 * @return array|WP_Error The item, or the reason why it failed.
	 */
	#[Override]
	protected function run( array $input ): array|WP_Error {
		$post = $this->get_usable_post( (int) ( $input['id'] ?? 0 ), ContentTypes::LEVEL_WRITE );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		return $this->describe_write( $this->services->writer->submit_for_review( $post ) );
	}
}
