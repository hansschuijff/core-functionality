<?php
/**
 * File containing the ComponentsRegister class.
 *
 * @package DeWittePrins\CoreFunctionality\Services
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Services;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Enums\Register;
use DeWittePrins\CoreFunctionality\Enums\RegisterSetting;
use DeWittePrins\CoreFunctionality\ValueObjects\ModuleClassValidator;
use DeWittePrins\CoreFunctionality\Services\FileSystem\FinderService;
use DeWittePrins\CoreFunctionality\Interfaces\ModuleInterface;
use DeWittePrins\CoreFunctionality\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service responsible for scanning the file system to locate autonomous features.
 *
 * Handles both active memory loading and disk directory iterations.
 *
 * @since 1.0.0
 */
class ComponentsRegister {

	/**
	 * The current Deployment environment (Deployment::development, Deployment::staging of Deployment::production).
	 *
	 * @var Deployment
	 */
	private Deployment $current_deployment;

	/**
	 * The current orientation (Orientation::ADMIN of Orientation::FRONTEND).
	 *
	 * @var Orientation
	 */
	private Orientation $current_orientation;

	/**
	 * The base namespace for modules.
	 *
	 * @var string
	 */
	private string $modules_namespace;

	/**
	 * Registry of all enabled modules and features (FQCN => true).
	 *
	 * @var array<string, bool>|null
	 */
	public ?array $enabled_components = null;

	/**
	 * Raw structural registry blueprint of all found disk modules and features.
	 *
	 * @var array<string, array>|null
	 */
	private ?array $components_register = null;

	/**
	 * Registry tracking the targeted deployment environments for components.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $components_deployment = null;

	/**
	 * Registry of modules and features with a frontend orientation.
	 *
	 * @var ModuleClassValidator[]|null
	 */
	private ?array $frontend_register = null;

	/**
	 * Registry of modules and features with an admin orientation.
	 *
	 * @var ModuleClassValidator[]|null
	 */
	private ?array $admin_register = null;

	/**
	 * Default deployment Enum.
	 *
	 * @var Deployment
	 */
	private Deployment $default_deployment = Deployment::DEVELOPMENT;

	/**
	 * Settings token under which the plugin version of the stored registers is kept.
	 *
	 * @var string
	 */
	private const VERSION_SETTING = 'components-register-version';

	/**
	 * Transient that holds the components added by the last plugin update, for an admin notice.
	 *
	 * @var string
	 */
	private const NEW_COMPONENTS_TRANSIENT = 'dwp_cf_new_components';

	/**
	 * Settings token under which this site keeps the components whose activation an administrator decided on.
	 *
	 * Only in the database, never in the register files: a decision belongs to one site.
	 *
	 * @var string
	 */
	private const ACTIVATION_DECIDED_SETTING = 'components-activation-decided';

	/**
	 * Components whose activation was decided on this site (FQCN => true).
	 *
	 * @var array<string, bool>|null
	 */
	private ?array $activation_decided = null;

	/**
	 * Settings token under which the deployment values of the installed release are kept (FQCN => tier).
	 *
	 * On the next update they tell whether this site changed a value itself (it differs from them) or
	 * still follows the release.
	 *
	 * @var string
	 */
	private const DEPLOYMENT_RELEASE_SETTING = 'components-deployment-release';

	/**
	 * Settings token under which the open questions about a new release's deployment are kept
	 * (FQCN => the tier the release sets).
	 *
	 * @var string
	 */
	private const DEPLOYMENT_QUESTIONS_SETTING = 'components-deployment-questions';

	/**
	 * Settings token under which the components that may run on this server are kept (list of FQCNs).
	 *
	 * @var string
	 */
	private const AVAILABLE_SETTING = 'components-available';

	/**
	 * Settings token that asks for the available components to be compared again on the next request.
	 *
	 * @var string
	 */
	private const AVAILABILITY_CHECK_SETTING = 'components-availability-check';

	/**
	 * ComponentsRegister Constructor.
	 *
	 * Hydrates all framework register matrices seamlessly using the unified
	 * Settings Option Vault pipeline, falling back dynamically to disk blueprints.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin Central framework orchestrator core instance.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {
		$this->modules_namespace   = $this->plugin->get_data( 'modules-namespace' );
		$this->current_deployment  = $this->plugin->get_deployment();
		$this->current_orientation = $this->plugin->get_orientation();

		// Must run before the registers are read, so an update's register file wins over a stale option.
		$this->sync_with_plugin_version();

		$this->enabled_components    = $this->get_register( Register::ENABLED );
		$this->components_register   = $this->get_register( Register::COMPONENTS );
		$this->components_deployment = $this->get_register( Register::DEPLOYMENT );

		// After the translations (init -10), because it reads the components' meta; before the Kernel (init 0).
		add_action( 'init', array( $this, 'handle_newly_available_components' ), -5 );
	}

	/**
	 * Refreshes the stored registers after a plugin update.
	 *
	 * The registers are kept in options for speed and fall back to the files in kernel/ when the
	 * option is missing. After an update the components option would still describe the previous
	 * version, so a new feature would stay invisible. When the stored version differs from the plugin
	 * version, the components option is removed (get_register() then refills it from this version's
	 * file) and the components that are new get their enabled state and deployment tier from the
	 * files. The site's own choices for existing components are kept.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function sync_with_plugin_version(): void {
		$plugin_version = $this->plugin->version();
		$stored_version = $this->plugin->settings->get( self::VERSION_SETTING, default: '' );

		if ( $plugin_version === $stored_version ) {
			return;
		}

		$previous_components = $this->plugin->settings->get( RegisterSetting::COMPONENTS->value, default: array() );
		$previous_components = is_array( $previous_components ) ? $previous_components : array();

		$this->plugin->settings->delete( RegisterSetting::COMPONENTS->value );
		$components = $this->get_register( Register::COMPONENTS );

		$new_components = array_values(
			array_diff( $this->flatten_components( $components ), $this->flatten_components( $previous_components ) )
		);

		if ( ! empty( $previous_components ) && ! empty( $new_components ) ) {
			$this->add_file_defaults( Register::ENABLED, $new_components );
			$this->add_file_defaults( Register::DEPLOYMENT, $new_components );
			set_transient( self::NEW_COMPONENTS_TRANSIENT, $new_components, WEEK_IN_SECONDS );
		}

		$this->follow_release_deployment();
		$this->request_availability_check();

		$this->plugin->settings->save( self::VERSION_SETTING, $plugin_version );
	}

	/**
	 * Applies the deployment values of a new release to the existing components where this site allows it.
	 *
	 * Compares three values per component: the previous release, the new release and this site.
	 * A site value equal to the previous release was never changed here, so it follows the new release.
	 * A site value of its own stays; when the release changed too, an administrator is asked whether the
	 * new release value may apply (DeploymentQuestion). Until then the site's value holds, so automatic
	 * updates never overrule a local choice (Hans, October 2026). New components already got the release
	 * value from add_file_defaults(). The first time there is no previous release to compare with, so
	 * only the baseline is stored.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function follow_release_deployment(): void {
		$release_values = $this->plugin->components_register_files->get_register( Register::DEPLOYMENT );
		$release_values = is_array( $release_values ) ? $release_values : array();

		$previous_release_values = $this->plugin->settings->get( self::DEPLOYMENT_RELEASE_SETTING, default: null );
		$this->plugin->settings->save( self::DEPLOYMENT_RELEASE_SETTING, $release_values );

		$site_values = $this->plugin->settings->get( RegisterSetting::DEPLOYMENT->value, default: null );

		// Without a baseline nothing can be compared; without site values the site reads the file anyway.
		if ( ! is_array( $previous_release_values ) || ! is_array( $site_values ) ) {
			return;
		}

		$questions = $this->get_deployment_questions();
		$default   = $this->default_deployment->value;

		foreach ( $release_values as $fqcn => $release_value ) {
			$previous_value = $previous_release_values[ $fqcn ] ?? $default;
			$site_value     = $site_values[ $fqcn ] ?? $default;

			if ( $previous_value === $release_value || $site_value === $release_value ) {
				unset( $questions[ $fqcn ] );
				continue;
			}

			if ( $site_value === $previous_value ) {
				$site_values[ $fqcn ] = $release_value;
				unset( $questions[ $fqcn ] );
				continue;
			}

			$questions[ $fqcn ] = $release_value;
		}

		$this->plugin->settings->save( RegisterSetting::DEPLOYMENT->value, $site_values );
		$this->components_deployment = $site_values;
		$this->save_deployment_questions( $questions );
	}

	/**
	 * Returns the open questions about a new release's deployment values.
	 *
	 * Only for components that still exist.
	 *
	 * @since  1.0.0
	 * @return array<string, string> FQCN => the tier the release sets.
	 */
	public function get_deployment_questions(): array {
		$questions = $this->plugin->settings->get( self::DEPLOYMENT_QUESTIONS_SETTING, default: array() );
		$questions = is_array( $questions ) ? $questions : array();

		$components = $this->flatten_components( $this->get_register( Register::COMPONENTS ) );

		return array_intersect_key( $questions, array_flip( $components ) );
	}

	/**
	 * Records an administrator's answer to the question whether a new release's deployment value may apply.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn  Fully Qualified Class Name of a module or feature with an open question.
	 * @param  bool   $adopt True to take the release value, false to keep this site's value.
	 * @return void
	 */
	public function answer_deployment_question( string $fqcn, bool $adopt ): void {
		$questions = $this->get_deployment_questions();
		if ( ! array_key_exists( $fqcn, $questions ) ) {
			return;
		}

		if ( $adopt ) {
			$site_values          = $this->get_register( Register::DEPLOYMENT );
			$site_values[ $fqcn ] = $questions[ $fqcn ];
			$this->save( Register::DEPLOYMENT, $site_values );
		}

		unset( $questions[ $fqcn ] );
		$this->save_deployment_questions( $questions );
	}

	/**
	 * Removes every open deployment question, as saving the Deployment tab decides them all.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function clear_deployment_questions(): void {
		$this->save_deployment_questions( array() );
	}

	/**
	 * Stores the open deployment questions, or removes the setting when there are none.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $questions FQCN => the tier the release sets.
	 * @return void
	 */
	private function save_deployment_questions( array $questions ): void {
		if ( empty( $questions ) ) {
			$this->plugin->settings->delete( self::DEPLOYMENT_QUESTIONS_SETTING );
			return;
		}

		$this->plugin->settings->save( self::DEPLOYMENT_QUESTIONS_SETTING, $questions );
	}

	/**
	 * Asks for the components that may run on this server to be compared again on the next request.
	 *
	 * Called when a status or deployment value may have changed: after an update and after a deployment save.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function request_availability_check(): void {
		$this->plugin->settings->save( self::AVAILABILITY_CHECK_SETTING, true );
	}

	/**
	 * Treats components that may now run on this server, and could not before, as new.
	 *
	 * A status or deployment change can make a component available here (Hans, October 2026). Like a new
	 * component it is then switched on, or, when it asks for confirmation, its question is asked (again).
	 * Runs only when a check was requested, or once to store the first list; the first list has nothing to
	 * compare with, so it changes nothing. Hooked on init -5: after the translations, before the Kernel.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function handle_newly_available_components(): void {
		$previous_available = $this->plugin->settings->get( self::AVAILABLE_SETTING, default: null );
		$check_requested    = (bool) $this->plugin->settings->get( self::AVAILABILITY_CHECK_SETTING, default: false );

		if ( is_array( $previous_available ) && ! $check_requested ) {
			return;
		}

		$available = array_values(
			array_filter(
				$this->flatten_components( $this->get_register( Register::COMPONENTS ) ),
				fn( string $fqcn ): bool => $this->current_deployment_allowed( $fqcn )
			)
		);

		$this->plugin->settings->save( self::AVAILABLE_SETTING, $available );
		$this->plugin->settings->delete( self::AVAILABILITY_CHECK_SETTING );

		if ( ! is_array( $previous_available ) ) {
			return;
		}

		$newly_available = array_diff( $available, $previous_available );
		if ( empty( $newly_available ) ) {
			return;
		}

		$enabled_components = $this->get_register( Register::ENABLED );
		$decisions          = $this->get_activation_decisions();

		foreach ( $newly_available as $fqcn ) {
			if ( $fqcn::needs_activation_confirmation() ) {
				unset( $decisions[ $fqcn ] );
				continue;
			}

			$enabled_components[ $fqcn ] = true;
		}

		$this->save( Register::ENABLED, $enabled_components );
		$this->plugin->settings->save( self::ACTIVATION_DECIDED_SETTING, $decisions );
		$this->activation_decided = $decisions;
	}

	/**
	 * Adds the file values of the given components to a register option that holds the site's choices.
	 *
	 * Components that already have a value in the option keep it.
	 *
	 * @since  1.0.0
	 * @param  Register $register Register::ENABLED or Register::DEPLOYMENT.
	 * @param  string[] $fqcns    Fully qualified class names of the new modules and features.
	 * @return void
	 */
	private function add_file_defaults( Register $register, array $fqcns ): void {
		$settings_key = $this->get_register_setting_key( $register );
		$site_values  = $this->plugin->settings->get( $settings_key, default: null );

		// Without site values get_register() reads the file anyway.
		if ( ! is_array( $site_values ) ) {
			return;
		}

		$file_values = $this->plugin->components_register_files->get_register( $register );
		foreach ( $fqcns as $fqcn ) {
			if ( array_key_exists( $fqcn, $file_values ) && ! array_key_exists( $fqcn, $site_values ) ) {
				$site_values[ $fqcn ] = $file_values[ $fqcn ];
			}
		}

		$this->plugin->settings->save( $settings_key, $site_values );
	}

	/**
	 * Returns the modules and features of a components register as one flat list.
	 *
	 * @since  1.0.0
	 * @param  array<string, string[]> $components Module class name => feature class names.
	 * @return string[]                            All module and feature class names.
	 */
	private function flatten_components( array $components ): array {
		$fqcns = array();
		foreach ( $components as $module_fqcn => $features_fqcn ) {
			$fqcns[] = $module_fqcn;
			foreach ( (array) $features_fqcn as $feature_fqcn ) {
				$fqcns[] = $feature_fqcn;
			}
		}

		return $fqcns;
	}

	/**
	 * Returns, once, the components that the last plugin update added.
	 *
	 * @since  1.0.0
	 * @return string[] Fully qualified class names, or an empty array.
	 */
	public function pull_new_components(): array {
		$new_components = get_transient( self::NEW_COMPONENTS_TRANSIENT );
		if ( false === $new_components ) {
			return array();
		}

		delete_transient( self::NEW_COMPONENTS_TRANSIENT );

		return is_array( $new_components ) ? $new_components : array();
	}

	/**
	 * Gets the register of modules and features of this plugin.
	 *
	 * In this method the components register is filtered to give only launchable
	 * modules and features, meaning:
	 * 1. Is enabled.
	 * 2. The target environment (frontend, admin) matches the current environment.
	 * 3. The deployment of the component allows launching on the current server environment.
	 *
	 * If any other register is given in de arguments, then it is returned unfiltered.
	 *
	 * @since  1.0.0
	 * @param  Register $register Which register to return. Default the components register.
	 *
	 * @return array<string, array> The filtered array of module FQCNs with their verified feature FQCNs.
	 */
	public function get( Register $register = Register::COMPONENTS ): array {
		$data = $this->get_register( $register );
		if ( Register::COMPONENTS === $register ) {
			return $this->filter( $data );
		}

		return $data;
	}

	/**
	 * Initializes and eager-loads a specific register from the database options vault,
	 * falling back dynamically to filesystem declarative snapshot files.
	 *
	 * The components register is validated after reading: a module or feature that no longer
	 * exists (removed from the code, the register not rescanned yet) is left out, so the kernel
	 * and the settings page go on without it.
	 *
	 * @since  1.0.0
	 * @param  Register $register Target architectural registration matrix key identifier.
	 * @return array              The cached configuration array payload, or a clean fallback array.
	 */
	public function get_register( Register $register ): array {

		$settings_key = $this->get_register_setting_key( $register );
		if ( ! $settings_key ) {
			return array();
		}

		// 1. Primary Strategy: Extract runtime data from our unified Option Vault service layer.
		$data = $this->plugin->settings->get( $settings_key, default: null );

		if ( null !== $data && is_array( $data ) ) {
			return Register::COMPONENTS === $register ? $this->validate( $data ) : $data;
		}

		// 2. Secondary Strategy: Fallback to reading declarative layout schemas from disk files.
		$data = $this->plugin->components_register_files->get_register( $register );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		if ( Register::COMPONENTS === $register ) {
			$data = $this->validate( $data );
		}

		// 3. Sync Strategy: Commit disk blueprints into the Option Vault cache for instant sub-millisecond execution next time.
		if ( ! empty( $data ) ) {
			$this->plugin->settings->save( $settings_key, $data );
		}

		return $data;
	}

	/**
	 * Translates structural Register components into their corresponding RegisterSetting option storage keys.
	 *
	 * @since  1.0.0
	 * @param  Register $register Target registration layer identifier.
	 * @return string|false       The resolved database setting storage key string, false on configuration gaps.
	 */
	private function get_register_setting_key( Register $register ): string|false {
		return match ( $register ) {
			Register::COMPONENTS => RegisterSetting::COMPONENTS->value,
			Register::ENABLED    => RegisterSetting::ENABLED->value,
			Register::DEPLOYMENT => RegisterSetting::DEPLOYMENT->value,
			Register::FRONTEND   => RegisterSetting::FRONTEND->value,
			Register::ADMIN      => RegisterSetting::ADMIN->value,

			// Failsafe: Prevent UnhandledMatchError crashes if an unmapped Enum case passes through.
			default              => false,
		};
	}

	/**
	 * Gets the register of features for a given module.
	 *
	 * This method is meant for use when you need the filtered list
	 * (launchable in current environment).
	 *
	 * @since  1.0.0
	 * @param string $module The Classname slug, or Fully Qualified Class Name, of a module.
	 * @return array<string, array> The filtered array of verified feature FQCNs for the module.
	 */
	public function get_module_features( string $module ): array {
		// Validation and preparations.
		if ( empty( $module ) ) {
			return array();
		}
		$module_fqcn = $this->get_module_fqcn( $module );
		if ( ! $module_fqcn ) {
			return array();
		}

		// Get the unfiltered features.
		$module_features_fcqn = $this->get_module_features_unfiltered( $module_fqcn );
		if ( empty( $module_features_fcqn ) ) {
			return $module_features_fcqn;
		}

		// Filter the output before returning it.
		$component[ $module_fqcn ] = $module_features_fcqn;
		$component                 = $this->filter( $component );

		return isset( $component[ $module_fqcn ] ) ? $component[ $module_fqcn ] : array();
	}

	/**
	 * Gets the register of features for a given module.
	 *
	 * @since  1.0.0
	 * @param string $module The Classname slug, or Fully Qualified Class Name, of a module.
	 * @return array<int, string> The complete unfiltered array of verified feature FQCNs for the module.
	 */
	public function get_module_features_unfiltered( string $module ): array {
		if ( empty( $module ) ) {
			return array();
		}

		if ( null === $this->components_register ) {
			$this->components_register = $this->get_components_register();
		}

		$module_fqcn = $this->get_module_fqcn( $module );
		if ( ! $module_fqcn ) {
			return array();
		}

		$module_features_fcqn = isset( $this->components_register[ $module_fqcn ] ) ? $this->components_register[ $module_fqcn ] : array();

		return $module_features_fcqn;
	}

	/**
	 * Gets only the modules (without Features) from the filtered components register.
	 *
	 * @since  1.0.0
	 * @return array<int, string> Filtered list of all registered modules fully qualified modules class names.
	 */
	public function get_modules(): array {
		$components_register = $this->get_register( Register::COMPONENTS );
		if ( empty( $components_register ) ) {
			return array();
		}
		return array_keys( $components_register ) ?? array();
	}

	/**
	 * Geta only the modules (without Features) from the unfiltered components register.
	 *
	 * @since  1.0.0
	 * @return array<int, string> List of all registered modules fully qualified modules class names.
	 */
	public function get_modules_unfiltered(): array {
		$components_register = $this->get_register( Register::COMPONENTS );
		if ( empty( $components_register ) ) {
			return array();
		}
		return array_keys( $components_register ) ?? array();
	}

	/**
	 * Resolves module strings or slugs into active fully qualified class names targets.
	 *
	 * @param string $module The classname slug or Fully Qualified Classname of a module.
	 * @return string|false The validated target FQCN class string, false on mismatch.
	 */
	private function get_module_fqcn( string $module ): string|false {
		$module_fqcn = $module;
		if ( ! str_starts_with( $module, $this->modules_namespace ) ) {
			$module_fqcn = $this->trailingbackwardslashit( $this->modules_namespace ) . $module;
		}
		if ( ClassParser::is_implementation_of( $module_fqcn, ModuleInterface::class ) ) {
			return $module_fqcn;
		}
		return false;
	}

	/**
	 * Forces a physical disk rescan to compile and freeze a fresh framework configuration blueprint.
	 *
	 * This method meant only for onboarging onboarding new modules and features in a setting page.
	 *
	 * @since  1.0.0
	 * @return array<string, array> The complete actualized array of module FQCNs with their verified feature FQCNs.
	 */
	public function rebuild(): array {
		$components_register = ( new FinderService( $this->plugin ) )->find_modules_and_features();

		if ( ! $components_register ) {
			return $this->components_register;
		}

		$this->save( Register::COMPONENTS, $components_register );

		return $components_register;
	}

	/**
	 * Filters a component matrix dynamically against target environment, deployment tier,
	 * and DB activations.
	 *
	 * @since  1.0.0
	 * @param  array<string, array> $components Array of Fully Qualified Class Names of features of a module.
	 * @return array<string, array> Cleaned layout matrix containing only active and authorized targets.
	 */
	private function filter( array $components ): array {
		$components_register = array();

		foreach ( $components as $module_fqcn => $features_fqcn ) {

			if ( ! $this->is_enabled( $module_fqcn )
			|| ! $this->current_orientation_allowed( $module_fqcn )
			|| ! $this->current_deployment_allowed( $module_fqcn )
			|| $this->awaits_activation_confirmation( $module_fqcn )
			) {
				continue;
			}

			$features = array();
			foreach ( $features_fqcn as $feature_fqcn ) {

				if ( ! $this->is_enabled( $feature_fqcn )
				|| ! $this->current_orientation_allowed( $feature_fqcn )
				|| ! $this->current_deployment_allowed( $feature_fqcn )
				|| $this->awaits_activation_confirmation( $feature_fqcn )
				) {
					continue;
				}

				$features[] = $feature_fqcn;
			}

			$components_register[ $module_fqcn ] = $features;
		}

		return $components_register;
	}

	/**
	 * Check if a module or feature is allowed to run on current orientation (frontend/admin).
	 *
	 * @param string $fqcn Fully Qualified Class Names of a module or feature.
	 * @return bool        True if allowed to run in current orientation, false otherwise.
	 */
	private function current_orientation_allowed( string $fqcn ): bool {

		$orientation = $fqcn::get_orientation();

		return $this->current_orientation === $orientation || Orientation::BOTH === $orientation;
	}

	/**
	 * Check if a component is authorized to execute within the active deployment tier infrastructure.
	 *
	 * Reads the component's status from its meta, so call it after init.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of the target element.
	 * @return bool         True if authorized, false otherwise.
	 */
	private function current_deployment_allowed( string $fqcn ): bool {
		return $this->get_effective_deployment( $fqcn )->allows( $this->current_deployment );
	}

	/**
	 * Returns up to which server tier a component runs: the lower of its status and its deployment.
	 *
	 * The status (in the code, set by the release) is a hard limit; the deployment ("Draait op") is
	 * where this site lets it run. Reads the component's meta, so call it after init.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of a module or feature.
	 * @return Deployment The tier up to which it runs.
	 */
	public function get_effective_deployment( string $fqcn ): Deployment {
		$deployment = Deployment::tryFrom( $this->get_deployment( $fqcn ) ) ?? $this->default_deployment;
		$status     = method_exists( $fqcn, 'get_status' ) ? $fqcn::get_status() : $this->default_deployment;

		return $status->lowest_with( $deployment );
	}

	/**
	 * Gets the deployment status of a specific component.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of the target component.
	 * @return string       The deployment value (development, staging, production) of the component's deployment status.
	 */
	public function get_deployment( string $fqcn ): string {
		if ( null === $this->components_deployment || ! is_array( $this->components_deployment ) ) {
			$this->components_deployment = $this->get_register( Register::DEPLOYMENT );
		}

		return array_key_exists( $fqcn, $this->components_deployment )
			? $this->components_deployment[ $fqcn ]
			: $this->default_deployment->value;
	}

	/**
	 * Check if a module or feature is enabled to launch.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of a module or feature.
	 * @return bool         True if enabled, false otherwise.
	 */
	public function is_enabled( string $fqcn ): bool {
		if ( null === $this->enabled_components || ! is_array( $this->enabled_components ) ) {
			$this->enabled_components = $this->get_register( Register::ENABLED );
		}

		return array_key_exists( $fqcn, $this->enabled_components );
	}

	/**
	 * Checks if a module or feature waits for an administrator to confirm its activation.
	 *
	 * That is the case when it asks for confirmation (ComponentMeta::$confirm_activation), nobody
	 * decided on it on this site yet, and its deployment tier lets it run on this server. Until then
	 * it does not launch, even when the register enables it (as a register file from another site may do).
	 * Reads the component's meta, so call it after init.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of a module or feature.
	 * @return bool         True if it waits for confirmation, false otherwise.
	 */
	public function awaits_activation_confirmation( string $fqcn ): bool {
		if ( ! class_exists( $fqcn ) || ! method_exists( $fqcn, 'needs_activation_confirmation' ) ) {
			return false;
		}

		return $fqcn::needs_activation_confirmation()
			&& ! array_key_exists( $fqcn, $this->get_activation_decisions() )
			&& $this->current_deployment_allowed( $fqcn );
	}

	/**
	 * Returns the modules and features that wait for an administrator to confirm their activation.
	 *
	 * @since  1.0.0
	 * @return string[] Fully qualified class names.
	 */
	public function get_components_awaiting_confirmation(): array {
		$components = $this->get_register( Register::COMPONENTS );

		return array_values(
			array_filter(
				$this->flatten_components( $components ),
				array( $this, 'awaits_activation_confirmation' )
			)
		);
	}

	/**
	 * Records an administrator's answer to the activation question of a module or feature.
	 *
	 * Switching a feature on switches its module on too, otherwise it still would not run.
	 * Either answer is final: the question is not asked again.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn   Fully Qualified Class Name of a module or feature.
	 * @param  bool   $enable True to switch it on, false to leave it off.
	 * @return void
	 */
	public function decide_activation( string $fqcn, bool $enable ): void {
		$enabled_components = $this->get_register( Register::ENABLED );

		if ( $enable ) {
			$enabled_components[ $fqcn ] = true;

			$module_fqcn = $this->get_module_of_feature( $fqcn );
			if ( false !== $module_fqcn ) {
				$enabled_components[ $module_fqcn ] = true;
			}
		} else {
			unset( $enabled_components[ $fqcn ] );
		}

		$this->save( Register::ENABLED, $enabled_components );
		$this->mark_activation_decided( array( $fqcn ) );
	}

	/**
	 * Records that the activation of the given modules and features was decided on, as their checkboxes
	 * on the Feature Activation tab were saved.
	 *
	 * @since  1.0.0
	 * @param  string[] $fqcns Fully qualified class names.
	 * @return void
	 */
	public function mark_activation_decided( array $fqcns ): void {
		if ( empty( $fqcns ) ) {
			return;
		}

		$decisions = $this->get_activation_decisions();
		foreach ( $fqcns as $fqcn ) {
			$decisions[ $fqcn ] = true;
		}

		$this->plugin->settings->save( self::ACTIVATION_DECIDED_SETTING, $decisions );
		$this->activation_decided = $decisions;
	}

	/**
	 * Forgets every activation decision, so components that ask for confirmation ask again.
	 *
	 * Used by the factory reset, which switches everything on.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function reset_activation_decisions(): void {
		$this->plugin->settings->delete( self::ACTIVATION_DECIDED_SETTING );
		$this->activation_decided = array();
	}

	/**
	 * Returns the components whose activation was decided on this site.
	 *
	 * @since  1.0.0
	 * @return array<string, bool> FQCN => true.
	 */
	private function get_activation_decisions(): array {
		if ( null === $this->activation_decided ) {
			$decisions                = $this->plugin->settings->get( self::ACTIVATION_DECIDED_SETTING, default: array() );
			$this->activation_decided = is_array( $decisions ) ? $decisions : array();
		}

		return $this->activation_decided;
	}

	/**
	 * Returns the module a feature belongs to.
	 *
	 * @since  1.0.0
	 * @param  string $feature_fqcn Fully Qualified Class Name of a feature.
	 * @return string|false         The module's class name, or false for a module or an unknown class.
	 */
	public function get_module_of_feature( string $feature_fqcn ): string|false {
		foreach ( $this->get_register( Register::COMPONENTS ) as $module_fqcn => $features_fqcn ) {
			if ( in_array( $feature_fqcn, (array) $features_fqcn, true ) ) {
				return $module_fqcn;
			}
		}

		return false;
	}

	/**
	 * Returns the name of a component for places where its module is not shown around it.
	 *
	 * Feature names are short and only complete within their module ("Menu Builder" under
	 * Admin Toolbar), so outside that context a feature gets its module in brackets:
	 * "Menu Builder (Admin Toolbar)". A module, or a class that is not a component, keeps its
	 * own name.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of a module or feature.
	 * @return string The name (not escaped), or the class name when it has none.
	 */
	public function get_display_name( string $fqcn ): string {
		if ( ! class_exists( $fqcn ) || ! method_exists( $fqcn, 'get_name' ) ) {
			return $fqcn;
		}

		$module_fqcn = $this->get_module_of_feature( $fqcn );
		if ( false === $module_fqcn ) {
			return $fqcn::get_name();
		}

		/* translators: 1: feature name, 2: module name. */
		return sprintf( __( '%1$s (%2$s)', 'core-functionality' ), $fqcn::get_name(), $module_fqcn::get_name() );
	}

	/**
	 * Gets the component register runtime cache or hydrates the module memory registry
	 * either from compiled cache or via disk iteration.
	 *
	 * @since  1.0.0
	 * @return array A complete and validated register of all modules and features in this plugin.
	 */
	private function get_components_register(): array {

		if ( ! empty( $this->components_register ) ) {
			return $this->components_register;
		}

		// fill the runtime cache from component-register settings or file.
		$this->components_register = $this->get_register( Register::COMPONENTS );

		// Still empty after reading the components register file?
		if ( empty( $this->components_register ) ) {

			// The components register file must be empty or not found.
			$this->plugin->notices->add( 'error', __( 'Components register file was empty or not found. Please inform support.' ) );

			// if component-register file was empty, try to rebuild the cache by scanning files.
			$this->components_register = $this->rebuild();

			if ( empty( $this->components_register ) ) {
				$this->plugin->notices->add( 'error', __( 'Something went wrong rebuilding the components registry. No component was found. Please inform support!' ) );
			}
		}

		return is_array( $this->components_register ) ? $this->components_register : array();
	}

	/**
	 * Serializes and commits register changes seamlessly toward
	 * both database options and physical files.
	 *
	 * @since  1.0.0
	 * @param  Register $register      Enum tracking the targeted architectural register.
	 * @param  array    $register_data The structured payload data matrix tree to persist.
	 * @return string|bool             False if neither the option nor the file was written; otherwise the file's
	 *                                 success message, or true when only the option was written.
	 */
	public function save( Register $register, array $register_data ): string|bool {
		if ( empty( $register_data ) ) {
			return true;
		}

		$settings_key = $this->get_register_setting_key( $register );

		// Perform specific data restructuring schemas if the target is
		// the administrative activation list.
		if ( Register::ENABLED === $register ) {
			$register_data = $this->validate_and_restructure_enabled_list( $register_data );
		}

		// update_option() also answers false when the value did not change; that counts as saved.
		$settings_written = $this->plugin->settings->save( $settings_key, $register_data )
			|| $this->plugin->settings->get( $settings_key, default: null ) === $register_data;

		// Commit directly toward the physical file blueprints (The Git Source of Truth).
		$file_written = $this->plugin->components_register_files->save_register( $register, $register_data );

		// Flush and refresh internal runtime properties instantly to prevent lifecycle lag.
		$this->cache_refresh( $register, $register_data );

		// A changed deployment can make a component available on this server.
		if ( Register::DEPLOYMENT === $register ) {
			$this->request_availability_check();
		}

		if ( false === $settings_written && false === $file_written ) {
			return false;
		}

		// The file is a fallback for the option; the option alone is a successful save.
		return false === $file_written ? true : $file_written;
	}

	/**
	 * Reforms and validates flat incoming form arrays into a high-performance hash layout (FQCN => true).
	 *
	 * Ensures lookups via array_key_exists execute at instant O(1) performance speeds.
	 *
	 * @since  1.0.0
	 * @param  array<int, string> $form_enabled_components Flat array payload matching submitted checkboxes.
	 * @return array<string, true>                         The array of enabled components, with fqcn as key-value.
	 */
	public function validate_and_restructure_enabled_list( array $form_enabled_components ): array {

		// Just in case the array is already restructured dynamically during an upstream request lifecycle.
		if ( ! is_int( array_key_first( $form_enabled_components ) ) ) {
			return $form_enabled_components;
		}

		$components_register = $this->get_register( Register::COMPONENTS );
		$enabled_components  = array();

		foreach ( $components_register as $module_fqcn => $features_fqcn ) {

			// If the module itself was checked/enabled in the form, add its full FQCN string.
			if ( ! in_array( $module_fqcn, $form_enabled_components, true ) ) {
				// If a parent module is disabled, all nested sub-features are implicitly disabled too.
				continue;
			}

			$enabled_components[ $module_fqcn ] = true;

			if ( ! is_array( $features_fqcn ) ) {
				continue;
			}

			// Validate and loop through nested sub-features under the authorized parent shield.
			foreach ( $features_fqcn as $feature_fqcn ) {
				if ( ! in_array( $feature_fqcn, $form_enabled_components, true ) ) {
					continue;
				}
				$enabled_components[ $feature_fqcn ] = true;
			}
		}

		return $enabled_components;
	}

	/**
	 * Updates the in-memory copy of a register after it was saved.
	 *
	 * @since  1.0.0
	 * @param  Register $register The register that was saved.
	 * @param  array    $register_data The saved register data.
	 * @return void
	 */
	private function cache_refresh( Register $register, array $register_data ): void {

		// Hydrate the matching in-memory cache properties immediately upon a successful write operation.
		match ( $register ) {
			Register::COMPONENTS => $this->components_register   = $register_data,
			Register::ENABLED    => $this->enabled_components    = $register_data,
			Register::DEPLOYMENT => $this->components_deployment = $register_data,
			Register::FRONTEND   => $this->frontend_register     = $register_data,
			Register::ADMIN      => $this->admin_register        = $register_data,

			// Failsafe: Prevent UnhandledMatchError crashes if an unmapped Enum case passes through.
			default              => null,
		};
	}

	/**
	 * Perfoms some basic checks on the component register.
	 * - classes exist
	 * - implement the correct Interfaces
	 *
	 * @since  1.0.0
	 * @param  array<string, string[]> $components_register Module class name => feature class names.
	 * @return array<string, string[]> The register without classes that do not exist or implement the wrong interface.
	 */
	private function validate( array $components_register ): array {

		$new_register = array();

		foreach ( $components_register as $module_fqcn => $features_fqcn ) {

			$feature_slugs = $this->features_fqcn_to_slug( $features_fqcn );

			/**
			 * Use the ModuleClassValidator Value Object to validate the module
			 * and its features.
			 */
			$features = ModuleClassValidator::validate( $module_fqcn, $feature_slugs, $this->modules_namespace );

			// Did any features pass the validations?
			if ( false !== $features ) {
				$new_register[ $module_fqcn ] = $features;
			}
		}

		return $new_register;
	}

	/**
	 * Remove the namespaces from a flat array containing fqcns
	 *
	 * @param array $fqcns A flat array of Fully Qualified Class Names.
	 * @return array       The input, but without namespaces.
	 */
	private function features_fqcn_to_slug( array $fqcns ): array {
		// From each array item, keep the part after the last slash and remove the rest.
		return array_map(
			function ( $fqcn ) {
				return basename( str_replace( '\\', '/', $fqcn ) );
			},
			$fqcns
		);
	}

	/**
	 * Make sure the input string ends with a forward slash.
	 *
	 * @param string $path The path.
	 * @return string
	 */
	private function trailingslashit( string $path ): string {
		return $this->untrailingslashit( $path ) . '/';
	}

	/**
	 * Makes sure a namespace string ends with a double backward slash.
	 *
	 * @param string $namespace A string representing a namespace.
	 * @return string $namespace with a single double slash at the end.
	 */
	private function trailingbackwardslashit( string $namespace ): string {
		return $this->untrailingslashit( $this->untrailingslashit( $namespace ) ) . '\\';
	}

	/**
	 * Trims trailing forward- and backward slash from path.
	 *
	 * @param string $path The path.
	 * @return string
	 */
	private function untrailingslashit( string $path ): string {
		return rtrim( $path, '/\\' );
	}
}
