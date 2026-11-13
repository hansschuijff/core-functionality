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

use function __;
use function add_action;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_values;
use function current_user_can;
use function deactivate_plugins;
use function esc_html;
use function get_current_user_id;
use function get_plugins;
use function get_template;
use function implode;
use function is_array;
use function is_plugin_active;
use function is_string;
use function sprintf;

/**
 * Class GenesisOnlyPlugins
 *
 * Switches off plugins that only work with the Genesis Framework when the active theme is not
 * built on Genesis (such as a block theme). Without Genesis they do nothing at best; some call
 * Genesis functions and could cause fatal errors.
 *
 * It runs right after a theme switch, and on every admin page load as a cheap check (one look at
 * the active plugins), so a Genesis-only plugin that is switched on later is caught as well.
 * An administrator sees a short notice listing what was switched off.
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
			description: __( 'Switches off plugins that only work with Genesis when the active theme is not built on Genesis, and tells the administrator which ones.', 'core-functionality' ),
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
		add_action( 'after_switch_theme', array( $this, 'deactivate_when_genesis_is_absent' ) );
		add_action( 'admin_init', array( $this, 'deactivate_when_genesis_is_absent' ) );
	}

	/**
	 * Switches off the active Genesis-only plugins when the active theme is not built on Genesis.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	public function deactivate_when_genesis_is_absent(): void {
		if ( $this->is_genesis_theme_active() ) {
			return;
		}

		$this->load_plugin_functions();

		$active_plugins = $this->get_active_genesis_only_plugins();
		if ( empty( $active_plugins ) ) {
			return;
		}

		deactivate_plugins( $active_plugins );

		$this->notify_administrator( $active_plugins );
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
	 * Returns the basenames of the plugins that only work with Genesis.
	 *
	 * Plugins that carry "Genesis" in their name but work with any theme (Genesis Blocks, Genesis
	 * Blocks Pro in the folder genesis-page-builder, Genesis Custom Blocks, Genesis Simple FAQ) are
	 * not on the list.
	 *
	 * @since  1.2.0
	 * @return string[] Plugin basenames (folder/file.php).
	 */
	private function get_genesis_only_plugins(): array {
		$default_plugins = array(
			'genesis-dambuster/main.php',
			'genesis-featured-page-advanced/genesis-featured-page-advanced.php',
			'genesis-connect-woocommerce/genesis-connect-woocommerce.php',
			'genesis-connect-sensei-lms/genesis-connect-sensei-lms.php',
			'genesis-enews-extended/plugin.php',
			'genesis-simple-sidebars/plugin.php',
			'display-featured-image-genesis/display-featured-image-genesis.php',
			'featured-videos-for-genesis/genesis-featured-video.php',
			'genesis-admin-bar-plus/genesis-admin-bar-plus.php',
		);

		/**
		 * Filters the plugins that are switched off when the active theme is not built on Genesis.
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
	 * Tells an administrator which plugins were switched off and why.
	 *
	 * Without a logged-in user (WP-CLI, cron) there is nobody to tell.
	 *
	 * @since  1.2.0
	 * @param  string[] $deactivated_plugins Plugin basenames.
	 * @return void
	 */
	private function notify_administrator( array $deactivated_plugins ): void {
		if ( 0 === get_current_user_id() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$plugin_names = implode( ', ', $this->get_plugin_names( $deactivated_plugins ) );

		$message = sprintf(
			/* translators: %s: comma-separated plugin names. */
			__( 'The active theme is not built on Genesis, so these Genesis-only plugins were switched off: %s.', 'core-functionality' ),
			$plugin_names
		);

		$this->plugin->notices->add( 'info', esc_html( $message ) );
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
