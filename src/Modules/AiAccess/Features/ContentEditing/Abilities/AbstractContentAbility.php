<?php
/**
 * Abstract Content Ability.
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

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentEditing;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentServices;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\ContentTypes;
use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\AbilityInterface;
use Override;
use WP_Error;
use WP_Post;

use function __;
use function current_user_can;
use function get_post;
use function is_array;

/**
 * Class AbstractContentAbility
 *
 * Base of the content abilities: the name, the category, the MCP exposure and a few checks they
 * all share. A subclass gives its slug, texts and schema, and does its work in execute().
 * Schema descriptions are for the AI assistant and stay in English on purpose; the label and
 * description are also shown to people and are translated.
 *
 * @since 1.0.0
 */
abstract class AbstractContentAbility implements AbilityInterface {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ContentServices $services The shared content services.
	 */
	public function __construct(
		protected readonly ContentServices $services,
	) {}

	/**
	 * Returns the last part of the ability name.
	 *
	 * @since  1.0.0
	 * @return string The slug, for example 'create-draft'.
	 */
	abstract protected function get_slug(): string;

	/**
	 * Returns the translated label.
	 *
	 * @since  1.0.0
	 * @return string The label.
	 */
	abstract protected function get_label(): string;

	/**
	 * Returns the translated description: what the ability does and when to use it.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	abstract protected function get_description(): string;

	/**
	 * Returns the JSON schema of the input.
	 *
	 * @since  1.0.0
	 * @return array An object schema.
	 */
	abstract protected function get_input_schema(): array;

	/**
	 * Returns whether the ability only reads.
	 *
	 * @since  1.0.0
	 * @return bool True when it changes nothing.
	 */
	abstract protected function is_readonly(): bool;

	/**
	 * Does the work.
	 *
	 * @since  1.0.0
	 * @param  array $input The validated input.
	 * @return array|WP_Error The result, or the reason why it failed.
	 */
	abstract protected function run( array $input ): array|WP_Error;

	/**
	 * Returns the full ability name.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'core-functionality/' . $this->get_slug();
	}

	/**
	 * Returns the arguments for wp_register_ability().
	 *
	 * @since  1.0.0
	 * @return array The arguments.
	 */
	#[Override]
	public function get_args(): array {
		return array(
			'label'               => $this->get_label(),
			'description'         => $this->get_description(),
			'category'            => ContentEditing::ABILITY_CATEGORY,
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'meta'                => array(
				'annotations' => array(
					'readonly'    => $this->is_readonly(),
					'destructive' => false,
					'idempotent'  => $this->is_readonly(),
				),
				'mcp'         => array( 'public' => true ),
			),
		);
	}

	/**
	 * Checks whether the current user may use this ability at all. Each post is checked again
	 * when it is used.
	 *
	 * @since  1.0.0
	 * @return bool True when the user may edit posts.
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Runs the ability with its input.
	 *
	 * @since  1.0.0
	 * @param  mixed $input The input.
	 * @return array|WP_Error The result, or the reason why it failed.
	 */
	public function execute( mixed $input = null ): array|WP_Error {
		return $this->run( is_array( $input ) ? $input : array() );
	}

	/**
	 * Returns a post the current user may edit, of a content type that has the level switched on.
	 *
	 * @since  1.0.0
	 * @param  int    $post_id The post id.
	 * @param  string $level   The level needed: one of the ContentTypes::LEVEL_ constants.
	 * @return WP_Post|WP_Error The post, or the reason why it cannot be used.
	 */
	protected function get_usable_post( int $post_id, string $level = ContentTypes::LEVEL_READ ): WP_Post|WP_Error {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || null === $this->services->types->get( $post->post_type ) ) {
			return new WP_Error( 'cf_ai_not_found', __( 'No item with this id that an AI assistant may use.', 'core-functionality' ) );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'cf_ai_forbidden', __( 'You may not edit this item.', 'core-functionality' ) );
		}

		if ( ContentTypes::LEVEL_WRITE === $level && ! $this->services->types->can_write( $post->post_type ) ) {
			return new WP_Error( 'cf_ai_read_only', __( 'An AI assistant may only read this content type, not make or change drafts of it.', 'core-functionality' ) );
		}

		return $post;
	}

	/**
	 * Returns the schema of a post id.
	 *
	 * @since  1.0.0
	 * @return array The schema.
	 */
	protected function get_id_schema(): array {
		return array(
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => 'The id of the item.',
		);
	}

	/**
	 * Returns the schema of a post type: one of the types that may be read, or written.
	 *
	 * @since  1.0.0
	 * @param  string $description The description for the AI assistant.
	 * @param  bool   $writable    Whether only types with drafts switched on count.
	 * @return array The schema.
	 */
	protected function get_post_type_schema( string $description, bool $writable = false ): array {
		$schema = array(
			'type'        => 'string',
			'description' => $description,
		);

		// An empty enum would refuse everything; with no types the ability refuses them itself.
		$types = $writable
			? $this->services->types->get_writable_post_types()
			: $this->services->types->get_readable_post_types();
		if ( array() !== $types ) {
			$schema['enum'] = $types;
		}

		return $schema;
	}

	/**
	 * Returns the schema properties for writing: title, content, excerpt, terms and fields.
	 *
	 * @since  1.0.0
	 * @return array Schema properties.
	 */
	protected function get_writable_properties(): array {
		return array(
			'title'   => array(
				'type'        => 'string',
				'description' => 'The title, as plain text.',
			),
			'content' => array(
				'type'        => 'string',
				'description' => 'The content as block editor markup (for example <!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->). Replaces the whole content, so send all of it.',
			),
			'excerpt' => array(
				'type'        => 'string',
				'description' => 'The excerpt, as plain text.',
			),
			'terms'   => array(
				'type'                 => 'object',
				'description'          => 'Taxonomy => list of existing term ids; replaces the terms of that taxonomy. See core-functionality/list-terms.',
				'additionalProperties' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			'fields'  => $this->services->handlers->get_input_schema(),
		);
	}

	/**
	 * Returns the result of a write: the post as it is now, and any warnings.
	 *
	 * @since  1.0.0
	 * @param  array|WP_Error $result The post and warnings from the writer.
	 * @return array|WP_Error The result for the AI assistant.
	 */
	protected function describe_write( array|WP_Error $result ): array|WP_Error {
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return array(
			'item'     => $this->services->reader->summarize( $result['post'] ),
			'warnings' => $result['warnings'],
		);
	}
}
