<?php
/**
 * List Venues And Organizers Ability.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\EventContent
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\EventContent;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Abilities\AbstractContentAbility;
use Override;
use WP_Error;
use WP_Post;

use function __;
use function get_posts;

/**
 * Class ListVenuesAndOrganizers
 *
 * Lists the published venues and organizers of The Events Calendar with their ids, so an AI
 * assistant can link them to an event. It does not create them.
 *
 * @since 1.0.0
 */
class ListVenuesAndOrganizers extends AbstractContentAbility {

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.0.0
	 * @return string The slug.
	 */
	#[Override]
	protected function get_slug(): string {
		return 'list-venues-and-organizers';
	}

	/**
	 * Returns the translated label.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	#[Override]
	protected function get_label(): string {
		return __( 'List venues and organizers', 'core-functionality' );
	}

	/**
	 * Returns the translated description.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	#[Override]
	protected function get_description(): string {
		return __( 'Lists the existing venues and organizers of events with their ids, to link them to an event. New ones are not created.', 'core-functionality' );
	}

	/**
	 * Returns the JSON schema of the input (none).
	 *
	 * @since  1.0.0
	 * @return array An object schema.
	 */
	#[Override]
	protected function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(),
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
	 * Returns the venues and organizers.
	 *
	 * @since  1.0.0
	 * @param  array $input The input.
	 * @return array|WP_Error The venues and organizers.
	 */
	#[Override]
	protected function run( array $input ): array|WP_Error {
		return array(
			'venues'     => $this->list_posts( 'tribe_venue' ),
			'organizers' => $this->list_posts( 'tribe_organizer' ),
		);
	}

	/**
	 * Returns id and title of the published posts of a type.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return array<int, array{id: int, title: string}> The posts.
	 */
	private function list_posts( string $post_type ): array {
		$posts = get_posts(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'numberposts' => 200,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$list = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$list[] = array(
					'id'    => $post->ID,
					'title' => $post->post_title,
				);
			}
		}

		return $list;
	}
}
