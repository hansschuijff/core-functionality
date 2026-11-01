<?php
/**
 * Legacy Handover Service.
 *
 * Temporary: remove this folder and its call in core-functionality.php together with the legacy plugin.
 *
 * @package DeWittePrins\CoreFunctionality\Migration
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0-only
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Modules\ActiveWoo\Features\KeepLicenseAlive\KeepLicenseAlive;
use DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\Cleaner\Cleaner;
use DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\MenuBuilder\MenuBuilder;
use DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\StylesFixer\StylesFixer;
use DeWittePrins\CoreFunctionality\Modules\AdminToolbar\Features\Visualizer\Visualizer;
use DeWittePrins\CoreFunctionality\Modules\WooCommerce\Features\BusinessInvoiceTotals\BusinessInvoiceTotals;
use DeWittePrins\CoreFunctionality\Modules\WooCommerce\Features\InvoiceDetails\InvoiceDetails;
use DeWittePrins\CoreFunctionality\Modules\WooCommerce\Features\ShortBulkActionLabels\ShortBulkActionLabels;
use DeWittePrins\CoreFunctionality\Plugin;

use function add_action;
use function add_filter;
use function array_search;
use function array_splice;
use function array_values;
use function in_array;
use function is_array;
use function plugin_basename;
use function remove_filter;
use function str_contains;
use function strrpos;
use function substr;

/**
 * Class LegacyHandover
 *
 * Lets the legacy plugin (Core Functionality Legacy) step aside for every feature of this plugin that
 * has taken over a part of it, so the two plugins can run side by side without doing things twice.
 *
 * The register (TAKEN_OVER) names per feature the legacy files it replaces as a whole, and the single
 * legacy callbacks it replaces where it takes over only a part of a file. Files are stopped through the
 * legacy filter core-functionality-legacy/load-file, which runs while the legacy plugin loads, so long
 * before the Kernel boots: whether a feature is on is then read from the release settings and the
 * register of enabled components (dependencies on other plugins are not checked yet at that moment).
 * Callbacks are removed once the features are launched, and only for features that really launched.
 *
 * WordPress loads the active plugins in the order of the active_plugins option, which it sorts by name,
 * and core-functionality-legacy/ sorts before core-functionality/. So this class also keeps this plugin
 * directly in front of the legacy plugin in that option.
 *
 * @since 1.0.0
 */
class LegacyHandover {

	/**
	 * Basename of the legacy plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const LEGACY_BASENAME = 'core-functionality-legacy/core-functionality.php';

	/**
	 * What each feature takes over from the legacy plugin.
	 *
	 * Per feature: 'files', legacy files relative to the legacy src/ folder without .php, and/or
	 * 'callbacks', legacy callbacks as array( hook, callback, priority ). Only use 'files' for a
	 * feature that does not ask for confirmation of its activation: that answer can only be read
	 * after init, when the legacy files have long been loaded.
	 *
	 * @since 1.0.0
	 * @var array<class-string, array{files?: string[], callbacks?: array<int, array{0: string, 1: string, 2: int}>}>
	 */
	private const TAKEN_OVER = array(
		KeepLicenseAlive::class      => array(
			'files' => array( 'activewoo/license' ),
		),
		Cleaner::class               => array(
			'files' => array( 'admin-nav/src/admin-toolbar/admin-bar-cleanup' ),
		),
		MenuBuilder::class           => array(
			'files' => array( 'admin-nav/src/admin-toolbar/add-shortcuts-to-admin-bar' ),
		),
		StylesFixer::class           => array(
			'files' => array( 'admin-nav/src/admin-toolbar/fix-admin-bar-styles' ),
		),
		Visualizer::class            => array(
			'files' => array( 'admin-nav/src/admin-toolbar/list-all-admin-bar-node-ids' ),
		),
		ShortBulkActionLabels::class => array(
			// WooCommerce has its own mark_cancelled bulk action, so the legacy one is not ported.
			'files' => array( 'woocommerce/src/bulk-order-cancellation' ),
		),
		// The legacy PDF file is split over these two; both are meant to be on wherever the shop runs.
		BusinessInvoiceTotals::class => array(
			'files' => array( 'woocommerce/src/pdf-invoice-customizations' ),
		),
		InvoiceDetails::class        => array(
			'files' => array( 'woocommerce/src/pdf-invoice-customizations' ),
		),
	);

	/**
	 * The legacy files that switched-on features have taken over, once collected.
	 *
	 * @since 1.0.0
	 * @var string[]|null
	 */
	private ?array $taken_over_files = null;

	/**
	 * LegacyHandover Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The central plugin instance.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {}

	/**
	 * Hooks the handover into WordPress and the legacy plugin.
	 *
	 * Does nothing when the plugin did not start its services (emergency halt): the legacy plugin then
	 * simply loads everything.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register(): void {
		if ( ! isset( $this->plugin->components_register, $this->plugin->kernel ) ) {
			return;
		}

		add_filter( 'core-functionality-legacy/load-file', array( $this, 'filter_legacy_file' ), 10, 2 );
		add_action( 'core-functionality/ready', array( $this, 'remove_taken_over_callbacks' ) );
		add_filter( 'pre_update_option_active_plugins', array( $this, 'load_before_legacy' ) );

		$this->keep_load_order();
	}

	/**
	 * Stops a legacy file that a switched-on feature has taken over.
	 *
	 * @since  1.0.0
	 * @param  mixed  $may_load Whether the file may load.
	 * @param  string $file     The legacy file, relative to its src/ folder and without .php.
	 * @return bool False for a taken-over file, otherwise the value it got.
	 */
	public function filter_legacy_file( mixed $may_load, string $file ): bool {
		if ( ! $may_load ) {
			return false;
		}

		return ! in_array( $file, $this->get_taken_over_files(), true );
	}

	/**
	 * Removes the legacy callbacks that launched features have taken over.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function remove_taken_over_callbacks(): void {
		foreach ( self::TAKEN_OVER as $feature => $taken_over ) {
			if ( empty( $taken_over['callbacks'] ) || null === $this->plugin->kernel->get_instance( $feature ) ) {
				continue;
			}

			foreach ( $taken_over['callbacks'] as $callback ) {
				remove_filter( $callback[0], $callback[1], $callback[2] );
			}
		}
	}

	/**
	 * Puts this plugin directly in front of the legacy plugin whenever the active plugins are saved.
	 *
	 * @since  1.0.0
	 * @param  mixed $plugins The basenames of the active plugins about to be saved.
	 * @return mixed The basenames, with this plugin in front of the legacy plugin.
	 */
	public function load_before_legacy( mixed $plugins ): mixed {
		if ( ! is_array( $plugins ) ) {
			return $plugins;
		}

		$plugins       = array_values( $plugins );
		$own_index     = array_search( $this->get_own_basename(), $plugins, true );
		$legacy_index  = array_search( self::LEGACY_BASENAME, $plugins, true );
		$is_misordered = false !== $own_index && false !== $legacy_index && $own_index > $legacy_index;
		if ( ! $is_misordered ) {
			return $plugins;
		}

		$own_basename = $plugins[ $own_index ];
		array_splice( $plugins, $own_index, 1 );
		array_splice( $plugins, $legacy_index, 0, array( $own_basename ) );

		return $plugins;
	}

	/**
	 * Saves the active plugins again when the legacy plugin still loads first.
	 *
	 * The save passes load_before_legacy(), so from the next request on this plugin loads first.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function keep_load_order(): void {
		$plugins = $this->plugin->settings->get_unmanaged( 'active_plugins', array() );
		if ( ! is_array( $plugins ) ) {
			return;
		}

		$current = array_values( $plugins );
		$ordered = $this->load_before_legacy( $current );
		if ( $current !== $ordered ) {
			$this->plugin->settings->save_unmanaged( 'active_plugins', $ordered );
		}
	}

	/**
	 * Returns the legacy files that switched-on features have taken over.
	 *
	 * @since  1.0.0
	 * @return string[] Legacy files, relative to its src/ folder and without .php.
	 */
	private function get_taken_over_files(): array {
		if ( null !== $this->taken_over_files ) {
			return $this->taken_over_files;
		}

		$this->taken_over_files = array();
		foreach ( self::TAKEN_OVER as $feature => $taken_over ) {
			if ( empty( $taken_over['files'] ) || ! $this->is_feature_switched_on( $feature ) ) {
				continue;
			}

			foreach ( $taken_over['files'] as $file ) {
				$this->taken_over_files[] = $file;
			}
		}

		return $this->taken_over_files;
	}

	/**
	 * Checks whether a feature and its module are switched on, without reading their meta.
	 *
	 * The meta holds translated names, so it may not be read before init.
	 *
	 * @since  1.0.0
	 * @param  string $feature Fully Qualified Class Name of the feature.
	 * @return bool True when both the feature and its module are switched on.
	 */
	private function is_feature_switched_on( string $feature ): bool {
		return $this->is_component_switched_on( $this->get_module_of( $feature ) )
			&& $this->is_component_switched_on( $feature );
	}

	/**
	 * Checks whether a module or feature is switched on: available here, and on by its rule or enabled.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Fully Qualified Class Name of a module or feature.
	 * @return bool True when it is switched on.
	 */
	private function is_component_switched_on( string $fqcn ): bool {
		$register = $this->plugin->components_register;

		return $register->is_available_here( $fqcn )
			&& ( $register->is_automatically_on( $fqcn ) || $register->is_enabled( $fqcn ) );
	}

	/**
	 * Returns the module of a feature from its namespace.
	 *
	 * A feature lives in Modules\<Module>\Features\<Feature>, its module is Modules\<Module>\<Module>.
	 *
	 * @since  1.0.0
	 * @param  string $feature Fully Qualified Class Name of the feature.
	 * @return string Fully Qualified Class Name of its module.
	 */
	private function get_module_of( string $feature ): string {
		if ( ! str_contains( $feature, '\\Features\\' ) ) {
			return $feature;
		}

		$module_namespace = substr( $feature, 0, (int) strrpos( $feature, '\\Features\\' ) );
		$module_name      = substr( $module_namespace, (int) strrpos( $module_namespace, '\\' ) + 1 );

		return $module_namespace . '\\' . $module_name;
	}

	/**
	 * Returns the basename of this plugin, as stored in the active plugins.
	 *
	 * @since  1.0.0
	 * @return string The basename, such as core-functionality/core-functionality.php.
	 */
	private function get_own_basename(): string {
		return plugin_basename( $this->plugin->get_data( 'plugin-dir' ) . 'core-functionality.php' );
	}
}
