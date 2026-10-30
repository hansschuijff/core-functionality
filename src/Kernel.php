<?php
/**
 * Framework Lifecycle Kernel.
 *
 * @package DeWittePrins\CoreFunctionality
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DeWittePrins\CoreFunctionality\Services\InterDependenciesGuard;
use DeWittePrins\CoreFunctionality\Environment\Environment;
use DeWittePrins\CoreFunctionality\Environment\EnvironmentLookup;
use DeWittePrins\CoreFunctionality\Services\ComponentsRegister;
use DeWittePrins\CoreFunctionality\Environment\Themes\Services\ThemeInspector;

// Import global functions.
use function is_array;
use function array_merge;
use function array_key_exists;
use function apply_filters;
use function add_action;
use function do_action;
use function method_exists;

/**
 * Class Kernel
 *
 * Boots the plugin in one step: it takes the launchable modules and features from the
 * components register, keeps those whose environment is ready, creates them and calls
 * their launch() method.
 *
 * The components register already filters on activation, deployment tier and orientation;
 * the Kernel adds the environment dependencies and the interdependencies between features.
 *
 * @since 1.0.0
 */
class Kernel {

	/**
	 * The hook on which the Kernel boots.
	 *
	 * Booting on init means all plugins have loaded and the theme is set up, so the
	 * environment guards can check what the plugins and theme actually started.
	 *
	 * @var string
	 */
	private string $boot_hook = 'init';

	/**
	 * The priority of the boot callback.
	 *
	 * Priority 0 leaves features room to hook into init itself.
	 *
	 * @var int
	 */
	private int $boot_priority = 0;

	/**
	 * Live module and feature objects, keyed by their fully qualified class name.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Whether the boot cycle has completed.
	 *
	 * @var bool
	 */
	private bool $did_launch = false;

	/**
	 * Kernel Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin                 $plugin                Global main plugin container shell.
	 * @param InterDependenciesGuard $interdependency_guard Shared interdependency checker service.
	 * @param Environment            $environment           Unified environment evaluator service.
	 * @param ComponentsRegister     $components_register   Register of launchable modules and features.
	 */
	public function __construct(
		private readonly Plugin $plugin,
		private readonly InterDependenciesGuard $interdependency_guard,
		private readonly Environment $environment,
		private readonly ComponentsRegister $components_register,
	) {
		$this->hook_boot();
	}

	/**
	 * Hooks the boot callback on the (filtered) boot timing.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function hook_boot(): void {
		$default_timing = array(
			'hook'     => $this->boot_hook,
			'priority' => $this->boot_priority,
		);

		/**
		 * Filters the moment the Kernel boots the modules and features.
		 *
		 * @since 1.0.0
		 * @param array{hook: string, priority: int} $default_timing Hook and priority (default init, 0).
		 */
		$timing = apply_filters( 'core-functionality/launch-timing', $default_timing );
		if ( ! is_array( $timing ) ) {
			$timing = $default_timing;
		}

		$this->boot_hook     = (string) ( $timing['hook'] ?? $this->boot_hook );
		$this->boot_priority = (int) ( $timing['priority'] ?? $this->boot_priority );

		add_action( $this->boot_hook, array( $this, 'boot' ), $this->boot_priority );
	}

	/**
	 * Boots all launchable modules and features and announces that the plugin is ready.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function boot(): void {
		if ( $this->did_launch ) {
			return;
		}

		$this->register_environment_nodes();
		$this->create_instances( $this->get_launchable_components() );
		$this->drop_instances_with_missing_features();
		$this->launch_instances();
		$this->launch_finished();
	}

	/**
	 * Lets every active environment guard feed its admin toolbar and menu nodes to the registers.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function register_environment_nodes(): void {
		foreach ( $this->get_active_environment_ids() as $dependency_id ) {
			$this->environment->is_ready( $dependency_id );
		}
	}

	/**
	 * Collects the ids of the core, plugins and themes from the environment index that are active.
	 *
	 * @since  1.0.0
	 * @return string[] Active environment ids.
	 */
	private function get_active_environment_ids(): array {
		$active_ids = array( 'wordpress-core' );

		$environment_index = $this->plugin->settings->get( 'environment-index', default: array() );
		if ( empty( $environment_index ) || ! is_array( $environment_index ) ) {
			return $active_ids;
		}

		return array_merge(
			$active_ids,
			$this->get_active_plugin_ids( $environment_index['plugins'] ?? array() ),
			$this->get_active_theme_ids( $environment_index['themes'] ?? array() )
		);
	}

	/**
	 * Returns the ids of the indexed plugins that are activated.
	 *
	 * @since  1.0.0
	 * @param  mixed $plugin_index Plugin id => plugin basename (or a list of basenames).
	 * @return string[] Active plugin ids.
	 */
	private function get_active_plugin_ids( mixed $plugin_index ): array {
		if ( ! is_array( $plugin_index ) || empty( $plugin_index ) ) {
			return array();
		}

		$active_plugins = $this->plugin->settings->get_unmanaged( 'active_plugins', array() );
		if ( ! is_array( $active_plugins ) ) {
			return array();
		}

		$ids_by_basename = array();
		foreach ( $plugin_index as $id => $entry ) {
			foreach ( EnvironmentLookup::to_basenames( $entry ) as $basename ) {
				$ids_by_basename[ $basename ] = $id;
			}
		}

		$active_ids = array();

		foreach ( $active_plugins as $basename ) {
			if ( array_key_exists( $basename, $ids_by_basename ) ) {
				$active_ids[] = (string) $ids_by_basename[ $basename ];
			}
		}

		return $active_ids;
	}

	/**
	 * Returns the ids (with the -theme suffix) of the indexed themes that are active.
	 *
	 * @since  1.0.0
	 * @param  mixed $theme_index Theme id => array{name, author}.
	 * @return string[] Active theme ids.
	 */
	private function get_active_theme_ids( mixed $theme_index ): array {
		if ( ! is_array( $theme_index ) || empty( $theme_index ) ) {
			return array();
		}

		$active_ids = array();
		foreach ( $theme_index as $id => $theme ) {
			if ( is_array( $theme ) && ThemeInspector::is_theme_active( $theme ) ) {
				$active_ids[] = $id . '-theme';
			}
		}

		return $active_ids;
	}

	/**
	 * Returns the modules and features from the register whose environment dependencies are ready.
	 *
	 * A feature is only kept when its module is kept.
	 *
	 * @since  1.0.0
	 * @return array<string, string[]> Module class name => feature class names.
	 */
	private function get_launchable_components(): array {
		$register = $this->components_register->get();
		if ( empty( $register ) || ! is_array( $register ) ) {
			return array();
		}

		$launchable = array();
		foreach ( $register as $module_fqcn => $features_fqcn ) {
			if ( ! $this->environment_is_ready( $module_fqcn ) ) {
				continue;
			}

			$launchable[ $module_fqcn ] = array();
			foreach ( (array) $features_fqcn as $feature_fqcn ) {
				if ( $this->environment_is_ready( $feature_fqcn ) ) {
					$launchable[ $module_fqcn ][] = $feature_fqcn;
				}
			}
		}

		return $launchable;
	}

	/**
	 * Checks the environment dependencies of a module or feature.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully qualified class name of a module or feature.
	 * @return bool         True if there are no dependencies or all are ready, false otherwise.
	 */
	private function environment_is_ready( string $fqcn ): bool {
		$dependencies = $fqcn::get_dependencies();
		if ( empty( $dependencies ) ) {
			return true;
		}

		return $this->environment->is_ready( $dependencies );
	}

	/**
	 * Creates the module objects and, for each module, its feature objects.
	 *
	 * @since  1.0.0
	 * @param  array<string, string[]> $components Module class name => feature class names.
	 * @return void
	 */
	private function create_instances( array $components ): void {
		foreach ( $components as $module_fqcn => $features_fqcn ) {
			$module = $this->instances[ $module_fqcn ] ?? new $module_fqcn( $this->plugin );

			$this->instances[ $module_fqcn ] = $module;

			foreach ( $features_fqcn as $feature_fqcn ) {
				$this->instances[ $feature_fqcn ] ??= new $feature_fqcn( $module, $this->plugin );
			}
		}
	}

	/**
	 * Removes features whose required features (uses_features()) did not make it.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function drop_instances_with_missing_features(): void {
		$this->instances = $this->interdependency_guard->clean_missing_interdependencies( $this->instances );
	}

	/**
	 * Calls launch() on every module and feature, then on the plugin.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function launch_instances(): void {
		foreach ( $this->instances as $instance ) {
			if ( method_exists( $instance, 'launch' ) ) {
				$instance->launch();
			}
		}

		$this->plugin->launch();
	}

	/**
	 * Marks the boot as done and tells the world the plugin is ready for normal service.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function launch_finished(): void {
		$this->did_launch = true;
		$this->plugin->set_ready();

		do_action( 'core-functionality/ready' );
	}

	/**
	 * Returns a live module or feature object.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully qualified class name.
	 * @return object|null  The object, or null if it was not launched.
	 */
	public function get_instance( string $fqcn ): ?object {
		return $this->instances[ $fqcn ] ?? null;
	}
}
