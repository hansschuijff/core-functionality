<?php
/**
 * Environment Lookup Service Class.
 *
 * @package DeWittePrins\CoreFunctionality\Environment
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Plugin;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Environment\Plugins\Services\PluginInspector;

use function is_string;
use function sanitize_key;
use function sanitize_text_field;
use function wp_get_environment_type;

/**
 * Class EnvironmentLookup
 *
 * Service that translates internal abstract IDs for Environment components
 * to the handles WordPress uses as their ID.
 *
 * For plugins WordPress uses the plugin's basename as ID, meaning the folder and filename of the plugin's base, like 'core-functionality/core-functionality.php'. This ID is vulnerable to change.
 *
 * For themes the name is used as WordPress internal ID.
 *
 * This registry and lookup decouples the code from the external IDs, so changes in IDs can be handled by simply changing a setting.
 *
 * @since 1.0.0
 */
class EnvironmentLookup {

	/**
	 * Centralized register containing token-to-plugin_basename strings. A plugin whose folder name differs
	 * between sites has a list of basenames instead.
	 *
	 * @var array<string, string|string[]>
	 */
	private array $plugins_register = array();

	/**
	 * Centralized register containing token-to-theme_name strings.
	 *
	 * @var array<string, string>
	 */
	private array $themes_register = array();

	/**
	 * EnvironmentLookup Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin De globale hoofd-plugin container shell.
	 */
	public function __construct( private readonly Plugin $plugin ) {
		$this->set_lookup_registries_from_settings();
	}

	/**
	 * Translates an abstract plugin ID into a physical active plugin basename string.
	 *
	 * When the index lists several basenames (the folder name differs between sites), the first one
	 * that is installed here wins, or else the first one.
	 *
	 * @since 1.0.0
	 * @param string $id The abstract plugin id/key (e.g. 'the-events-calendar').
	 * @return string The basename of the corresponding plugin, or '' if not found.
	 */
	public function get_plugin_basename( string $id ): string {
		$basenames = self::to_basenames( $this->plugins_register[ $id ] ?? '' );

		foreach ( $basenames as $basename ) {
			if ( PluginInspector::is_plugin_installed( $basename ) ) {
				return $basename;
			}
		}

		return $basenames[0] ?? '';
	}

	/**
	 * Returns the basenames of an index entry as a list: one basename, or several when the plugin's
	 * folder name differs between sites.
	 *
	 * @since  1.0.0
	 * @param  mixed $entry The index entry: a basename or a list of basenames.
	 * @return string[] The basenames, empty ones left out.
	 */
	public static function to_basenames( mixed $entry ): array {
		$basenames = array();

		foreach ( (array) $entry as $basename ) {
			if ( is_string( $basename ) && '' !== $basename ) {
				$basenames[] = $basename;
			}
		}

		return $basenames;
	}

	/**
	 * Translates an abstract theme ID into an active theme name string.
	 *
	 * @since 1.0.0
	 * @param string $id The abstract theme id/key (e.g. 'essence-pro').
	 * @return array An array containing the name and author of the theme, or empty array if not found.
	 */
	public function get_theme_name( string $id ): array {
		return $this->themes_register[ $id ] ?? array( 'name' => '' );
	}

	/**
	 * Overwrites the entire plugins lookup register with a new version.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $new_register New dictionary map containing raw ID => Basename allocations.
	 * @return void
	 */
	public function set_plugins_register( array $new_register ): void {
		$this->plugins_register = $this->set_register( $new_register );
	}

	/**
	 * Overwrites the entire themes lookup register with a new version.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $new_register New dictionary map containing raw ID => Basename allocations.
	 * @return void
	 */
	public function set_themes_register( array $new_register ): void {
		$this->themes_register = $this->set_register( $new_register, true );
	}

	/**
	 * Overwrites the entire plugins or themes lookup register with a new version and saves the new version in settings.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $new_register New dictionary map containing raw ID => Basename allocations.
	 * @param  bool                  $is_theme     True if themes register, false if plugins register.
	 * @return array<string, string>               Sanitized and checked new register.
	 */
	private function set_register( array $new_register, bool $is_theme = false ): array {
		$register = $this->sanitize_register( $new_register );

		// Get the current environment lookup index to update (guaranteed to have keys 'plugins' and 'themes').
		$environment_index = $this->get_environment_lookup_register();

		if ( $is_theme ) {
			$environment_index['themes'] = $register;
		} else {
			$environment_index['plugins'] = $register;
		}

		// Save the updated environment lookup index in settings.
		$this->plugin->settings->set( 'environment-index', $environment_index );

		return $register;
	}

	/**
	 * Sanitizes an environment lookup register.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $register An array containing tokens (id) and basenames (plugins or themes).
	 * @return array<string, string>           The sanitized register map.
	 */
	private function sanitize_register( array $register ): array {
		$sanitised_register = array();

		foreach ( $register as $id => $basename ) {
			// Sanitize the new registry entries.
			$clean_id       = sanitize_key( $id );
			$clean_basename = sanitize_text_field( $basename );

			// Skip if not properly filled.
			if ( ! empty( $clean_id ) && ! empty( $clean_basename ) ) {
				$sanitised_register[ $clean_id ] = $clean_basename;
			}
		}

		return $sanitised_register;
	}

	/**
	 * Returns the current deployment environment as a Deployment Enum instance.
	 *
	 * @since  1.0.0
	 * @return Deployment The active deployment environment enum case.
	 */
	public function get_current_deployment_env(): Deployment {
		return match ( $this->get_current_environment_type() ) {
			'development' => Deployment::DEVELOPMENT,
			'staging'     => Deployment::STAGING,
			'production'  => Deployment::PRODUCTION,
		};
	}

	/**
	 * Checks if we're currently running on a production environment.
	 *
	 * @since  1.0.0
	 * @return bool True if current environment is production, false otherwise.
	 */
	public function is_production_environment(): bool {
		return $this->get_current_deployment_env() === Deployment::PRODUCTION;
	}

	/**
	 * Checks if we're currently running on a staging environment.
	 *
	 * @since  1.0.0
	 * @return bool True if current environment is staging, false otherwise.
	 */
	public function is_staging_environment(): bool {
		return $this->get_current_deployment_env() === Deployment::STAGING;
	}

	/**
	 * Checks if we're currently running on a development environment.
	 *
	 * @since  1.0.0
	 * @return bool True if current environment is development, false otherwise.
	 */
	public function is_development_environment(): bool {
		return $this->get_current_deployment_env() === Deployment::DEVELOPMENT;
	}

	/**
	 * Checks if we're currently running on a development or local environment.
	 *
	 * Ddev servers will always be seen as development, otherwise revert to WP_ENVIRONMENT_TYPE constant value.
	 *
	 * If in doubt, will return 'production'.
	 *
	 * @since  1.0.0
	 * @return string 'development', 'staging' or 'production'
	 */
	private function get_current_environment_type(): string {
		// 1. When we're on a (local) ddev server, look no further and always return development.
		if ( isset( $_SERVER['IS_DDEV_PROJECT'] ) || str_contains( $_SERVER['HTTP_HOST'] ?? '', '.ddev.site' ) ) {
			return 'development';
		}

		// 2. Get the official WordPress-environment type.
		$environment_type = wp_get_environment_type();

		// 3. Map WordPress 'local' to 'development'.
		if ( 'local' === $environment_type ) {
			$environment_type = 'development';
		}

		// 4. Check the validity of the env.type, revert to production otherwise.
		$allowed_types = array( 'development', 'staging', 'production' );
		if ( ! in_array( $environment_type, $allowed_types, true ) ) {
			return 'production';
		}

		// 5. Return the validated environment type value.
		return $environment_type;
	}

	/**
	 * Gets the environment lookup register.
	 *
	 * Guaranteed to return an array with both 'plugins' and 'themes' keys present.
	 *
	 * @since  1.0.0
	 * @return array{plugins: array, themes: array} The structured environment lookup register.
	 */
	private function get_environment_lookup_register(): array {
		$register = $this->plugin->settings->get( 'environment-index' );

		$default = array(
			'plugins' => array(),
			'themes'  => array(),
		);

		if ( ! is_array( $register ) ) {
			return $default;
		}

		return array_merge( $default, $register );
	}

	/**
	 * Gets the environment lookup registry from settings and fills the register properties.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function set_lookup_registries_from_settings(): void {
		$register               = $this->get_environment_lookup_register();
		$this->plugins_register = $register['plugins'];
		$this->themes_register  = $register['themes'];
	}
}
