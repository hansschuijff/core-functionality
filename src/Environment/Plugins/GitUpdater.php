<?php
/**
 * Git Updater Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\Enums\Orientation;
use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;
use function admin_url;

/**
 * Class GitUpdater
 *
 * Represents Git Updater in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class GitUpdater extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'git-updater';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( classes: array( 'Fragen\Git_Updater\Bootstrap' ) );
	}

	/**
	 * Returns the admin toolbar links this plugin offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		return array(
			new ToolbarShortcut(
				id:      'cf-git-updater',
				title:   __( 'Github', 'core-functionality' ),
				href:    admin_url( 'options-general.php?page=git-updater' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Git Updater', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-git-updater-settings',
				title:   __( 'Git Updater Settings', 'core-functionality' ),
				href:    admin_url( 'options-general.php?page=git-updater&tab=git_updater_settings&subtab=github' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Git Updater: Github settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-git-updater-plugin-install',
				title:   __( 'Install plugin', 'core-functionality' ),
				href:    admin_url( 'options-general.php?page=git-updater&tab=git_updater_install_plugin' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Git Updater: Install Plugin', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-git-updater-theme-install',
				title:   __( 'Install theme', 'core-functionality' ),
				href:    admin_url( 'options-general.php?page=git-updater&tab=git_updater_install_theme' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Git Updater: Install Theme', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-git-updater-remote-management',
				title:   __( 'Remote Management', 'core-functionality' ),
				href:    admin_url( 'options-general.php?page=git-updater&tab=git_updater_remote_management' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Git Updater: Remote Management', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
