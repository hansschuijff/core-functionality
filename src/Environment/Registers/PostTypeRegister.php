<?php
/**
 * Post Type Registry.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Registers
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Environment\Registers;

use DeWittePrins\CoreFunctionality\Enums\ProviderStatus;
use Closure;

use function did_action;
use function in_array;
use function is_callable;
use function is_string;

/**
 * Class PostTypeRegister
 *
 * The catalogue of the post types that the core and every known plugin register, with the
 * status of each provider. Features use it to offer choices per plugin ("events and tickets",
 * "Sensei") instead of per post type, and to find the plugin a post type belongs to.
 *
 * The catalogue is filled once per request by a loader (set by the Environment), the first time
 * it is asked for after init, like the AdminToolbarRegister.
 *
 * @since 1.0.0
 */
class PostTypeRegister {

	/**
	 * The post types per provider.
	 *
	 * @var array<string, string[]>
	 */
	private static array $post_types = array();

	/**
	 * The status per provider.
	 *
	 * @var array<string, ProviderStatus>
	 */
	private static array $statuses = array();

	/**
	 * The name per provider, or a closure that returns it (reading a plugin header is only done
	 * when a name is needed).
	 *
	 * @var array<string, string|Closure>
	 */
	private static array $names = array();

	/**
	 * Fills the catalogue; called once, on first use.
	 *
	 * @var callable|null
	 */
	private static $loader = null;

	/**
	 * Whether the loader has run.
	 *
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Sets the callable that fills the catalogue on first use.
	 *
	 * @since  1.0.0
	 * @param  callable $loader Registers the post types of every provider.
	 * @return void
	 */
	public static function set_loader( callable $loader ): void {
		self::$loader = $loader;
	}

	/**
	 * Registers the post types of a provider. Providers without post types are left out.
	 *
	 * @since  1.0.0
	 * @param  string         $provider_id The get_id() of the plugin or core guard.
	 * @param  string[]       $post_types  The post types the provider registers.
	 * @param  ProviderStatus $status      Whether the provider is active, inactive or not installed.
	 * @param  string|Closure $name        The provider's name, or a closure that returns it.
	 * @return void
	 */
	public static function register( string $provider_id, array $post_types, ProviderStatus $status, string|Closure $name ): void {
		$valid = array();
		foreach ( $post_types as $post_type ) {
			if ( is_string( $post_type ) && '' !== $post_type ) {
				$valid[] = $post_type;
			}
		}

		if ( array() === $valid ) {
			return;
		}

		self::$post_types[ $provider_id ] = $valid;
		self::$statuses[ $provider_id ]   = $status;
		self::$names[ $provider_id ]      = $name;
	}

	/**
	 * Returns the post types of all known providers, active or not.
	 *
	 * @since  1.0.0
	 * @return array<string, string[]> Provider id => post types.
	 */
	public static function get_all(): array {
		self::load();

		return self::$post_types;
	}

	/**
	 * Returns the post types of the active providers.
	 *
	 * @since  1.0.0
	 * @return array<string, string[]> Provider id => post types.
	 */
	public static function get_active(): array {
		self::load();

		$active = array();
		foreach ( self::$post_types as $provider_id => $post_types ) {
			if ( ProviderStatus::ACTIVE === self::$statuses[ $provider_id ] ) {
				$active[ $provider_id ] = $post_types;
			}
		}

		return $active;
	}

	/**
	 * Returns the post types of one provider.
	 *
	 * @since  1.0.0
	 * @param  string $provider_id The get_id() of the plugin or core guard.
	 * @return string[] The post types, or none for an unknown provider.
	 */
	public static function get_post_types( string $provider_id ): array {
		self::load();

		return self::$post_types[ $provider_id ] ?? array();
	}

	/**
	 * Returns the provider a post type belongs to.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type.
	 * @return string|null The provider id, or null when no known provider registers it.
	 */
	public static function get_provider( string $post_type ): ?string {
		self::load();

		foreach ( self::$post_types as $provider_id => $post_types ) {
			if ( in_array( $post_type, $post_types, true ) ) {
				return $provider_id;
			}
		}

		return null;
	}

	/**
	 * Returns the status of a provider.
	 *
	 * @since  1.0.0
	 * @param  string $provider_id The get_id() of the plugin or core guard.
	 * @return ProviderStatus|null The status, or null for an unknown provider.
	 */
	public static function get_status( string $provider_id ): ?ProviderStatus {
		self::load();

		return self::$statuses[ $provider_id ] ?? null;
	}

	/**
	 * Returns the name of a provider (resolved once, the first time it is asked for).
	 *
	 * @since  1.0.0
	 * @param  string $provider_id The get_id() of the plugin or core guard.
	 * @return string The name, or '' for an unknown provider.
	 */
	public static function get_name( string $provider_id ): string {
		self::load();

		$name = self::$names[ $provider_id ] ?? '';
		if ( $name instanceof Closure ) {
			$name                        = (string) $name();
			self::$names[ $provider_id ] = $name;
		}

		return $name;
	}

	/**
	 * Fills the catalogue once, through the loader. Not before init: the guards check their
	 * proofs on init.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private static function load(): void {
		if ( self::$loaded || ! is_callable( self::$loader ) || ! did_action( 'init' ) ) {
			return;
		}

		self::$loaded = true;
		( self::$loader )();
	}
}
