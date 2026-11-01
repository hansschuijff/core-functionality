<?php
/**
 * Git Updater Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;

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
		// The GitHub subtab is the most used page, so several nodes lead there.
		$github_settings = 'options-general.php?page=git-updater&tab=git_updater_settings&subtab=github';

		return array(
			ToolbarShortcut::to_admin_page(
				id:    'cf-git-updater',
				title: __( 'GitHub', 'core-functionality' ),
				path:  $github_settings,
				label: __( 'Git Updater: GitHub settings', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:    'cf-git-updater-main',
				title: __( 'Git Updater', 'core-functionality' ),
				path:  $github_settings,
				label: __( 'Git Updater: GitHub settings', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-git-updater-github-settings',
				title:       __( 'GitHub settings', 'core-functionality' ),
				path:        $github_settings,
				short_title: __( 'Settings', 'core-functionality' ),
				label:       __( 'Git Updater: GitHub settings', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-git-updater-settings',
				title:       __( 'Git Updater Settings', 'core-functionality' ),
				path:        'options-general.php?page=git-updater&tab=git_updater_settings&subtab=git_updater',
				short_title: __( 'Updater settings', 'core-functionality' ),
				label:       __( 'Git Updater: general settings', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:    'cf-git-updater-plugin-install',
				title: __( 'Install plugin', 'core-functionality' ),
				path:  'options-general.php?page=git-updater&tab=git_updater_install_plugin',
				label: __( 'Git Updater: Install Plugin', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:    'cf-git-updater-theme-install',
				title: __( 'Install theme', 'core-functionality' ),
				path:  'options-general.php?page=git-updater&tab=git_updater_install_theme',
				label: __( 'Git Updater: Install Theme', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:    'cf-git-updater-remote-management',
				title: __( 'Remote Management', 'core-functionality' ),
				path:  'options-general.php?page=git-updater&tab=git_updater_remote_management',
				label: __( 'Git Updater: Remote Management', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-git-updater-addons',
				title:       __( 'Git Updater Add-ons', 'core-functionality' ),
				path:        'options-general.php?page=git-updater&tab=git_updater_addons',
				short_title: __( 'Add-ons', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:    'cf-git-updater-additions',
				title: __( 'Extra packages', 'core-functionality' ),
				path:  'options-general.php?page=git-updater&tab=git_updater_additions',
				label: __( 'Git Updater: Additions', 'core-functionality' ),
			),
			// Only used when this site delivers private packages to sites running Git Updater Lite:
			// it lists the client domains that may download them.
			ToolbarShortcut::to_admin_page(
				id:    'cf-git-updater-lite-domains',
				title: __( 'Allowed update clients', 'core-functionality' ),
				path:  'options-general.php?page=git-updater&tab=git_updater_lite_domains',
				label: __( 'Git Updater: Lite Client Domains', 'core-functionality' ),
			),
		);
	}
}
