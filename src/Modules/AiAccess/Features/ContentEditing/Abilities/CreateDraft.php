<?php
/**
 * Create Draft Ability.
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
use function current_user_can;
use function get_post_type_object;

/**
 * Class CreateDraft
 *
 * Creates a new item as a draft. Publishing it is left to the site owner.
 *
 * @since 1.0.0
 */
class CreateDraft extends AbstractContentAbility {

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.0.0
	 * @return string The slug.
	 */
	#[Override]
	protected function get_slug(): string {
		return 'create-draft';
	}

	/**
	 * Returns the translated label.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	#[Override]
	protected function get_label(): string {
		return __( 'Create draft', 'core-functionality' );
	}

	/**
	 * Returns the translated description.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	protected function get_description(): string {
		return __( 'Creates a new post, page or other item as a draft, with title, content, excerpt, terms and extra fields. It is never published; the site owner does that.', 'core-functionality' );
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
			'required'             => array( 'post_type', 'title' ),
			'properties'           => array(
				'post_type' => $this->get_post_type_schema( 'The content type of the new item.', true ),
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
	 * Creates the draft.
	 *
	 * @since  1.0.0
	 * @param  array $input The input.
	 * @return array|WP_Error The new item and warnings, or the reason why it failed.
	 */
	#[Override]
	protected function run( array $input ): array|WP_Error {
		$profile = $this->services->types->get( (string) ( $input['post_type'] ?? '' ) );
		if ( null === $profile || ! $this->services->types->can_write( $profile->get_post_type() ) ) {
			return new WP_Error( 'cf_ai_type', __( 'An AI assistant may not make drafts of this content type.', 'core-functionality' ) );
		}

		$type_object = get_post_type_object( $profile->get_post_type() );
		if ( null === $type_object || ! current_user_can( $type_object->cap->edit_posts ) ) {
			return new WP_Error( 'cf_ai_forbidden', __( 'You may not create items of this type.', 'core-functionality' ) );
		}

		return $this->describe_write( $this->services->writer->create( $profile, $input ) );
	}
}
