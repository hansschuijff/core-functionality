<?php
/**
 * Ability Interface.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\AiAccess\Features\ContentEditing\Interfaces
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\AiAccess\Features\ContentEditing\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Interface AbilityInterface
 *
 * One ability for the WordPress Abilities API. Abilities are collected on the filter
 * core-functionality/ai-access-abilities and registered on wp_abilities_api_init.
 *
 * @since 1.0.0
 */
interface AbilityInterface {

	/**
	 * Returns the full ability name.
	 *
	 * @since  1.0.0
	 * @return string The name, for example 'core-functionality/create-draft'.
	 */
	public function get_name(): string;

	/**
	 * Returns the arguments for wp_register_ability().
	 *
	 * @since  1.0.0
	 * @return array The arguments.
	 */
	public function get_args(): array;
}
