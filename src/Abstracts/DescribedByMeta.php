<?php
/**
 * Described By Meta Trait.
 *
 * @package DeWittePrins\CoreFunctionality\Abstracts
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Abstracts;

use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Enums\Orientation;

/**
 * Trait DescribedByMeta
 *
 * Answers the descriptive getters of ModuleInterface and FeatureInterface from the
 * ComponentMeta that the class returns in get_meta(). The meta is built once per class
 * per request.
 *
 * @since 1.0.0
 */
trait DescribedByMeta {

	/**
	 * Built meta per class name.
	 *
	 * @var array<string, ComponentMeta>
	 */
	private static array $meta_per_class = array();

	/**
	 * Returns the descriptive data of the module or feature.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta The descriptive data.
	 */
	abstract public static function get_meta(): ComponentMeta;

	/**
	 * Returns the meta of the calling class, building it on first use.
	 *
	 * @since  1.0.0
	 * @return ComponentMeta The descriptive data.
	 */
	protected static function meta(): ComponentMeta {
		return self::$meta_per_class[ static::class ] ??= static::get_meta();
	}

	/**
	 * Returns the unique id.
	 *
	 * @since  1.0.0
	 * @return string The id.
	 */
	public static function get_id(): string {
		return static::meta()->id;
	}

	/**
	 * Returns the human-readable name.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	public static function get_name(): string {
		return static::meta()->name;
	}

	/**
	 * Returns the short description.
	 *
	 * @since  1.0.0
	 * @return string The description.
	 */
	public static function get_description(): string {
		return static::meta()->description;
	}

	/**
	 * Returns where the module or feature runs.
	 *
	 * @since  1.0.0
	 * @return Orientation Admin, front end or both.
	 */
	public static function get_orientation(): Orientation {
		return static::meta()->orientation;
	}

	/**
	 * Returns the environment dependencies.
	 *
	 * @since  1.0.0
	 * @return string|array Environment id, or an and/or array of ids.
	 */
	public static function get_dependencies(): string|array {
		return static::meta()->dependencies;
	}

	/**
	 * Returns the features this module or feature needs.
	 *
	 * @since  1.0.0
	 * @return string[] Fully qualified class names.
	 */
	public static function uses_features(): array {
		return static::meta()->uses_features;
	}

	/**
	 * Returns whether an administrator must confirm the module or feature before it runs.
	 *
	 * @since  1.0.0
	 * @return bool True when activation needs confirmation.
	 */
	public static function needs_activation_confirmation(): bool {
		return static::meta()->confirm_activation;
	}

	/**
	 * Returns the development state of the code: the highest server tier it is ready for.
	 *
	 * @since  1.0.0
	 * @return Deployment Development, staging or production.
	 */
	public static function get_status(): Deployment {
		return static::meta()->status;
	}
}
