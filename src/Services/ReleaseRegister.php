<?php
/**
 * File containing the ReleaseRegister class.
 *
 * @package DeWittePrins\CoreFunctionality\Services
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Enums\EnvironmentRule;
use DeWittePrins\CoreFunctionality\Enums\Register;
use DeWittePrins\CoreFunctionality\Plugin;

use function apply_filters;
use function array_intersect_key;
use function array_merge;
use function in_array;
use function is_array;
use function is_string;

/**
 * Class ReleaseRegister
 *
 * Holds, per module and feature, what the release decides about it, before a site does:
 *
 * - its status: how far the code is ready (development, staging, production). It never runs on a
 *   higher environment than that.
 * - its rule per environment (EnvironmentRule): always active, adjustable or always inactive,
 *   based on what it does.
 *
 * These are plugin management properties, not properties of the feature's code, so they live in one
 * file that travels with the release: settings/components-release.php, written by the Release
 * Management tab on a development environment (Hans, October 2026). A component that is not in the
 * file has status development and is adjustable everywhere. A module with features is only their
 * container: its entry is derived from theirs (see with_module_entries()).
 *
 * An installation can adjust them without touching the plugin, in two layers on top of the release:
 *
 * 1. The configuration file wp-content/core-functionality/configuration.php, in the same format; a
 *    plugin update does not overwrite it, and an organisation can roll the same file out to many
 *    sites. Its path can be changed with the filter `core-functionality/configuration-file`.
 * 2. The filter `core-functionality/release-settings`, for a must-use plugin that decides in code.
 *
 * Both are conscious choices by whoever may change the installation's files, so they may raise a
 * limit as well as lower it.
 *
 * @since 1.0.0
 */
class ReleaseRegister {

	/**
	 * File name of the release file, in the plugin's settings folder.
	 *
	 * @var string
	 */
	private const RELEASE_FILE = 'components-release.php';

	/**
	 * Key of the status in an entry.
	 *
	 * @var string
	 */
	public const STATUS_KEY = 'status';

	/**
	 * Setting with the developer's choices on the Release Management tab, including the rules for
	 * environments the status does not reach yet (only on a development site).
	 *
	 * @var string
	 */
	private const CHOICES_SETTING = 'components-release-choices';

	/**
	 * The release settings as they apply in this installation (FQCN => entry), built on first use.
	 *
	 * @var array<string, array<string, string>>|null
	 */
	private ?array $settings = null;

	/**
	 * ReleaseRegister Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The main plugin instance (paths and the FileService).
	 */
	public function __construct( private readonly Plugin $plugin ) {}

	/**
	 * Returns the status of a component: up to which environment its code is ready.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of a module or feature.
	 * @return Deployment The status.
	 */
	public function get_status( string $fqcn ): Deployment {
		$entry = $this->get_entry( $fqcn );

		return Deployment::tryFrom( $entry[ self::STATUS_KEY ] ) ?? Deployment::DEVELOPMENT;
	}

	/**
	 * Returns the rule of a component on one kind of environment.
	 *
	 * @since  1.0.0
	 * @param  string     $fqcn        Fully Qualified Class Name of a module or feature.
	 * @param  Deployment $environment The kind of environment.
	 * @return EnvironmentRule The rule.
	 */
	public function get_rule( string $fqcn, Deployment $environment ): EnvironmentRule {
		$entry = $this->get_entry( $fqcn );

		return EnvironmentRule::tryFrom( $entry[ $environment->value ] ) ?? EnvironmentRule::ADJUSTABLE;
	}

	/**
	 * Returns the entry of a component as it applies in this installation.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of a module or feature.
	 * @return array<string, string> The status and the rule per environment.
	 */
	public function get_entry( string $fqcn ): array {
		return $this->get_settings()[ $fqcn ] ?? self::get_default_entry();
	}

	/**
	 * Returns the entries of the release file itself, without the installation's adjustments.
	 *
	 * @since  1.0.0
	 * @return array<string, array<string, string>> FQCN => entry.
	 */
	public function get_release_values(): array {
		return $this->read_entries( $this->get_release_file_path() );
	}

	/**
	 * Whether this installation's configuration or filter changes the release value of a component.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of a module or feature.
	 * @return bool True when the entry that applies differs from the release file.
	 */
	public function is_adjusted_here( string $fqcn ): bool {
		$release_entry = $this->with_module_entries( $this->get_release_values() )[ $fqcn ] ?? self::get_default_entry();

		return $release_entry !== $this->get_entry( $fqcn );
	}

	/**
	 * Returns the developer's choices as the Release Management tab shows them: the release file,
	 * with the choices for later saved on this site on top.
	 *
	 * @since  1.0.0
	 * @return array<string, array<string, string>> FQCN => entry.
	 */
	public function get_release_choices(): array {
		$choices = $this->plugin->settings->get( self::CHOICES_SETTING, default: array() );
		$entries = $this->get_release_values();

		foreach ( is_array( $choices ) ? $choices : array() as $fqcn => $entry ) {
			if ( is_string( $fqcn ) && is_array( $entry ) ) {
				$entries[ $fqcn ] = $this->sanitize_entry( $entry );
			}
		}

		return $entries;
	}

	/**
	 * Saves the developer's choices on this site and writes what of them applies to the release file,
	 * then asks for the components that may run here to be compared again.
	 *
	 * The release only gets what the build status allows: a rule on an environment the status does
	 * not reach yet becomes "always inactive" in the file. The choice itself is kept in the option, so
	 * it applies once the status gets there (Hans, November 2026).
	 *
	 * @since  1.0.0
	 * @param  array<string, array<string, string>> $entries FQCN => entry; invalid values become the defaults.
	 * @return bool True when the file was written.
	 */
	public function save_release_values( array $entries ): bool {
		$clean_entries     = array();
		$effective_entries = array();
		foreach ( $entries as $fqcn => $entry ) {
			if ( is_string( $fqcn ) && is_array( $entry ) ) {
				$clean_entries[ $fqcn ]     = $this->sanitize_entry( $entry );
				$effective_entries[ $fqcn ] = $this->get_effective_entry( $clean_entries[ $fqcn ] );
			}
		}

		$this->plugin->settings->save( self::CHOICES_SETTING, $clean_entries );

		$written = $this->plugin->file_service->write(
			$this->get_release_file_path(),
			$effective_entries,
			'Release settings of the modules and features: status and rule per environment. Written by the Release Management tab.'
		);

		$this->settings = null;
		$this->plugin->components_register->request_availability_check();

		return $written;
	}

	/**
	 * Returns an entry as it may go into the release: always inactive on every environment its
	 * status does not reach.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $entry A clean entry.
	 * @return array<string, string> The entry for the release file.
	 */
	private function get_effective_entry( array $entry ): array {
		$status = Deployment::from( $entry[ self::STATUS_KEY ] );
		foreach ( Deployment::cases() as $environment ) {
			if ( ! $status->allows( $environment ) ) {
				$entry[ $environment->value ] = EnvironmentRule::AUTO_OFF->value;
			}
		}

		return $entry;
	}

	/**
	 * Returns the entry of a component that is not in the release file.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Status development, freely adjustable everywhere.
	 */
	public static function get_default_entry(): array {
		return array(
			self::STATUS_KEY               => Deployment::DEVELOPMENT->value,
			Deployment::DEVELOPMENT->value => EnvironmentRule::ADJUSTABLE->value,
			Deployment::STAGING->value     => EnvironmentRule::ADJUSTABLE->value,
			Deployment::PRODUCTION->value  => EnvironmentRule::ADJUSTABLE->value,
		);
	}

	/**
	 * Builds, once, the release settings as they apply here: the release file, then the
	 * configuration file, then the filter.
	 *
	 * @since  1.0.0
	 * @return array<string, array<string, string>> FQCN => entry.
	 */
	private function get_settings(): array {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$settings = $this->get_release_values();
		foreach ( $this->read_entries( $this->get_configuration_file_path(), partial: true ) as $fqcn => $entry ) {
			$settings[ $fqcn ] = array_merge( $settings[ $fqcn ] ?? self::get_default_entry(), $entry );
		}

		/**
		 * Filters the release settings of the modules and features as they apply in this installation.
		 *
		 * Runs after the release file and the configuration file. Each entry holds the status
		 * ('development', 'staging' or 'production') and a rule per environment ('auto-on',
		 * 'adjustable' or 'auto-off'), keyed by 'development', 'staging' and 'production'.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<string, string>> $settings FQCN => entry.
		 */
		$filtered = apply_filters( 'core-functionality/release-settings', $settings );
		$filtered = is_array( $filtered ) ? $filtered : $settings;

		$clean_settings = array();
		foreach ( $filtered as $fqcn => $entry ) {
			if ( is_string( $fqcn ) && is_array( $entry ) ) {
				$clean_settings[ $fqcn ] = $this->sanitize_entry( $entry );
			}
		}

		$this->settings = $this->with_module_entries( $clean_settings );

		return $this->settings;
	}

	/**
	 * Returns the entries with the entry of each module derived from its features.
	 *
	 * A module is only a container (Hans, November 2026): it takes the widest setting of its
	 * features, so it is there wherever one of them may run. Its status is the highest status of its
	 * features; its rule on an environment is the widest rule of the features that may run there
	 * (always active before adjustable before always inactive). A module without features keeps its
	 * own entry.
	 *
	 * @since  1.0.0
	 * @param  array<string, array<string, string>> $entries FQCN => entry (features as they apply).
	 * @return array<string, array<string, string>> The entries, with the derived module entries.
	 */
	public function with_module_entries( array $entries ): array {
		foreach ( $this->plugin->components_register->get_register( Register::COMPONENTS ) as $module_fqcn => $features_fqcn ) {
			$feature_entries = array();
			foreach ( (array) $features_fqcn as $feature_fqcn ) {
				$feature_entries[] = $entries[ $feature_fqcn ] ?? self::get_default_entry();
			}

			if ( ! empty( $feature_entries ) ) {
				$entries[ $module_fqcn ] = $this->derive_module_entry( $feature_entries );
			}
		}

		return $entries;
	}

	/**
	 * Returns the entry of a module from the entries of its features: the widest of each.
	 *
	 * @since  1.0.0
	 * @param  array<int, array<string, string>> $feature_entries The (clean) entries of its features.
	 * @return array<string, string> The entry of the module.
	 */
	private function derive_module_entry( array $feature_entries ): array {
		$status = Deployment::DEVELOPMENT;
		foreach ( $feature_entries as $feature_entry ) {
			$feature_status = Deployment::from( $feature_entry[ self::STATUS_KEY ] );
			if ( $feature_status->get_rank() > $status->get_rank() ) {
				$status = $feature_status;
			}
		}

		$module_entry = array( self::STATUS_KEY => $status->value );
		foreach ( Deployment::cases() as $environment ) {
			$module_entry[ $environment->value ] = $this->get_widest_rule( $feature_entries, $environment )->value;
		}

		return $module_entry;
	}

	/**
	 * Returns the widest rule on an environment among the features that may run there.
	 *
	 * Features whose status does not reach the environment do not count; when none reaches it, the
	 * module cannot run there either (its status is theirs), and the widest rule of all is used.
	 *
	 * @since  1.0.0
	 * @param  array<int, array<string, string>> $feature_entries The (clean) entries of the features.
	 * @param  Deployment                        $environment     The kind of environment.
	 * @return EnvironmentRule The widest rule.
	 */
	private function get_widest_rule( array $feature_entries, Deployment $environment ): EnvironmentRule {
		$rules = array();
		foreach ( $feature_entries as $feature_entry ) {
			if ( Deployment::from( $feature_entry[ self::STATUS_KEY ] )->allows( $environment ) ) {
				$rules[] = EnvironmentRule::from( $feature_entry[ $environment->value ] );
			}
		}

		if ( empty( $rules ) ) {
			foreach ( $feature_entries as $feature_entry ) {
				$rules[] = EnvironmentRule::from( $feature_entry[ $environment->value ] );
			}
		}

		if ( in_array( EnvironmentRule::AUTO_ON, $rules, true ) ) {
			return EnvironmentRule::AUTO_ON;
		}

		return in_array( EnvironmentRule::ADJUSTABLE, $rules, true )
			? EnvironmentRule::ADJUSTABLE
			: EnvironmentRule::AUTO_OFF;
	}

	/**
	 * Whether the entry of a module is derived from its features (it has features).
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of a module or feature.
	 * @return bool True for a module with features.
	 */
	public function is_derived( string $fqcn ): bool {
		return ! empty( $this->plugin->components_register->get_register( Register::COMPONENTS )[ $fqcn ] );
	}

	/**
	 * Reads the entries of a release or configuration file.
	 *
	 * @since  1.0.0
	 * @param  string $path    Full path of the file.
	 * @param  bool   $partial Whether entries may hold only some keys (a configuration file); a
	 *                         partial entry keeps only its valid keys, a full one is completed.
	 * @return array<string, array<string, string>> FQCN => entry, or an empty array when there is no file.
	 */
	private function read_entries( string $path, bool $partial = false ): array {
		$data = $this->plugin->file_service->read( $path ) ?? array();

		$entries = array();
		foreach ( $data as $fqcn => $entry ) {
			if ( ! is_string( $fqcn ) || ! is_array( $entry ) ) {
				continue;
			}
			$entries[ $fqcn ] = $partial
				? array_intersect_key( $this->sanitize_entry( $entry ), $this->get_valid_keys( $entry ) )
				: $this->sanitize_entry( $entry );
		}

		return $entries;
	}

	/**
	 * Returns the keys of an entry that hold a valid value.
	 *
	 * @since  1.0.0
	 * @param  array<mixed> $entry A raw entry.
	 * @return array<string, true> Key => true.
	 */
	private function get_valid_keys( array $entry ): array {
		$valid = array();
		foreach ( self::get_default_entry() as $key => $default ) {
			if ( isset( $entry[ $key ] ) && null !== $this->get_valid_value( $key, $entry[ $key ] ) ) {
				$valid[ $key ] = true;
			}
		}

		return $valid;
	}

	/**
	 * Returns a complete entry with only valid values; a missing or invalid value becomes its default.
	 *
	 * @since  1.0.0
	 * @param  array<mixed> $entry A raw entry.
	 * @return array<string, string> The clean entry.
	 */
	private function sanitize_entry( array $entry ): array {
		$clean = array();
		foreach ( self::get_default_entry() as $key => $default ) {
			$clean[ $key ] = $this->get_valid_value( $key, $entry[ $key ] ?? null ) ?? $default;
		}

		return $clean;
	}

	/**
	 * Returns a value when it is valid for its key.
	 *
	 * @since  1.0.0
	 * @param  string $key   self::STATUS_KEY or an environment.
	 * @param  mixed  $value The raw value.
	 * @return string|null The value, or null when it is not valid.
	 */
	private function get_valid_value( string $key, mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$is_valid = self::STATUS_KEY === $key
			? null !== Deployment::tryFrom( $value )
			: null !== EnvironmentRule::tryFrom( $value );

		return $is_valid ? $value : null;
	}

	/**
	 * Returns the path of the release file.
	 *
	 * @since  1.0.0
	 * @return string The path.
	 */
	private function get_release_file_path(): string {
		return $this->plugin->get_data( 'settings-dir' ) . self::RELEASE_FILE;
	}

	/**
	 * Returns the path of this installation's configuration file.
	 *
	 * @since  1.0.0
	 * @return string The path.
	 */
	private function get_configuration_file_path(): string {
		/**
		 * Filters the path of the configuration file that adjusts the release settings in this installation.
		 *
		 * Lets an organisation keep one shared file for many sites.
		 *
		 * @since 1.0.0
		 *
		 * @param string $path Full path; default wp-content/core-functionality/configuration.php.
		 */
		$path = apply_filters( 'core-functionality/configuration-file', WP_CONTENT_DIR . '/core-functionality/configuration.php' );

		return is_string( $path ) ? $path : '';
	}
}
