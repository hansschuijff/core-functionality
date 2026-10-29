<?php
/**
 * Start Rewrite Ability.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\RewriteCopies
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\RewriteCopies;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities\AbstractContentAbility;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentServices;
use Override;
use WP_Error;
use WP_Post;
use Yoast\WP\Duplicate_Post\Permissions_Helper;
use Yoast\WP\Duplicate_Post\Post_Duplicator;

use function __;
use function get_post;
use function in_array;
use function is_wp_error;
use function wp_update_post;

/**
 * Class StartRewrite
 *
 * Makes a Rewrite & Republish copy of a published item, or returns the copy that already exists,
 * so an AI assistant can change it with update-draft. The copy replaces the original only when
 * the site owner publishes it.
 *
 * @since 1.0.0
 */
class StartRewrite extends AbstractContentAbility {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ContentServices    $services The shared content services.
	 * @param Permissions_Helper $helper   Duplicate Post's own checks.
	 */
	public function __construct(
		ContentServices $services,
		private readonly Permissions_Helper $helper,
	) {
		parent::__construct( $services );
	}

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.0.0
	 * @return string The slug.
	 */
	#[Override]
	protected function get_slug(): string {
		return 'start-rewrite';
	}

	/**
	 * Returns the translated label.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	#[Override]
	protected function get_label(): string {
		return __( 'Start rewrite', 'core-functionality' );
	}

	/**
	 * Returns the translated description.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	protected function get_description(): string {
		return __( 'Makes a Rewrite & Republish copy of a published item (or returns the existing one) to change with update-draft. The copy replaces the original only when the site owner publishes it.', 'core-functionality' );
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
	 * Makes or finds the copy.
	 *
	 * @since  1.0.0
	 * @param  array $input The input.
	 * @return array|WP_Error The copy and the original, or the reason why it failed.
	 */
	#[Override]
	protected function run( array $input ): array|WP_Error {
		$post = $this->get_usable_post( (int) ( $input['id'] ?? 0 ) );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$refusal = $this->check_allowed( $post );
		if ( is_wp_error( $refusal ) ) {
			return $refusal;
		}

		$copy = $this->helper->get_rewrite_and_republish_copy( $post );
		if ( ! $copy instanceof WP_Post ) {
			$copy = $this->create_copy( $post );
			if ( is_wp_error( $copy ) ) {
				return $copy;
			}
		}

		return array(
			'copy'     => $this->services->reader->summarize( $copy ),
			'original' => $this->services->reader->summarize( $post ),
			'next'     => 'Change the copy with core-functionality/update-draft using the id of the copy. The site owner publishes it to replace the original.',
		);
	}

	/**
	 * Refuses items that cannot get a copy: not published, type not allowed, or switched off in
	 * Duplicate Post.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The item.
	 * @return true|WP_Error True, or the reason why not.
	 */
	private function check_allowed( WP_Post $post ): true|WP_Error {
		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'cf_ai_rewrite_status', __( 'Only a published item gets a copy; change drafts directly with update-draft.', 'core-functionality' ) );
		}

		if ( $this->helper->is_rewrite_and_republish_copy( $post ) ) {
			return new WP_Error( 'cf_ai_rewrite_copy', __( 'This item is itself a copy.', 'core-functionality' ) );
		}

		if ( ! $this->services->types->allows_rewrite( $post->post_type ) ) {
			return new WP_Error( 'cf_ai_rewrite_type', __( 'Copies are not allowed for this content type (see the AI Access settings).', 'core-functionality' ) );
		}

		if ( ! $this->helper->is_post_type_enabled( $post->post_type ) || ! $this->helper->is_current_user_allowed_to_copy() ) {
			return new WP_Error( 'cf_ai_rewrite_duplicate_post', __( 'Duplicate Post does not allow a copy of this content type, or not for this user (see its settings).', 'core-functionality' ) );
		}

		return true;
	}

	/**
	 * Makes the copy and makes sure it is a draft, whatever Duplicate Post's settings say.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post The original.
	 * @return WP_Post|WP_Error The copy, or the reason why it failed.
	 */
	private function create_copy( WP_Post $post ): WP_Post|WP_Error {
		$copy_id = ( new Post_Duplicator() )->create_duplicate_for_rewrite_and_republish( $post );
		if ( is_wp_error( $copy_id ) ) {
			return $copy_id;
		}

		$copy = get_post( (int) $copy_id );
		if ( ! $copy instanceof WP_Post ) {
			return new WP_Error( 'cf_ai_rewrite_missing', __( 'The copy could not be found after it was made.', 'core-functionality' ) );
		}

		if ( ! in_array( $copy->post_status, array( 'draft', 'pending' ), true ) ) {
			wp_update_post(
				array(
					'ID'          => $copy->ID,
					'post_status' => 'draft',
				)
			);
			$copy = get_post( $copy->ID );
		}

		return $copy instanceof WP_Post ? $copy : new WP_Error( 'cf_ai_rewrite_missing', __( 'The copy could not be found after it was made.', 'core-functionality' ) );
	}
}
