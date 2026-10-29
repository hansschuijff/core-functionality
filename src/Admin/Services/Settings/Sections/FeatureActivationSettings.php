<?php
/**
 * Core Feature Activation Settings Section Component.
 *
 * @package DeWittePrins\CoreFunctionality\Admin\Services\Settings\Sections
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Admin\Services\Settings\Sections;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_Error;
use DeWittePrins\CoreFunctionality\Plugin;
use DeWittePrins\CoreFunctionality\Enums\Register;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\AdminSettingsPage;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;

use function __;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function sanitize_key;
use function sanitize_text_field;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Class FeatureActivationSettings
 */
class FeatureActivationSettings implements SettingsSectionInterface {

	/**
	 * Default setting for is_enabled used at factory reset.
	 *
	 * @var bool
	 */
	private bool $default_enabled_setting = true;

	/**
	 * FeatureActivationSettings Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The central plugin class instance acting as a provider.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {}

	/**
	 * URL slug identifier.
	 */
	#[Override]
	public static function get_id(): string {
		return 'cf-feature-activation';
	}

	/**
	 * Returns an icon for the admin settings menu.
	 *
	 * @return string.
	 */
	#[Override]
	public static function get_icon(): string {
		return 'dashicons-buddicons-replies';
	}

	/**
	 * Navigation title.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'Feature Activation', 'core-functionality' );
	}

	/**
	 * Short description to explain to users what this section is for.
	 *
	 * @return string The description of this Settings Section.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'Here you can enable and disable the modules and features in this plugin. Refreshing this list, will perform a scan that automatically detect new modules and features if there are any. By default new modules and features will be disabled en need to be enabled to launch them.', 'core-functionality' );
	}

	/**
	 * Returns the absolute stylesheet URLs for this specific section.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Array matching unique handles to asset URLs.
	 */
	#[Override]
	public static function get_stylesheets(): array {
		// $base_url = plugins_url( 'assets/css/', __FILE__ );

		return array(
			// 'dwp-cf-feature-activation-css' => $base_url . 'feature-activation.css',
		);
	}

	/**
	 * Returns the absolute asset URLs for this specific section.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Array matching unique handles to asset URLs.
	 */
	#[Override]
	public static function get_scripts(): array {
		$base_url = plugins_url( 'assets/js/', __FILE__ );

		return array(
			'dwp-cf-feature-activation-js' => $base_url . 'feature-activation.js',
		);
	}

	/**
	 * Outputs the wrapping HTML matrix shell.
	 *
	 * @param bool $reset_to_defaults Optional. Force bypass of database overrides. Default false.
	 */
	#[Override]
	public function render( bool $reset_to_defaults = false ): void {

		$this->render_section_start();

		$registered_modules = $this->plugin->components_register->get_register( Register::COMPONENTS );
		if ( ! is_array( $registered_modules ) ) {
			return;
		}

		foreach ( $registered_modules as $module_fqcn => $features_fqcn ) {

			$this->render_module_start( $module_fqcn, $reset_to_defaults );

			if ( ! is_array( $features_fqcn ) || empty( $features_fqcn ) ) {
				continue;
			}

			$this->render_features_heading();

			foreach ( $features_fqcn as $feature_fqcn ) {

				$this->render_feature( $feature_fqcn, $reset_to_defaults );
			}

			$this->render_module_end();
		}

		$this->render_section_end();
	}

	/**
	 * Opens the list of modules and features.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_section_start() {
		$button_text_select_everything = esc_html__( 'Select all', 'core-functionality' );
		echo <<<HTML
			<div class="dewitteprins-global-actions" style="margin: 20px 0; display: flex; gap: 10px; align-items: center;">
				<button type="button" class="button button-secondary js-dwp-toggle-all" data-state="select">
					{$button_text_select_everything}
				</button>
			</div>
			<div class="dwp-modules-grid">
		HTML;
	}

	/**
	 * Opens a module block and renders its checkbox.
	 *
	 * @since  1.0.0
	 * @param  string $module_fqcn Fully qualified class name of the module.
	 * @param  bool   $reset_to_defaults Whether to show the default values instead of the saved ones.
	 * @return void
	 */
	private function render_module_start( string $module_fqcn, bool $reset_to_defaults ): void {

		$module_name    = esc_html( $module_fqcn::get_name() );
		$module_enabled = $reset_to_defaults
			? $this->default_enabled_setting
			: $this->plugin->components_register->is_enabled( $module_fqcn );
		$module_class   = esc_attr( $module_fqcn );
		$is_enabled     = checked( $module_enabled, true, false );

		echo <<<HTML
			<div class="dwp-module-card" style="border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; background: #fff; max-width: 800px;">
				<!-- module -->
				<label style="font-size: 15px; font-weight: bold; display: block; margin-bottom: 5px;">
					<input type="checkbox" class="js-dwp-module-toggle" name="dwp_active_classes[{$module_fqcn}]" value="{$module_class}" {$is_enabled} />
					{$module_name}
				</label>
				<?php
		HTML;
	}

	/**
	 * Renders the heading above the features of a module.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_features_heading(): void {

		$available_features_text = esc_html_e( 'Available features:', 'core-functionality' );

		echo <<<HTML
			<!-- features -->
			<div class="js-dwp-features-container" style="margin-left: 25px; border-left: 2px solid #2271b1; padding-left: 15px; margin-top: 10px;">
				<p style="font-weight: 600; margin-top: 0; margin-bottom: 10px;">{$available_features_text}</p>
		HTML;
	}

	/**
	 * Renders the checkbox of a feature.
	 *
	 * @since  1.0.0
	 * @param  string $feature_fqcn Fully qualified class name of the feature.
	 * @param  bool   $reset_to_defaults Whether to show the default values instead of the saved ones.
	 * @return void
	 */
	private function render_feature( string $feature_fqcn, bool $reset_to_defaults ): void {
		$feature_name    = $feature_fqcn::get_name();
		$feature_enabled = $reset_to_defaults
			? $this->default_enabled_setting
			: $this->plugin->components_register->is_enabled( $feature_fqcn );
		$is_enabled      = checked( $feature_enabled, true, false );
		$feature_fqcn    = esc_attr( $feature_fqcn );
		$feature_name    = esc_html( $feature_name );

		echo <<<HTML
			<label style="display: block; margin-bottom: 8px;">
				<input type="checkbox" class="js-dwp-feature-toggle" name="dwp_active_classes[{$feature_fqcn}]" value="{$feature_fqcn}" {$is_enabled} />
				{$feature_name}
			</label>
		HTML;
	}

	/**
	 * Closes a module block.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_module_end(): void {
		echo <<<HTML
				</div>
			</div>
		HTML;
	}

	/**
	 * Closes the list of modules and features.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_section_end() {
		echo <<<HTML
			</div>
		HTML;
	}

	/**
	 * Pre-flight validation gate checking inputs before database insertion.
	 *
	 * @param array $posted_data The form data that was submitted.
	 */
	#[Override]
	public function validate( array $posted_data ): bool|WP_Error {
		return true;
	}

	/**
	 * Single point of entry for data mutations, routing requests to specific save or reset sub-routines.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data Raw $_POST context forwarded by the host controller.
	 * @return string|bool        The translatable success message string on success, false on failure.
	 */
	#[Override]
	public function save( array $posted_data ): string|bool {
		// 1. Check of de specifieke rescan knop van deze sectie is ingedrukt
		if ( isset( $posted_data['dwp_cf_rescan'] ) && '1' === $posted_data['dwp_cf_rescan'] ) {
			return $this->save_rescan();
		}

		// 2. De globale factory reset (aangestuurd door de paginafabriek)
		if ( isset( $posted_data['dwp_cf_reset_defaults'] ) && '1' === $posted_data['dwp_cf_reset_defaults'] ) {
			return $this->save_factory_reset();
		}

		// 3. Regulier opslaan (vinkjes bijwerken)
		return $this->save_normal( $posted_data );
	}

	/**
	 * Executes the standard save routine for active classes.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data Sanitized post data.
	 * @return string|bool
	 */
	private function save_normal( array $posted_data ): string|bool {
		$raw_input_classes = isset( $posted_data['dwp_active_classes'] ) && is_array( $posted_data['dwp_active_classes'] ) ? $posted_data['dwp_active_classes'] : array();
		$submitted_classes = wp_unslash( $raw_input_classes );

		$sanitized_classes = array();
		foreach ( $submitted_classes as $class_name ) {
			$sanitized_classes[] = sanitize_text_field( $class_name );
		}

		$this->plugin->components_register->save( Register::ENABLED, $sanitized_classes );

		return true;
	}

	/**
	 * Rescan the files for new modules and features and perform a factory reset.
	 *
	 * @since  1.0.0
	 * @return string|bool
	 */
	private function save_factory_reset(): string|bool {

		$enabled_components = $this->get_initial_enabled_components_array();

		$this->plugin->components_register->save( Register::ENABLED, $enabled_components );

		return __( 'All modules and features where reset to their default settings.', 'core-functionality' );
	}

	/**
	 * Returns the enabled components as they are after a reset: every module and feature in the register.
	 *
	 * @since  1.0.0
	 * @return string[] Fully qualified class names.
	 */
	private function get_initial_enabled_components_array() {
		$components_register = $this->plugin->components_register->get_register( Register::COMPONENTS );
		// Built a flat array with all the classes in $components_register..
		$enabled_components = array();
		foreach ( $components_register as $module_fqcn => $features_fqcn ) {
			$enabled_components[ $module_fqcn ] = true;
			foreach ( $features_fqcn as $feature_fqcn ) {
				$enabled_components[ $feature_fqcn ] = true;
			}
		}
		return $enabled_components;
	}

	/**
	 * Rescan the files for new modules and features.
	 *
	 * @since  1.0.0
	 * @return string|bool
	 */
	private function save_rescan(): string|bool {

		$discovery_results = $this->plugin->components_register->rebuild();

		if ( empty( $discovery_results ) || ! is_array( $discovery_results ) ) {
			$this->plugin->notices->add( 'error', __( 'Critical Error: Failed to find modules and features in scan. Is this correct?', 'core-functionality' ) );
			return false;
		}

		return __( 'Scan was succesful. All Modules and Features succesfully registered.', 'core-functionality' );
	}

	/**
	 * Adds trailing forward slash to path.
	 *
	 * @param string $path The path.
	 * @return string
	 */
	private static function trailingslashit( $path ) {
		return self::untrailingslashit( $path ) . '/';
	}

	/**
	 * Trims trailing forward- and backward slash from path.
	 *
	 * @param string $path The path.
	 * @return string
	 */
	private static function untrailingslashit( string $path ): string {
		return rtrim( $path, '/\\' );
	}

	/**
	 * Returns the buttons of the footer: save, scan for new features, and reset to factory defaults.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Button key => button HTML.
	 */
	#[Override]
	public function get_action_buttons(): array {
		$button_text_rescan = esc_html__( 'Scan for new features', 'core-functionality' );

		return array(
			'save'    => AdminSettingsPage::get_save_button(),
			'rescan'  => '<button type="submit" name="dwp_cf_rescan" value="1" class="button button-secondary">' . $button_text_rescan . '</button>',
			'factory' => AdminSettingsPage::get_factory_reset_button(),
		);
	}
}
