<?php
/**
 * Module Interface Contract.
 *
 * @package DeWittePrins\CoreFunctionality\Interfaces
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Interfaces;

use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Orientation;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Interface ModuleInterface
 *
 * Defines the contract for all core modules. Requires static metadata
 * generation for registry inventory and dynamic activation lifecycles.
 *
 * Note that the function of a module in this plugin is just a container of features.
 * Modules are not meant to contain any functionality of their own.
 *
 * If functionality is needed, it should be implemented by adding a new feature.
 * Although the kernel will fire the launch() method, it should normally be an empty method.
 *
 * @since 1.0.0
 */
interface ModuleInterface {

	/**
	 * Returns the descriptive data of the module (id, name, description, orientation, needs).
	 *
	 * Static, because the register and the Kernel read it before the module exists.
	 * AbstractModule answers the getters below from it.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta The descriptive data.
	 */
	public static function get_meta(): ComponentMeta;

	/**
	 * Retrieves the unique string identifier for the module.
	 *
	 * @since  1.0.0
	 * @return string Unique module key (slug syntax).
	 */
	public static function get_id(): string;

	/**
	 * Retrieves the human-readable name of the module.
	 *
	 * @since  1.0.0
	 * @return string Module title.
	 */
	public static function get_name(): string;

	/**
	 * Retrieves the contextual description of what the module provides.
	 *
	 * @since  1.0.0
	 * @return string Module description.
	 */
	public static function get_description(): string;

	/**
	 * Requests the current usage-target of the module (Frontend, Admin, Both).
	 *
	 * @since  1.0.0
	 * @return Orientation Enum indication if the module is targeted for use on the Frontend, Admin or both.
	 */
	public static function get_orientation(): Orientation;

	/**
	 * Requests the structural environment requirements for the module base layer.
	 *
	 * Must return a multi-dimensional array mapping an 'and' or 'or' matrix
	 * of clean kebab-case system environment identifiers.
	 * Example: array( 'and' => array( 'woocommerce', 'woocommerce-subscriptions' ) )
	 *
	 * Note: Made static so the loader can run pre-flights before instantiation.
	 *
	 * @since  1.0.0
	 * @return string|array A string with the id of dependent plugin or theme, or multi-dimensional array tracking environmental requirements.
	 */
	public static function get_dependencies(): string|array;

	/**
	 * Requests structural dependencies to other features in order to perform an interdependency check.
	 *
	 * Note: Made static so interdependency can be inspected statically.
	 *
	 * @since  1.0.0
	 * @return array<string> An array containing the Fully Qualified Class Names of other features that are required by this feature.
	 */
	public static function uses_features(): array;

	/**
	 * Requests whether an administrator must confirm the module before it runs.
	 *
	 * @since  1.0.0
	 * @return bool True when activation needs confirmation.
	 */
	public static function needs_activation_confirmation(): bool;

	/**
	 * Initiates the module base configuration layer.
	 *
	 * Executed exclusively by the central loader orchestrator once the module's
	 * pre-flight validation matrices return an absolute true state.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function launch(): void;
}
