<?php
/**
 * Genesis-only Plugins Feature.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Modules\Themes\Features\GenesisOnlyPlugins
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Modules\Themes\Features\GenesisOnlyPlugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DeWittePrins\CoreFunctionality\Abstracts\AbstractFeature;
use DeWittePrins\CoreFunctionality\DTO\ComponentMeta;
use Override;
use WP_Screen;

use function __;
use function add_action;
use function add_query_arg;
use function admin_url;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_values;
use function current_user_can;
use function deactivate_plugins;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_current_user_id;
use function get_plugins;
use function get_template;
use function implode;
use function in_array;
use function is_admin;
use function is_array;
use function is_plugin_active;
use function is_string;
use function remove_query_arg;
use function sanitize_key;
use function sprintf;
use function wp_nonce_url;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Class GenesisOnlyPlugins
 *
 * Plugins that only work with the Genesis Framework do nothing without it at best; some call
 * Genesis functions and could cause fatal errors.
 *
 * Switching to a theme that is not built on Genesis (such as a block theme) is the one moment this
 * feature acts by itself: it switches those plugins off and tells the administrator which ones.
 * After that it only reports: when one of them is active while the theme is not Genesis, a warning
 * shows on the dashboard and the plugins and updates pages, to users who manage plugins, with a
 * button to switch them off. Whoever switches such a plugin on again on purpose can keep it on.
 *
 * @since 1.2.0
 */
class GenesisOnlyPlugins extends AbstractFeature {

	/**
	 * The template (parent theme folder) of the Genesis Framework.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private const GENESIS_TEMPLATE = 'genesis';

	/**
	 * The key of the settings file with the plugins that only work with Genesis.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private const SETTINGS_KEY = 'genesis-only-plugins';

	/**
	 * The query arg and nonce action of the warning's button that switches the plugins off.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private const DEACTIVATE = 'dwp_cf_deactivate_genesis_only_plugins';

	/**
	 * The admin pages that show the warning, single site and network.
	 *
	 * @since 1.2.0
	 * @var string[]
	 */
	private const SCREENS = array( 'dashboard', 'plugins', 'update-core', 'dashboard-network', 'plugins-network', 'update-core-network' );

	/**
	 * Returns the descriptive data of this feature.
	 *
	 * @since  1.2.0
	 * @return ComponentMeta Id, name, description and requirements.
	 */
	#[Override]
	public static function get_meta(): ComponentMeta {
		return new ComponentMeta(
			id:          'themes_genesis_only_plugins',
			name:        __( 'Genesis-only plugins', 'core-functionality' ),
			description: __( 'Switches off plugins that only work with Genesis when the site switches to a theme not built on Genesis, and warns when one of them is still active.', 'core-functionality' ),
		);
	}

	/**
	 * Adds the hooks of this feature.
	 *
	 * WordPress fires after_switch_theme on init 99 of the first request after a switch, so the new
	 * theme is loaded by then; also for a switch with WP-CLI.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	#[Override]
	public function launch(): void {
		add_action( 'after_switch_theme', array( $this, 'deactivate_after_theme_switch' ) );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'current_screen', array( $this, 'warn_about_active_plugins' ) );
	}

	/**
	 * Switches off the active Genesis-only plugins after a switch to a theme not built on Genesis.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	public function deactivate_after_theme_switch(): void {
		if ( $this->is_genesis_theme_active() ) {
			return;
		}

		$this->load_plugin_functions();

		$active_plugins = $this->get_active_genesis_only_plugins();
		if ( empty( $active_plugins ) ) {
			return;
		}

		deactivate_plugins( $active_plugins );

		$this->notify_deactivation(
			$active_plugins,
			/* translators: %s: comma-separated plugin names. */
			__( 'The new theme is not built on Genesis, so these Genesis-only plugins were switched off: %s.', 'core-functionality' )
		);
	}

	/**
	 * Shows a warning when Genesis-only plugins are active while the theme is not built on Genesis.
	 * Only on the dashboard and the plugins and updates pages, to users who manage plugins.
	 *
	 * @since  1.2.0
	 * @param  WP_Screen $screen The current admin page.
	 * @return void
	 */
	public function warn_about_active_plugins( WP_Screen $screen ): void {
		if ( ! in_array( $screen->id, self::SCREENS, true ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( $this->is_genesis_theme_active() ) {
			return;
		}

		$active_plugins = $this->get_active_genesis_only_plugins();
		if ( empty( $active_plugins ) ) {
			return;
		}

		if ( $this->is_deactivation_requested() ) {
			$this->deactivate_on_request( $active_plugins );
		}

		$plugin_names       = $this->get_plugin_names( $active_plugins );
		$is_on_plugins_page = in_array( $screen->id, array( 'plugins', 'plugins-network' ), true );

		add_action( 'admin_notices', fn () => $this->render_warning( $plugin_names, $is_on_plugins_page ) );
	}

	/**
	 * Checks whether the warning's button was clicked, with a valid nonce.
	 *
	 * @since  1.2.0
	 * @return bool True when the plugins should be switched off now.
	 */
	private function is_deactivation_requested(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified right below.
		if ( ! isset( $_GET[ self::DEACTIVATE ], $_GET['_wpnonce'] ) ) {
			return false;
		}

		return false !== wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), self::DEACTIVATE );
	}

	/**
	 * Switches the plugins off on the administrator's request, then reloads the page without the
	 * button's query args.
	 *
	 * @since  1.2.0
	 * @param  string[] $active_plugins Plugin basenames.
	 * @return never
	 */
	private function deactivate_on_request( array $active_plugins ): never {
		deactivate_plugins( $active_plugins );

		$this->notify_deactivation(
			$active_plugins,
			/* translators: %s: comma-separated plugin names. */
			__( 'These Genesis-only plugins were switched off: %s.', 'core-functionality' )
		);

		wp_safe_redirect( remove_query_arg( array( self::DEACTIVATE, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Checks whether the active theme is Genesis itself or a Genesis child theme.
	 *
	 * @since  1.2.0
	 * @return bool True when the active theme's template is Genesis.
	 */
	private function is_genesis_theme_active(): bool {
		return self::GENESIS_TEMPLATE === get_template();
	}

	/**
	 * Returns the basenames of the plugins that only work with Genesis, from the settings file
	 * `settings/modules/themes/genesis-only-plugins.php`.
	 *
	 * @since  1.2.0
	 * @return string[] Plugin basenames (folder/file.php).
	 */
	private function get_genesis_only_plugins(): array {
		$default_plugins = $this->plugin->settings->get( self::SETTINGS_KEY, $this->module::get_id(), false, array() );
		$default_plugins = is_array( $default_plugins ) ? array_values( array_filter( $default_plugins, 'is_string' ) ) : array();

		/**
		 * Filters the plugins that are switched off after a switch to a theme not built on Genesis,
		 * and reported when they are active without Genesis.
		 *
		 * @since 1.2.0
		 * @param string[] $default_plugins Plugin basenames (folder/file.php).
		 */
		$plugins = apply_filters( 'core-functionality/genesis-only-plugins', $default_plugins );

		if ( ! is_array( $plugins ) ) {
			return $default_plugins;
		}

		return array_values( array_filter( $plugins, 'is_string' ) );
	}

	/**
	 * Returns the Genesis-only plugins that are active.
	 *
	 * @since  1.2.0
	 * @return string[] Plugin basenames.
	 */
	private function get_active_genesis_only_plugins(): array {
		return array_values( array_filter( $this->get_genesis_only_plugins(), 'is_plugin_active' ) );
	}

	/**
	 * Loads WordPress's plugin functions, which only the admin loads by itself.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	private function load_plugin_functions(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * Tells an administrator which plugins were switched off.
	 *
	 * Without a logged-in user (WP-CLI) there is nobody to tell.
	 *
	 * @since  1.2.0
	 * @param  string[] $deactivated_plugins Plugin basenames.
	 * @param  string   $message_template    Translated message with %s for the plugin names.
	 * @return void
	 */
	private function notify_deactivation( array $deactivated_plugins, string $message_template ): void {
		if ( 0 === get_current_user_id() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$plugin_names = implode( ', ', $this->get_plugin_names( $deactivated_plugins ) );

		$this->plugin->notices->add( 'info', esc_html( sprintf( $message_template, $plugin_names ) ) );
	}

	/**
	 * Prints the warning about Genesis-only plugins that are active without Genesis, with a button
	 * that switches them off. It can be closed for the page view; it returns on the next one while
	 * the plugins stay active.
	 *
	 * @since  1.2.0
	 * @param  string[] $plugin_names       Names of the active Genesis-only plugins.
	 * @param  bool     $is_on_plugins_page Whether the warning shows on the plugins page itself.
	 * @return void
	 */
	private function render_warning( array $plugin_names, bool $is_on_plugins_page ): void {
		$heading = esc_html__( 'Genesis-only plugins', 'core-functionality' );
		$message = esc_html(
			sprintf(
				/* translators: %s: comma-separated plugin names. */
				__( 'The active theme is not built on Genesis, but these plugins that only work with Genesis are active: %s.', 'core-functionality' ),
				implode( ', ', $plugin_names )
			)
		);
		$button = $this->render_deactivate_button();
		$link   = $is_on_plugins_page ? '' : $this->render_plugins_page_link();

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All parts are escaped above.
		echo <<<HTML
			<div class="notice notice-warning is-dismissible">
				<p><strong>{$heading}</strong></p>
				<p>{$message}</p>
				<p>{$button}{$link}</p>
			</div><!-- closes notice -->
			HTML;
		// phpcs:enable
	}

	/**
	 * Returns the button that switches the plugins off, as a link with a nonce.
	 *
	 * @since  1.2.0
	 * @return string The button markup.
	 */
	private function render_deactivate_button(): string {
		$url   = esc_url( wp_nonce_url( add_query_arg( self::DEACTIVATE, '1' ), self::DEACTIVATE ) );
		$label = esc_html__( 'Switch them off', 'core-functionality' );

		return '<a class="button button-primary" href="' . $url . '">' . $label . '</a>';
	}

	/**
	 * Returns a link to the active plugins, to switch them off one by one, with a space in front.
	 *
	 * @since  1.2.0
	 * @return string The link markup.
	 */
	private function render_plugins_page_link(): string {
		$url   = esc_url( admin_url( 'plugins.php?plugin_status=active' ) );
		$label = esc_html__( 'Go to the plugins', 'core-functionality' );

		return ' <a class="button" href="' . $url . '">' . $label . '</a>';
	}

	/**
	 * Returns the names of plugins, or their basename when the name is unknown.
	 *
	 * @since  1.2.0
	 * @param  string[] $basenames Plugin basenames.
	 * @return string[] Plugin names.
	 */
	private function get_plugin_names( array $basenames ): array {
		$installed_plugins = get_plugins();

		return array_map(
			static function ( string $basename ) use ( $installed_plugins ): string {
				$name = $installed_plugins[ $basename ]['Name'] ?? '';

				return ( is_string( $name ) && '' !== $name ) ? $name : $basename;
			},
			$basenames
		);
	}
}
