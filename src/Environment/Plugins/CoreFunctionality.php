<?php
/**
 * Core Functionality Toolbar Node Provider.
 *
 * @package    DeWittePrins\CoreFunctionality
 * @subpackage Environment\Plugins
 * @author     Hans Schuijff <@hansschuijff>
 * @license    GPL-2.0
 * @since      1.0.0
 */

declare(strict_types=1);

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Admin\Services\Settings\AdminSettingsPage;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\Environment\Plugins\Interfaces\PluginInterface;
use Override;

use function __;
use function admin_url;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CoreFunctionality
 *
 * The guard of this plugin itself, which supplies its admin toolbar nodes, such as the link to its
 * settings page. The plugin is always active when it runs, so the guard is always ready and needs
 * no entry in environment-index.php; it does not extend AbstractPlugin, which looks the plugin up
 * there. Nothing depends on it, but if something ever names it as a dependency, it answers true.
 *
 * @since 1.0.0
 */
class CoreFunctionality implements PluginInterface {

	/**
	 * The guard id (the class name in kebab-case).
	 *
	 * @since  1.0.0
	 * @return string The id.
	 */
	#[Override]
	public function get_id(): string {
		return 'core-functionality';
	}

	/**
	 * Returns the post types this plugin registers (none).
	 *
	 * @since  1.0.0
	 * @return string[] Empty array.
	 */
	#[Override]
	public function get_post_types(): array {
		return array();
	}

	/**
	 * Always ready: this plugin runs.
	 *
	 * @since  1.0.0
	 * @return bool Always true.
	 */
	#[Override]
	public function is_ready(): bool {
		return true;
	}

	/**
	 * Always alive: this plugin runs.
	 *
	 * @since  1.0.0
	 * @return bool Always true.
	 */
	#[Override]
	public function has_proof_of_life(): bool {
		return true;
	}

	/**
	 * The plugin is installed: it is running.
	 *
	 * @since  1.0.0
	 * @return bool Always true.
	 */
	#[Override]
	public function is_installed(): bool {
		return true;
	}

	/**
	 * Returns the name of this plugin.
	 *
	 * @since  1.0.0
	 * @return string The name.
	 */
	#[Override]
	public function get_name(): string {
		return 'Core Functionality';
	}

	/**
	 * Returns the toolbar shortcuts of this plugin.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The shortcuts.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:          'cf-core-functionality-settings',
				title:       __( 'Core Functionality', 'core-functionality' ),
				href:        admin_url( 'options-general.php?page=' . AdminSettingsPage::SLUG ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Core functionality settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
