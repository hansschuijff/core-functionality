<?php
/**
 * Component Meta DTO.
 *
 * @package DeWittePrins\CoreFunctionality\DTO
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\DTO;

use DeWittePrins\CoreFunctionality\Enums\Orientation;

/**
 * Class ComponentMeta
 *
 * The descriptive data of a module or feature: what it is called, where it runs and what it
 * needs. A module or feature fills it in its static get_meta(); the abstract base classes
 * answer the interface getters from it, so the class itself only holds its functionality.
 *
 * @since 1.0.0
 */
readonly class ComponentMeta {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string       $id            Unique id of the module or feature.
	 * @param string       $name          Human-readable (translated) name.
	 * @param string       $description   Short (translated) description.
	 * @param Orientation  $orientation   Where it runs: admin, front end or both.
	 * @param string|array $dependencies  Environment id, or an and/or array of ids, that must be ready.
	 * @param string[]     $uses_features Fully qualified class names of features it needs.
	 * @param bool         $confirm_activation Whether an administrator must confirm it before it runs on a
	 *                                         site, for example because it replaces code that may still run there.
	 */
	public function __construct(
		public string $id,
		public string $name,
		public string $description = '',
		public Orientation $orientation = Orientation::BOTH,
		public string|array $dependencies = array(),
		public array $uses_features = array(),
		public bool $confirm_activation = false,
	) {}
}
