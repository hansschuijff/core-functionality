<?php
/**
 * Release Management Settings Section.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Admin\Services\Settings\Sections
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Admin\Services\Settings\Sections;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\AdminSettingsPage;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Admin\Services\Settings\SettingsExplanation;
use DeWittePrins\CoreFunctionality\Enums\CoreFunction;
use DeWittePrins\CoreFunctionality\Enums\Deployment;
use DeWittePrins\CoreFunctionality\Enums\EnvironmentRule;
use DeWittePrins\CoreFunctionality\Enums\Register;
use DeWittePrins\CoreFunctionality\Plugin;
use DeWittePrins\CoreFunctionality\Services\ReleaseRegister;
use Override;

use function __;
use function array_key_exists;
use function count;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function is_array;
use function plugins_url;
use function sanitize_text_field;
use function selected;
use function sprintf;
use function wp_unslash;

/**
 * Class ReleaseSettings
 *
 * The Release Management tab: per module and feature its status and its rule per environment, as
 * the release decides them (see ReleaseRegister), and which of the plugin's own functions are on per
 * environment (see CoreFunction). Saving writes the release file in the plugin's settings folder,
 * which is committed and travels with the release. The tab is only registered on a development
 * environment, so the sites that install the plugin do not get it (Hans, October 2026).
 *
 * @since 1.0.0
 */
class ReleaseSettings implements SettingsSectionInterface {

	/**
	 * Name of the posted field that holds the entries.
	 *
	 * @var string
	 */
	private const FIELD_NAME = 'dwp_release';

	/**
	 * ReleaseSettings Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin The central plugin instance.
	 */
	public function __construct(
		private readonly Plugin $plugin,
	) {}

	/**
	 * Returns the unique section identifier used in the URL.
	 *
	 * The id stays the one of the former Deployment tab, so existing links keep working.
	 *
	 * @since  1.0.0
	 * @return string The section id.
	 */
	#[Override]
	public static function get_id(): string {
		return 'cf-release-management';
	}

	/**
	 * Returns an icon for the admin settings menu.
	 *
	 * @since  1.0.0
	 * @return string The dashicon class.
	 */
	#[Override]
	public static function get_icon(): string {
		return 'dashicons-buddicons-replies';
	}

	/**
	 * Returns the name of the tab.
	 *
	 * @since  1.0.0
	 * @return string The translated name.
	 */
	#[Override]
	public static function get_name(): string {
		return __( 'Release Management', 'core-functionality' );
	}

	/**
	 * Returns the description of the tab.
	 *
	 * @since  1.0.0
	 * @return string The translated description.
	 */
	#[Override]
	public static function get_description(): string {
		return __( 'For the developers of this plugin: how far each module and feature is built, and how freely it may be activated per environment.', 'core-functionality' );
	}

	/**
	 * Returns the stylesheets of this tab.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Handle => URL.
	 */
	#[Override]
	public static function get_stylesheets(): array {
		return array(
			'dwp-cf-release-settings-css' => plugins_url( 'assets/css/release-settings.css', __FILE__ ),
		);
	}

	/**
	 * Returns the scripts of this tab.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Handle => URL.
	 */
	#[Override]
	public static function get_scripts(): array {
		return array();
	}

	/**
	 * Renders one table per module: the module, then its features, each with its status and rules.
	 *
	 * @since  1.0.0
	 * @param  bool $reset_to_defaults Not used: this tab has no factory reset.
	 * @return void
	 */
	#[Override]
	public function render( bool $reset_to_defaults = false ): void {
		$components = $this->plugin->components_register->get_register( Register::COMPONENTS );
		$release    = $this->plugin->release_register->get_release_choices();

		$html  = $this->render_explanation();
		$html .= $this->render_core_table( $release );
		$html .= '<div class="dwp-release-modules">';
		foreach ( $components as $module_fqcn => $features_fqcn ) {
			$html .= $this->render_module_table( $module_fqcn, (array) $features_fqcn, $release );
		}
		$html .= '</div><!-- closes dwp-release-modules -->';

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while it is built.
	}

	/**
	 * Returns the collapsed explanation of the page, written for its developers (Hans, November 2026).
	 *
	 * @since  1.0.0
	 * @return string The explanation (HTML).
	 */
	private function render_explanation(): string {
		return SettingsExplanation::render(
			__( 'Explanation', 'core-functionality' ),
			array(
				__( 'This page is only for the developers of the modules and features in this plugin.', 'core-functionality' ),
				__( 'The build status says how far a module or feature has been developed. It matches the environments where it may be active, so a feature that is not ready for production can never be activated on a production environment.', 'core-functionality' ),
				__( 'Per environment you choose whether a module or feature is always active there, always inactive, or adjustable by the administrator. That choice depends on the purpose of the module or feature more than on how far it is built.', 'core-functionality' ),
				__( 'A module is only the container of its features and follows them: it takes the widest setting of its features, so it is there wherever one of them may be active.', 'core-functionality' ),
				__( 'These settings are saved in a settings file that goes with the release. It sets the conditions for using this plugin on other systems, so their administrators start from a safe point.', 'core-functionality' ),
				__( 'The file only gets what the build status allows: on an environment it does not reach yet, a feature is always inactive there. Your choice for later is kept on this site and shown here, so it applies once the status gets there.', 'core-functionality' ),
				__( 'The core functions are parts of the plugin itself. They have no build status and a development environment always has them all, so only staging and production can be set. A locked one cannot be changed here.', 'core-functionality' ),
			)
		);
	}

	/**
	 * Returns the table of the plugin's own functions.
	 *
	 * @since  1.1.0
	 * @param  array<string, array<string, string>> $release The entries of the release file.
	 * @return string The table (HTML).
	 */
	private function render_core_table( array $release ): string {
		$header_row = $this->render_core_header_row();

		$rows = '';
		foreach ( CoreFunction::cases() as $core_function ) {
			$rows .= $this->render_core_row( $core_function, $release );
		}

		return <<<HTML
			<div class="dwp-release-card">
				<table class="dwp-release-table">
					<thead>{$header_row}</thead>
					<tbody>{$rows}</tbody>
				</table>
			</div><!-- closes dwp-release-card -->
		HTML;
	}

	/**
	 * Returns the header rows of the core functions: their name, then the environments that can be set.
	 *
	 * @since  1.1.0
	 * @return string The rows (HTML).
	 */
	private function render_core_header_row(): string {
		$name_label       = esc_html__( 'Core functions', 'core-functionality' );
		$activation_label = esc_html__( 'Activation per environment', 'core-functionality' );
		$environments     = $this->get_core_environments();
		$colspan          = count( $environments );

		$environment_cells = '';
		foreach ( $environments as $environment ) {
			$environment_cells .= '<th scope="col">' . esc_html( $environment->get_label() ) . '</th>';
		}

		return <<<HTML
			<tr>
				<th scope="col" rowspan="2" class="dwp-release-name-column">{$name_label}</th>
				<th scope="colgroup" colspan="{$colspan}" class="dwp-release-group-heading">{$activation_label}</th>
			</tr>
			<tr>
				{$environment_cells}
			</tr>
		HTML;
	}

	/**
	 * Returns the row of one core function.
	 *
	 * @since  1.1.0
	 * @param  CoreFunction                         $core_function The function.
	 * @param  array<string, array<string, string>> $release       The entries of the release file.
	 * @return string The row (HTML).
	 */
	private function render_core_row( CoreFunction $core_function, array $release ): string {
		$key              = $core_function->get_release_key();
		$entry            = $release[ $key ] ?? array();
		$name             = $core_function->get_label();
		$name_html        = esc_html( $name );
		$description_html = esc_html( $core_function->get_description() );
		$note_html        = $this->render_adjusted_note( $key );

		$environment_cells = '';
		foreach ( $this->get_core_environments() as $environment ) {
			$environment_cells .= null === $core_function->get_locked_state()
				? $this->render_select_cell( $key, $environment->value, $entry, $this->get_core_options(), $name )
				: $this->render_locked_cell( $core_function );
		}

		return <<<HTML
			<tr class="dwp-release-core">
				<th scope="row">{$name_html}<span class="dwp-release-core-description">{$description_html}</span>{$note_html}</th>
				{$environment_cells}
			</tr>
		HTML;
	}

	/**
	 * Returns the cell of a locked core function: its state, without a choice.
	 *
	 * @since  1.1.0
	 * @param  CoreFunction $core_function The locked function.
	 * @return string The cell (HTML).
	 */
	private function render_locked_cell( CoreFunction $core_function ): string {
		$state = $core_function->get_locked_state()
			? esc_html__( '🔒 Always on', 'core-functionality' )
			: esc_html__( '🔒 Always off', 'core-functionality' );

		return "<td class=\"dwp-release-locked\">{$state}</td>";
	}

	/**
	 * Returns the environments on which a core function can be set: all but development, which always
	 * has them all.
	 *
	 * @since  1.1.0
	 * @return Deployment[] The environments.
	 */
	private function get_core_environments(): array {
		return array( Deployment::STAGING, Deployment::PRODUCTION );
	}

	/**
	 * Returns the choices of a core function on an environment.
	 *
	 * @since  1.1.0
	 * @return array<string, string> Value => label.
	 */
	private function get_core_options(): array {
		return array(
			EnvironmentRule::AUTO_ON->value  => __( 'On', 'core-functionality' ),
			EnvironmentRule::AUTO_OFF->value => __( 'Off', 'core-functionality' ),
		);
	}

	/**
	 * Returns the entries of the core functions that can be set, from the posted form data.
	 *
	 * A core function is part of the plugin, so its status never limits it. Locked ones get no entry:
	 * the code decides those.
	 *
	 * @since  1.1.0
	 * @param  array<mixed> $posted The posted entries.
	 * @return array<string, array<string, string>> Key => entry.
	 */
	private function get_posted_core_entries( array $posted ): array {
		$entries = array();
		foreach ( CoreFunction::cases() as $core_function ) {
			if ( null !== $core_function->get_locked_state() ) {
				continue;
			}

			$key          = $core_function->get_release_key();
			$posted_entry = isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ? $posted[ $key ] : array();

			$entry = array( ReleaseRegister::STATUS_KEY => Deployment::PRODUCTION->value );
			foreach ( Deployment::cases() as $environment ) {
				$is_off = EnvironmentRule::AUTO_OFF->value === ( $posted_entry[ $environment->value ] ?? '' );

				$entry[ $environment->value ] = $is_off ? EnvironmentRule::AUTO_OFF->value : EnvironmentRule::AUTO_ON->value;
			}

			$entries[ $key ] = $entry;
		}

		return $entries;
	}

	/**
	 * Returns the table of one module and its features.
	 *
	 * @since  1.0.0
	 * @param  string                               $module_fqcn   Class name of the module.
	 * @param  string[]                             $features_fqcn Class names of its features.
	 * @param  array<string, array<string, string>> $release       The entries of the release file.
	 * @return string The table (HTML).
	 */
	private function render_module_table( string $module_fqcn, array $features_fqcn, array $release ): string {
		$header_row = $this->render_header_row( $module_fqcn );
		$rows       = empty( $features_fqcn )
			? $this->render_component_row( $module_fqcn, $release, 'dwp-release-module' )
			: '';

		foreach ( $features_fqcn as $feature_fqcn ) {
			$rows .= $this->render_component_row( $feature_fqcn, $release, 'dwp-release-feature' );
		}

		return <<<HTML
			<div class="dwp-release-card">
				<table class="dwp-release-table">
					<thead>{$header_row}</thead>
					<tbody>{$rows}</tbody>
				</table>
			</div><!-- closes dwp-release-card -->
		HTML;
	}

	/**
	 * Returns the header rows: the module's name and the build status, then the three environments
	 * grouped under "Activation per environment". The module gets no row of its own: it follows its
	 * features (Hans, November 2026).
	 *
	 * @since  1.0.0
	 * @param  string $module_fqcn Class name of the module.
	 * @return string The rows (HTML).
	 */
	private function render_header_row( string $module_fqcn ): string {
		$name_label       = esc_html( $module_fqcn::get_name() );
		$status_label     = esc_html( $this->get_column_label( ReleaseRegister::STATUS_KEY ) );
		$activation_label = esc_html__( 'Activation per environment', 'core-functionality' );
		$environments     = count( Deployment::cases() );

		$environment_cells = '';
		foreach ( Deployment::cases() as $environment ) {
			$environment_cells .= '<th scope="col">' . esc_html( $environment->get_label() ) . '</th>';
		}

		return <<<HTML
			<tr>
				<th scope="col" rowspan="2" class="dwp-release-name-column">{$name_label}</th>
				<th scope="col" rowspan="2" class="dwp-release-status-column">{$status_label}</th>
				<th scope="colgroup" colspan="{$environments}" class="dwp-release-group-heading">{$activation_label}</th>
			</tr>
			<tr>
				{$environment_cells}
			</tr>
		HTML;
	}

	/**
	 * Returns the row of one module or feature.
	 *
	 * @since  1.0.0
	 * @param  string                               $fqcn      Class name of the module or feature.
	 * @param  array<string, array<string, string>> $release   The entries of the release file.
	 * @param  string                               $row_class The class of the row (module or feature).
	 * @return string The row (HTML).
	 */
	private function render_component_row( string $fqcn, array $release, string $row_class ): string {
		$entry       = $release[ $fqcn ] ?? ReleaseRegister::get_default_entry();
		$name        = $fqcn::get_name();
		$name_html   = esc_html( $name );
		$note_html   = $this->render_adjusted_note( $fqcn );
		$status_cell = $this->render_select_cell( $fqcn, ReleaseRegister::STATUS_KEY, $entry, $this->get_status_options(), $name );

		$environment_cells = '';
		foreach ( Deployment::cases() as $environment ) {
			$environment_cells .= $this->render_select_cell( $fqcn, $environment->value, $entry, $this->get_rule_options(), $name );
		}

		return <<<HTML
			<tr class="{$row_class}">
				<th scope="row">{$name_html}{$note_html}</th>
				{$status_cell}
				{$environment_cells}
			</tr>
		HTML;
	}

	/**
	 * Returns a note when this installation's configuration or filter changes the release value.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Class name of the module or feature.
	 * @return string The note (HTML), or '' when nothing is adjusted.
	 */
	private function render_adjusted_note( string $fqcn ): string {
		if ( ! $this->plugin->release_register->is_adjusted_here( $fqcn ) ) {
			return '';
		}

		$note_text = esc_html__( 'This installation\'s configuration adjusts it.', 'core-functionality' );

		return "<span class=\"dwp-release-adjusted\">{$note_text}</span>";
	}

	/**
	 * Returns a table cell with a select for one key of an entry.
	 *
	 * @since  1.0.0
	 * @param  string                $fqcn    Class name of the module or feature.
	 * @param  string                $key     ReleaseRegister::STATUS_KEY or an environment.
	 * @param  array<string, string> $entry   The entry of the component.
	 * @param  array<string, string> $options Value => label.
	 * @param  string                $name    The name of the component (not escaped).
	 * @return string The cell (HTML).
	 */
	private function render_select_cell( string $fqcn, string $key, array $entry, array $options, string $name ): string {
		$field_name = esc_attr( self::FIELD_NAME . '[' . $fqcn . '][' . $key . ']' );
		$aria_label = esc_attr( sprintf( '%1$s: %2$s', $name, $this->get_column_label( $key ) ) );
		$current    = $entry[ $key ] ?? '';

		$options_html = '';
		foreach ( $options as $value => $label ) {
			$value_attr    = esc_attr( $value );
			$label_html    = esc_html( $label );
			$is_selected   = selected( $current, $value, false );
			$options_html .= "<option value=\"{$value_attr}\" {$is_selected}>{$label_html}</option>";
		}

		return "<td><select name=\"{$field_name}\" aria-label=\"{$aria_label}\">{$options_html}</select></td>";
	}

	/**
	 * Returns the label of a column, for the accessible name of its selects.
	 *
	 * @since  1.0.0
	 * @param  string $key ReleaseRegister::STATUS_KEY or an environment.
	 * @return string The label (not escaped).
	 */
	private function get_column_label( string $key ): string {
		$environment = Deployment::tryFrom( $key );

		return null === $environment || ReleaseRegister::STATUS_KEY === $key
			? __( 'Build status', 'core-functionality' )
			: $environment->get_label();
	}

	/**
	 * Returns the choices of the status.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Value => label.
	 */
	private function get_status_options(): array {
		$options = array();
		foreach ( Deployment::cases() as $status ) {
			$options[ $status->value ] = $status->get_label();
		}

		return $options;
	}

	/**
	 * Returns the choices of a rule.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Value => label.
	 */
	private function get_rule_options(): array {
		$options = array();
		foreach ( EnvironmentRule::cases() as $rule ) {
			$options[ $rule->value ] = $rule->get_label();
		}

		return $options;
	}

	/**
	 * Validates the posted entries; invalid values are replaced by their defaults on saving.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The posted form data.
	 * @return bool True when there are entries to save.
	 */
	#[Override]
	public function validate( array $posted_data ): bool {
		return isset( $posted_data[ self::FIELD_NAME ] ) && is_array( $posted_data[ self::FIELD_NAME ] );
	}

	/**
	 * Writes the posted entries of the known modules and features to the release file.
	 *
	 * @since  1.0.0
	 * @param  array $posted_data The posted form data.
	 * @return string|bool The success message, or false when the file could not be written.
	 */
	#[Override]
	public function save( array $posted_data ): string|bool {
		$posted  = wp_unslash( $posted_data[ self::FIELD_NAME ] ?? array() );
		$entries = array();

		foreach ( $this->get_component_classes() as $fqcn ) {
			if ( ! isset( $posted[ $fqcn ] ) || ! is_array( $posted[ $fqcn ] ) ) {
				continue;
			}
			$entries[ $fqcn ] = $this->sanitize_posted_entry( $posted[ $fqcn ] );
		}

		$entries += $this->get_posted_core_entries( $posted );

		if ( ! $this->plugin->release_register->save_release_values( $entries ) ) {
			$this->plugin->notices->add( 'error', __( 'The release file could not be written. Check whether the settings folder of the plugin is writable.', 'core-functionality' ) );
			return false;
		}

		return __( 'The settings are saved in settings/components-release.php. Remember to commit it.', 'core-functionality' );
	}

	/**
	 * Returns the posted values of one entry as plain strings; ReleaseRegister checks them.
	 *
	 * @since  1.0.0
	 * @param  array<mixed> $posted_entry The posted values of one component.
	 * @return array<string, string> Key => value.
	 */
	private function sanitize_posted_entry( array $posted_entry ): array {
		$entry = array();
		foreach ( ReleaseRegister::get_default_entry() as $key => $default ) {
			$entry[ $key ] = array_key_exists( $key, $posted_entry )
				? sanitize_text_field( (string) $posted_entry[ $key ] )
				: $default;
		}

		return $entry;
	}

	/**
	 * Returns the class names of the modules and features that have their own entry: every feature,
	 * and a module only when it has no features (otherwise its entry follows from theirs).
	 *
	 * @since  1.0.0
	 * @return string[] Fully qualified class names.
	 */
	private function get_component_classes(): array {
		$classes = array();
		foreach ( $this->plugin->components_register->get_register( Register::COMPONENTS ) as $module_fqcn => $features_fqcn ) {
			if ( empty( $features_fqcn ) ) {
				$classes[] = $module_fqcn;
			}
			foreach ( (array) $features_fqcn as $feature_fqcn ) {
				$classes[] = $feature_fqcn;
			}
		}

		return $classes;
	}

	/**
	 * Returns the buttons of the footer: only Save, as resetting the release file is never wanted.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Name => button (HTML).
	 */
	#[Override]
	public function get_action_buttons(): array {
		return array(
			'save' => AdminSettingsPage::get_save_button(),
		);
	}
}
