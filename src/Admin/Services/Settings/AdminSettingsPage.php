<?php
/**
 * Unified Framework Administration Settings Page Controller.
 *
 * @package DeWittePrins\CoreFunctionality\Admin\Services\Settings
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Admin\Services\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\Interfaces\SettingsSectionInterface;
use DeWittePrins\CoreFunctionality\Services\ClassParser;
use DeWittePrins\CoreFunctionality\Plugin;
use WP_Error;

// Import global PHP and WordPress functions to optimize runtime lookups.
use function __;
use function add_options_page;
use function add_query_arg;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function menu_page_url;
use function plugins_url;
use function remove_query_arg;
use function sanitize_key;
use function get_submit_button;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_referer;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_verify_nonce;
use function ob_start;
use function ob_get_clean;
use function get_current_screen;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function is_readable;
use function filemtime;
use function array_key_first;
use function array_key_exists;
use function strtolower;
use function sprintf;
use function current_filter;
use function doing_action;
use function did_action;
use function end;
use function method_exists;
use function is_wp_error;
use function is_string;
use function wp_unslash;
use function sanitize_text_field;
use function error_log;
use function preg_match;
use function uksort;

/**
 * Class AdminSettingsPage
 *
 * Coordinates rendering, validation routing, and persistence execution
 * for the centralized plugin administration layout interface.
 *
 * @since 1.0.0
 */
class AdminSettingsPage {

	/**
	 * The slug of the settings page (Settings → Core Functionality).
	 *
	 * @var string
	 */
	public const SLUG = 'dwp-cf-settings';

	/**
	 * Array of registered setting sections array<id, fqcn>.
	 *
	 * @var array<string, string>
	 */
	private array $sections = array();

	/**
	 * The current section object.
	 *
	 * @var SettingsSectionInterface
	 */
	private SettingsSectionInterface $current_section_object;

	/**
	 * Cached WordPress admin notices HTML.
	 *
	 * @var string
	 */
	private string $admin_notices_buffered_html = '';

	/**
	 * Settings Page Controller Constructor.
	 *
	 * @since 1.0.0
	 * @param Plugin $plugin Central framework orchestrator instance.
	 */
	public function __construct( private readonly Plugin $plugin ) {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );

		add_action( 'admin_notices', array( $this, 'start_buffering_admin_notices' ), PHP_INT_MIN );
		add_action( 'admin_notices', array( $this, 'save_buffered_admin_notices' ), PHP_INT_MAX );
	}

	/**
	 * Registers the master options node inside the WordPress general settings menu column.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_settings_page(): void {
		$page_hook = add_options_page(
			__( 'Core Functionality Settings', 'core-functionality' ),
			__( 'Core Functionality', 'core-functionality' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_settings_page' )
		);
		add_action( 'admin_print_styles-' . $page_hook, array( $this, 'enqueue_section_assets' ) );

		do_action( 'core-functionality/register-settings-sections', $this );
	}

	/**
	 * Public factory API allowing sections to hook themselves into the menu matrix grid registry.
	 *
	 * @since  1.0.0
	 * @param  string $section_class Component class satisfying the SettingsSectionInterface.
	 * @return void
	 */
	public function register_section( string $section_class ): void {
		if ( ! $this->is_section_class( $section_class ) ) {
			return;
		}

		$this->sections[ $section_class::get_id() ] = $section_class;
	}

	/**
	 * Validates if the given class string implements the mandatory settings section interface.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn The Fully Qualified Class Name to investigate.
	 * @return bool True if validated successfully, false otherwise.
	 */
	private function is_section_class( string $fqcn ): bool {
		return ClassParser::is_implementation_of( $fqcn, SettingsSectionInterface::class );
	}

	/**
	 * Enqueues stylesheets and scripts exclusively for the currently active settings section.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function enqueue_section_assets(): void {
		if ( empty( $this->sections ) ) {
			return;
		}

		// The page's own assets also serve an unknown section, which still shows the menu.
		$this->enqueue_styles( self::get_stylesheets() );
		$this->enqueue_scripts( self::get_scripts() );

		$unchecked_id = $_GET['section'] ?? '';
		$section_fqcn = $this->get_active_section_fqcn( $unchecked_id );
		if ( ! $section_fqcn ) {
			return;
		}

		$this->enqueue_styles( $section_fqcn::get_stylesheets() );
		$this->enqueue_scripts( $section_fqcn::get_scripts() );
	}

	/**
	 * Registers an array of stylesheet handles and source URLs with WordPress.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $styles Dictionary of handles matching asset source URLs.
	 * @return void
	 */
	private function enqueue_styles( array $styles ): void {
		if ( empty( $styles ) ) {
			return;
		}

		foreach ( $styles as $handle => $url ) {
			wp_enqueue_style(
				$handle,
				esc_url( $url ),
				array(),
				$this->get_asset_version( $url )
			);
		}
	}

	/**
	 * Returns the version for an asset URL: the modification time of the file when it is part of
	 * this plugin, so a changed file is never served from the browser cache; the plugin version
	 * otherwise.
	 *
	 * @since  1.0.0
	 * @param  string $url The asset URL.
	 * @return string      The version.
	 */
	private function get_asset_version( string $url ): string {
		$plugin_url = $this->plugin->get_data( 'plugin-url' );
		$plugin_dir = $this->plugin->get_data( 'plugin-dir' );

		if ( '' !== $plugin_url && str_starts_with( $url, $plugin_url ) ) {
			$file = $plugin_dir . substr( $url, strlen( $plugin_url ) );
			if ( is_readable( $file ) ) {
				return (string) filemtime( $file );
			}
		}

		return $this->plugin->get_data( 'version' );
	}

	/**
	 * Registers an array of JavaScript handles and source URLs with WordPress.
	 *
	 * @since  1.0.0
	 * @param  array<string, string> $scripts Dictionary of handles matching asset source URLs.
	 * @return void
	 */
	private function enqueue_scripts( array $scripts ): void {
		if ( empty( $scripts ) ) {
			return;
		}

		foreach ( $scripts as $handle => $url ) {
			wp_enqueue_script(
				$handle,
				esc_url( $url ),
				array( 'jquery' ),
				$this->get_asset_version( $url ),
				true
			);
		}
	}

	/**
	 * Returns the absolute stylesheet URLs for this master page shell environment.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Array matching unique handles to asset URLs.
	 */
	public static function get_stylesheets(): array {
		$base_url = plugins_url( 'assets/css/', __FILE__ );

		return array(
			'dwp-cf-admin-settings-page-css' => $base_url . 'admin-settings.css',
		);
	}

	/**
	 * Returns the absolute asset URLs for this master page shell environment.
	 *
	 * @since  1.0.0
	 * @return array<string, string> Array matching unique handles to asset URLs.
	 */
	public static function get_scripts(): array {
		$base_url = plugins_url( 'assets/js/', __FILE__ );

		return array(
			'dwp-cf-admin-settings-page-js' => $base_url . 'admin-settings.js',
		);
	}

	/**
	 * Renders the full Gutenberg settings layout configuration interface.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! $this->is_this_settings_page() ) {
			echo 'not a settings page';
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			echo 'not authorized';
			return;
		}

		if ( empty( $this->sections ) ) {
			echo 'No sections';
			return;
		}

		$unchecked_id = $_GET['section'] ?? '';
		$section_fqcn = $this->get_active_section_fqcn( $unchecked_id );
		if ( ! $section_fqcn ) {
			$this->render_unknown_section_page();
			return;
		}

		$this->set_current_section( $section_fqcn );

		if ( ! $this->current_section() ) {
			echo 'current section not a section:' . $section_fqcn;
			return;
		}

		$reset_to_defaults = ( isset( $_GET['view'] ) && 'settings_reset' === $_GET['view'] );

		self::render_sidebar_flash_prevention();

		echo '<div class="dwp-gutenberg-frame">';

		$this->render_navigation_sidebar( $section_fqcn::get_id() );
		$this->render_content_area( $this->current_section(), $reset_to_defaults );

		echo '</div>';

		$this->render_command_pallette_modal();
	}

	/**
	 * Renders the page for a section that does not exist (an old link, a module switched off): the
	 * menu stays, so another section can be chosen, and the canvas says what happened.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_unknown_section_page(): void {
		self::render_sidebar_flash_prevention();

		echo '<div class="dwp-gutenberg-frame">';
		$this->render_navigation_sidebar( '' );
		echo '<main class="dwp-gutenberg-canvas"><div class="dwp-gutenberg-sticky-header"><h1>' . esc_html__( 'Settings', 'core-functionality' ) . '</h1></div>';
		echo '<div class="dwp-gutenberg-scroll-content"><div class="notice notice-warning inline"><p>' . esc_html__( 'This section does not exist (any more). Choose a section in the menu.', 'core-functionality' ) . '</p></div></div>';
		echo '</main></div>';

		$this->render_command_pallette_modal();
	}

	/**
	 * Renders the modal shortcuts keyboard command palette.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_command_pallette_modal(): void {
		$placeholder = esc_attr__( 'Type to search... (↑↓ to navigate, Enter)', 'core-functionality' );
		echo <<<HTML
			<div class="dwp-command-palette" id="js-dwp-palette" style="display: none;">
				<div class="dwp-palette-overlay"></div>
				<div class="dwp-palette-box">
					<div class="dwp-palette-search-wrapper">
						<span class="dashicons dashicons-search"></span>
						<input type="text" id="js-palette-input" placeholder="{$placeholder}" autocomplete="off">
					</div>
					<div class="dwp-palette-results" id="js-palette-results"></div>
				</div>
			</div>
		HTML;
	}

	/**
	 * Renders the context section wrapper and form fields inside the right canvas workspace.
	 *
	 * @since  1.0.0
	 * @param  SettingsSectionInterface $section           The active instantiated section engine.
	 * @param  bool                     $reset_to_defaults True if previewing factory settings overrides.
	 * @return void
	 */
	private function render_content_area( SettingsSectionInterface $section, bool $reset_to_defaults ): void {
		?>
		<!-- RECHTS: CENTRALE WORKSPACE CANVAS -->
		<main class="dwp-gutenberg-canvas">

			<!-- STICKY HEADER AREA (Alleen voor de H1 Titel voor maximale ruimte) -->
			<div class="dwp-gutenberg-sticky-header">
				<h1>
					<?php echo esc_html( $section::get_name() . ' ' . __( 'Settings', 'core-functionality' ) ); ?>
				</h1>
			</div>

			<!-- START THE FORM HERE (Omspant de binnenzijde van het canvas) -->
			<?php $this->render_settings_form_start( $section::get_id() ); ?>

			<!-- INHOUDSGEBIED (SCROLLBAAR) -->
			<div class="dwp-gutenberg-scroll-content">
				<?php
				$this->render_buffered_admin_notices();
				$this->render_description( $section::get_description() );
				?>

				<!-- De invoervelden van de actieve sectie -->
				<div class="dwp-gutenberg-body">
					<?php $section->render( $reset_to_defaults ); ?>
				</div>
			</div>
			<!-- Rendert de vastgekleefde footer en sluit het formulier aan de buitenzijde van de scrollbox -->
			<?php $this->render_settings_form_end( $section::get_id(), $reset_to_defaults ); ?>

		</main>
		<?php
	}

	/**
	 * Outputs the section description text block if present.
	 *
	 * @since  1.0.0
	 * @param  string $description Context narrative string.
	 * @return void
	 */
	private function render_description( string $description ): void {
		if ( empty( $description ) ) {
			return;
		}
		?>
		<p class="dwp-gutenberg-description">
			<?php echo esc_html( $description ); ?>
		</p>
		<?php
	}

	/**
	 * Injects the localized anti-flicker sidebar script handler.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function render_sidebar_flash_prevention(): void {
		?>
		<script type="text/javascript">
			(function() {
				if (localStorage.getItem('dwp_gutenberg_sidebar') === 'collapsed') {
					document.documentElement.classList.add('dwp-sidebar-init-collapsed');
				}
			})();
		</script>
		<?php
	}

	/**
	 * Renders the section navigation sidebar with Gutenberg structure.
	 *
	 * @since  1.0.0
	 * @param  string $active_section_id The registered id of the current section.
	 * @return void
	 */
	private function render_navigation_sidebar( string $active_section_id ): void {
		$search_placeholder = esc_attr__( 'Search... (/)', 'core-functionality' );
		$collapse_title     = esc_attr__( 'Collapse menu', 'core-functionality' );
		?>
		<aside class="dwp-gutenberg-sidebar" id="js-dwp-sidebar">
			<div class="dwp-sidebar-header">
				<div class="dwp-search-wrapper">
					<span class="dashicons dashicons-search"></span>
					<input type="text" id="js-sidebar-search" placeholder="<?php echo $search_placeholder; ?>" autocomplete="off">
				</div>
				<button type="button" class="dwp-sidebar-toggle js-dwp-sidebar-toggle" title="<?php echo $collapse_title; ?>">
					<span class="dashicons dashicons-menu-alt3" id="js-toggle-icon"></span>
				</button>
			</div>

			<nav class="dwp-sidebar-menu">
				<!-- GROEP 1: CORE FUNCTIONALITY SETTINGS -->
				<div class="dwp-menu-group">
					<span class="dwp-group-title"><?php esc_html_e( 'Core Functionality Settings', 'core-functionality' ); ?></span>
					<?php
					foreach ( $this->sections as $id => $fqcn ) {
						if ( ! $this->is_feature_section( $fqcn ) ) {
							$this->render_sidebar_link_html( $id, $fqcn, $active_section_id );
						}
					}
					?>
				</div>

				<?php
				// One group per module, so each tab of a feature keeps the context of its module.
				foreach ( $this->get_feature_sections_by_module() as $module_name => $sections ) {
					$this->render_sidebar_group( $module_name, $sections, $active_section_id );
				}
				?>
				<div class="dwp-no-results" id="js-search-no-results" style="display: none;"><?php esc_html_e( 'No items found', 'core-functionality' ); ?></div>
			</nav>
		</aside>
		<?php
	}

	/**
	 * Returns the sections of features grouped by the name of their module, modules sorted by name and
	 * sections in the order they were registered.
	 *
	 * @since  1.0.0
	 * @return array<string, array<string, string>> Module name => section id => section class.
	 */
	private function get_feature_sections_by_module(): array {
		$groups = array();
		foreach ( $this->sections as $id => $fqcn ) {
			if ( ! $this->is_feature_section( $fqcn ) ) {
				continue;
			}

			$module_name = $this->get_module_name( $fqcn );
			if ( null === $module_name ) {
				$this->report_section_without_module( $fqcn );
				continue;
			}

			$groups[ $module_name ][ $id ] = $fqcn;
		}

		uksort( $groups, 'strnatcasecmp' );

		return $groups;
	}

	/**
	 * Returns the name of the module a feature section belongs to, found through the folder rule:
	 * a class in `…\Modules\<Module>\…` belongs to the module class `…\Modules\<Module>\<Module>`.
	 *
	 * @since  1.0.0
	 * @param  string $section_fqcn The section class.
	 * @return string|null The module's (translated) name, or null when no module class is found.
	 */
	private function get_module_name( string $section_fqcn ): ?string {
		if ( 1 !== preg_match( '/^(.*\\\\Modules\\\\)([^\\\\]+)\\\\/', $section_fqcn, $matches ) ) {
			return null;
		}

		$module_fqcn = $matches[1] . $matches[2] . '\\' . $matches[2];
		if ( ! ClassParser::is_module_fqcn( $module_fqcn ) ) {
			return null;
		}

		return $module_fqcn::get_meta()->name;
	}

	/**
	 * Writes a section of a feature without a module to the PHP error log. A feature only loads with its
	 * module, so this is a programming error (a section class outside its feature's folder, say).
	 *
	 * @since  1.0.0
	 * @param  string $section_fqcn The section class.
	 * @return void
	 */
	private function report_section_without_module( string $section_fqcn ): void {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- A programming error must be visible, also without WP_DEBUG.
			sprintf(
				'[CoreFunctionality Settings] The settings section %s is in the Modules namespace but no module class was found for it. It is left out of the menu; check its namespace and folder.',
				$section_fqcn
			)
		);
	}

	/**
	 * Outputs one group of the sidebar: a title and the links of its sections.
	 *
	 * @since  1.0.0
	 * @param  string                $title             The group title, such as the module's name.
	 * @param  array<string, string> $sections          Section id => section class.
	 * @param  string                $active_section_id The currently active section key.
	 * @return void
	 */
	private function render_sidebar_group( string $title, array $sections, string $active_section_id ): void {
		$title_html = esc_html( $title );

		echo '<div class="dwp-menu-group"><span class="dwp-group-title">' . $title_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		foreach ( $sections as $id => $fqcn ) {
			$this->render_sidebar_link_html( $id, $fqcn, $active_section_id, $title );
		}
		echo '</div><!-- closes dwp-menu-group -->';
	}

	/**
	 * Filters namespace identifiers to detect feature module classes.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn The Full class namespace string to inspect.
	 * @return bool         True if it matches a module layout path string.
	 */
	private function is_feature_section( string $fqcn ): bool {
		return str_contains( $fqcn, '\\Modules\\' );
	}

	/**
	 * Renders the top part of the settings data mutation form.
	 *
	 * @since  1.0.0
	 * @param  string $active_section_id The currently active section key.
	 * @return void
	 */
	private function render_settings_form_start( string $active_section_id ): void {
		$active_section_id = esc_attr( $active_section_id );
		$action_url        = esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) );
		$nonce_field       = wp_nonce_field( 'dwp_cf_save_settings', 'dwp_cf_nonce', true, false );

		echo <<<HTML
			<form method="post" action="{$action_url}" class="dwp-gutenberg-section-form" enctype="multipart/form-data">
				{$nonce_field}
				<input type="hidden" name="action" value="dwp_cf_save_options" />
				<input type="hidden" name="current_section" value="{$active_section_id}" />
		HTML;
	}

	/**
	 * Renders the bottom part of the setting form, enclosing the form tags.
	 *
	 * @since  1.0.0
	 * @param  string $active_section_id The active section slug identifier.
	 * @param  bool   $reset_to_defaults True if currently triggering factory overrides.
	 * @return void
	 */
	// private function render_settings_form_end( $active_section_id, $reset_to_defaults = false ): void {
	// $submit_button = get_submit_button( __( 'Save Settings', 'core-functionality' ), 'primary', 'submit', false );.

	// echo <<<HTML
	// <div class="dwp-gutenberg-actions-footer">
	// {$submit_button}
	// HTML;.

	// $this->render_return_to_factory_settings_button();

	// echo <<<HTML
	// </div>
	// </form>
	// HTML;
	// }.
	/**
	 * Renders the bottom part of the setting form, enclosing the form tags.
	 * Dynamically injects section-specific action buttons or falls back to core defaults.
	 *
	 * @since  1.0.0
	 * @param  string $active_section_id The active section slug identifier.
	 * @param  bool   $reset_to_defaults True if currently triggering factory overrides.
	 * @return void
	 */
	private function render_settings_form_end( $active_section_id, $reset_to_defaults = false ): void {
		$section_object = $this->current_section();
		$custom_buttons = array();

		// Check if the current section overrides the default actions footer buttons template matrix.
		if ( $section_object && method_exists( $section_object, 'get_action_buttons' ) ) {
			$custom_buttons = $section_object->get_action_buttons();
		}

		echo '<div class="dwp-gutenberg-actions-footer">';

		// If the section provided context-aware layout buttons, loop and output them directly.
		if ( is_array( $custom_buttons ) && ! empty( $custom_buttons ) ) {
			foreach ( $custom_buttons as $button_html ) {
				echo $button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} else {
			// Fallback: Render the standard global default framework submission actions.
			echo self::get_save_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_submit_button().
			echo self::get_factory_reset_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		}

		echo '</div></form>';
	}

	/**
	 * Renders a single section navigation link item inside the sidebar.
	 *
	 * @since  1.0.0
	 * @param  string $id                The unique settings section identifier key.
	 * @param  string $section_fqcn      The Fully Qualified Class Name of the section.
	 * @param  string $active_section_id The currently active section key.
	 * @param  string $group_name        The name of the group (module), added to the search terms.
	 * @return void
	 */
	private function render_sidebar_link_html( string $id, string $section_fqcn, string $active_section_id, string $group_name = '' ): void {
		$section_url = add_query_arg( array( 'section' => $id ), menu_page_url( self::SLUG, false ) );
		$section_url = esc_url( $section_url );

		$is_active_section = ( $active_section_id === $id );
		$active_class      = $is_active_section ? 'is-active' : '';
		$section_name      = esc_html( $section_fqcn::get_name() );

		$dashicon = esc_attr( $section_fqcn::get_icon() );
		if ( empty( $dashicon ) ) {
			$dashicon = 'dashicons-admin-generic';
		}

		$search_terms = esc_attr( strtolower( $section_fqcn::get_name() . ' ' . $group_name ) . ' ' . $id );

		if ( $is_active_section ) {
			echo <<<HTML
				<span class="dwp-menu-item {$active_class}" data-id="{$id}" data-name="{$section_name}" data-search-term="{$search_terms}" title="{$section_name}">
					<span class="dashicons {$dashicon}"></span>
					<span class="dwp-menu-text">{$section_name}</span>
				</span>
			HTML;
			return;
		}

		echo <<<HTML
			<a href="{$section_url}" class="dwp-menu-item {$active_class}" data-id="{$id}" data-name="{$section_name}" data-search-term="{$search_terms}" title="{$section_name}">
				<span class="dashicons {$dashicon}"></span>
				<span class="dwp-menu-text">{$section_name}</span>
			</a>
		HTML;
	}

	/**
	 * Returns the standard "reset to factory defaults" button of the footer, for tabs with buttons of
	 * their own too.
	 *
	 * @since  1.0.0
	 * @return string The button (HTML).
	 */
	public static function get_factory_reset_button(): string {
		$button_text       = esc_html__( 'Reset to Factory Defaults', 'core-functionality' );
		$are_you_sure_text = esc_attr__( '🚨 CRITICAL WARNING:\n\nAre you sure you want to trigger a settings reset for this section?', 'core-functionality' );

		return <<<HTML
			<button type="submit" name="dwp_cf_reset_defaults" value="1" class="button button-secondary" onclick="return confirm('{$are_you_sure_text}');" >
				{$button_text}
			</button>
		HTML;
	}

	/**
	 * Returns the standard save button of the footer, for tabs with buttons of their own too.
	 *
	 * @since  1.0.0
	 * @return string The button (HTML).
	 */
	public static function get_save_button(): string {
		return get_submit_button( __( 'Save', 'core-functionality' ), 'primary', 'submit', false );
	}

	/**
	 * Resolves query args parameters to establish the active section class name.
	 *
	 * @since  1.0.0
	 * @param  string $id_unchecked Unverified raw token from $_GET.
	 * @return string|false          Fully Qualified class name string on success, false on failure.
	 */
	private function get_active_section_fqcn( $id_unchecked ): string|false {
		if ( empty( $id_unchecked ) ) {
			$id = array_key_first( $this->sections );
			return $id ? $this->sections[ $id ] : false;
		}

		$id = sanitize_key( $id_unchecked );
		if ( ! array_key_exists( $id, $this->sections ) ) {
			return false;
		}
		return $this->sections[ $id ];
	}

	/**
	 * Handles form data updates, routing payloads to individual section handlers.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function save_settings(): void {
		if ( ! isset( $_POST['dwp_cf_nonce'] ) || ! isset( $_POST['action'] ) || 'dwp_cf_save_options' !== $_POST['action'] ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['dwp_cf_nonce'] ), 'dwp_cf_save_settings' ) ) {
			$this->plugin->notices->add( 'error', esc_html__( 'Security check failed. Please try again.', 'core-functionality' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->plugin->notices->add( 'error', esc_html__( 'You do not have sufficient permissions to update this settings.', 'core-functionality' ) );
			return;
		}

		$unchecked_id = $_POST['current_section'] ?? '';
		$section_fqcn = $this->get_active_section_fqcn( $unchecked_id );
		if ( ! $section_fqcn ) {
			return;
		}

		$this->set_current_section( $section_fqcn );

		$section_object = $this->current_section();
		$redirect_url   = remove_query_arg( array( 'view', 'settings-updated' ), wp_get_referer() );

		$validation_result = $section_object->validate( $_POST );
		if ( is_wp_error( $validation_result ) ) {
			$this->plugin->notices->add( 'error', $validation_result->get_error_message() );
			return;
		}

		if ( false === $validation_result ) {
			$this->plugin->notices->add( 'error', __( 'Validation failed. Please double-check your entries.', 'core-functionality' ) );
			return;
		}

		$save_result = $section_object->save( $_POST );

		if ( false !== $save_result ) {
			$success_message = is_string( $save_result ) ? $save_result : __( 'Settings saved successfully.', 'core-functionality' );
			$this->plugin->notices->add( 'success', $success_message );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Returns the current section's object.
	 *
	 * @since  1.0.0
	 * @return SettingsSectionInterface|false Current section object, or false.
	 */
	private function current_section(): SettingsSectionInterface|false {
		if ( empty( $this->current_section_object ) ) {
			return false;
		}
		return $this->current_section_object;
	}

	/**
	 * Fetches section class name mapping matching its text slug token.
	 *
	 * @since  1.0.0
	 * @param  string $id Tab section slug string.
	 * @return string|false Mapped class name string, false on absence.
	 */
	private function get_section_fqcn( string $id ): string|false {
		if ( empty( $id ) ) {
			return false;
		}
		if ( array_key_exists( $id, $this->sections ) ) {
			return $this->sections[ $id ];
		}
		return false;
	}

	/**
	 * Instantiates a configuration settings page tab object safely.
	 *
	 * @since  1.0.0
	 * @param  string $fqcn Target class string to instantiate.
	 * @return bool          True on successful hydration, false otherwise.
	 */
	private function set_current_section( $fqcn ): bool {
		if ( ! $this->is_section_class( $fqcn ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					'%1$s is not a valid section for the core functionality settings page. Sections should implement the SettingsSectionInterface.',
					esc_html( $fqcn )
				),
				$this->plugin->get_data( 'version' )
			);
			return false;
		}
		$this->current_section_object = new $fqcn( $this->plugin );
		return true;
	}

	/**
	 * Checks if the current admin screen matches this framework settings page.
	 *
	 * @since  1.0.0
	 * @return bool True if on this settings page, false otherwise.
	 */
	private function is_this_settings_page(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return ( $screen && 'settings_page_' . self::SLUG === $screen->id );
	}

	/**
	 * Starts output buffering for admin notices at the absolute beginning of the hook execution.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function start_buffering_admin_notices(): void {
		if ( ! $this->is_this_settings_page() ) {
			return;
		}
		ob_start();
	}

	/**
	 * Captures and cleans the buffered admin notices HTML at the absolute end of the hook execution.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function save_buffered_admin_notices(): void {
		if ( ! $this->is_this_settings_page() ) {
			return;
		}
		$this->admin_notices_buffered_html = ob_get_clean();
	}

	/**
	 * Outputs the wrapping container and buffered notices HTML only if notifications exist.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function render_buffered_admin_notices(): void {
		if ( empty( $this->admin_notices_buffered_html ) ) {
			return;
		}

		echo <<<HTML
			<div class="dwp-gutenberg-notices">
				{$this->admin_notices_buffered_html}
			</div>
		HTML;
	}
}
