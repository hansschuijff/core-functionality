<?php
/**
 * Field Handlers of the AI Access content abilities.
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

use DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces\FieldHandlerInterface;

use function apply_filters;
use function array_filter;
use function is_array;

/**
 * Class FieldHandlers
 *
 * Collects the groups of extra fields (SEO, event dates, course structure) that other features
 * add, and gives the ones that belong to a post type.
 *
 * @since 1.0.0
 */
class FieldHandlers {

	/**
	 * The handlers, keyed by id, once collected.
	 *
	 * @var FieldHandlerInterface[]|null
	 */
	private ?array $handlers = null;

	/**
	 * Returns every handler.
	 *
	 * @since  1.0.0
	 * @return FieldHandlerInterface[] The handlers, keyed by id.
	 */
	public function get_all(): array {
		if ( null !== $this->handlers ) {
			return $this->handlers;
		}

		/**
		 * Filters the groups of extra fields an AI assistant may read and write.
		 *
		 * @since 1.0.0
		 * @param FieldHandlerInterface[] $handlers The handlers.
		 */
		$handlers = apply_filters( 'core-functionality/ai-access-field-handlers', array() );

		$this->handlers = array();
		foreach ( is_array( $handlers ) ? $handlers : array() as $handler ) {
			if ( $handler instanceof FieldHandlerInterface ) {
				$this->handlers[ $handler->get_id() ] = $handler;
			}
		}

		return $this->handlers;
	}

	/**
	 * Returns the handlers that belong to a post type.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return FieldHandlerInterface[] The handlers, keyed by id.
	 */
	public function get_for( string $post_type ): array {
		return array_filter(
			$this->get_all(),
			fn ( FieldHandlerInterface $handler ) => $handler->applies_to( $post_type )
		);
	}

	/**
	 * Returns the input schema of the "fields" object: one property per handler.
	 *
	 * @since  1.0.0
	 * @return array An object schema.
	 */
	public function get_input_schema(): array {
		$properties = array();
		foreach ( $this->get_all() as $id => $handler ) {
			$properties[ $id ] = $handler->get_input_schema();
		}

		return array(
			'type'        => 'object',
			'description' => 'Extra fields per group. Only the groups that belong to the post type are used.',
			'properties'  => $properties,
		);
	}
}
