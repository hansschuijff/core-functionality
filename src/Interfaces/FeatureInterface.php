<?php
/**
 * Structural Contract for Micro-Features and Contextual Sub-Modules.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Interfaces
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0-or-later
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Interfaces;

use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Interface FeatureInterface
 *
 * Enforces the required toggle, guard, and execution architecture for
 * small-scale contextual alterations and custom business logic extensions.
 *
 * @since 1.0.0
 */
interface FeatureInterface {

	/**
	 * Returns the descriptive data of the feature (id, name, description, orientation, needs).
	 *
	 * Static, because the register and the Kernel read it before the feature exists.
	 * AbstractFeature answers the getters below from it.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta The descriptive data.
	 */
	public static function get_meta(): ComponentMeta;

	/**
	 * Retrieves the absolute, fully qualified system identifier key for this live instance.
	 *
	 * @since  1.0.0
	 * @return string Unique identifier token.
	 */
	public static function get_id(): string;

	/**
	 * Retrieves the human-readable name of the feature.
	 *
	 * @since  1.0.0
	 * @return string Feature title.
	 */
	public static function get_name(): string;

	/**
	 * Retrieves a short description of the feature.
	 *
	 * @since  1.0.0
	 * @return string Short feature description.
	 */
	public static function get_description(): string;

	/**
	 * FeatureInterface Constructor.
	 *
	 * Receives its parent module dependency early for runtime contextual reference.
	 *
	 * @since 1.0.0
	 * @param ModuleInterface $module The instantiating parent module object layer.
	 * @param Plugin          $plugin The central framework orchestrator core reference.
	 */
	public function __construct( ModuleInterface $module, Plugin $plugin );

	/**
	 * Retrieves feature-specific environment prerequisites.
	 *
	 * @since  1.0.0
	 * @return string|array Required plugins and themes for this feature.
	 */
	public static function get_dependencies(): string|array;

	/**
	 * Requests structural dependencies to other features in order to perform an interdependency check.
	 *
	 * @since  1.0.0
	 * @return array<string> An array containing the Fully Qualified Class Names of other features.
	 */
	public static function uses_features(): array;

	/**
	 * Requests the static usage-target of the feature (Frontend, Admin, Both).
	 *
	 * @since  1.0.0
	 * @return Orientation The static environment context enum.
	 */
	public static function get_orientation(): Orientation;

	/**
	 * Requests whether an administrator must confirm the feature before it runs.
	 *
	 * @since  1.0.0
	 * @return bool True when activation needs confirmation.
	 */
	public static function needs_activation_confirmation(): bool;

	/**
	 * Requests the warning shown when someone ticks the feature, or ''.
	 *
	 * @since  1.1.1
	 * @return string The warning (translated, plain text).
	 */
	public static function get_activation_warning(): string;

	/**
	 * Requests extra lines for the explanation of the feature on the feature activation tab.
	 *
	 * @since  1.1.1
	 * @return string[] The lines, as plain text.
	 */
	public static function get_explanation_details(): array;

	/**
	 * Initiates the specific filters, action hooks, and layout alterations.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function launch(): void;

	/**
	 * Retrieves the parent module instance container shell.
	 *
	 * @since  1.0.0
	 * @return ModuleInterface The active initialized parent module container.
	 */
	public function get_module(): ModuleInterface;
}
