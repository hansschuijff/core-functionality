<?php
/**
 * Festinger Vault Verification Guard.
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

use function admin_url;
use function __;

/**
 * Class FestingerVault
 *
 * Represents Festinger Vault in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class FestingerVault extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'festinger-vault';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'FV_PLUGIN_VERSION' );
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
				id:          'cf-festinger-vault',
				title:       __( 'Festinger Vault', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=festingervault#/updates' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Festinger Vault', 'core-functionality' ) . ': ' . __( 'Plugin Updates', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-festinger-vault-plugin-updates',
				title:       __( 'Vault Updates', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=festingervault#/updates' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Festinger Vault', 'core-functionality' ) . ': ' . __( 'Plugin Updates', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Updates', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-festinger-vault-plugins',
				title:       __( 'Vault Plugins', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=festingervault#/item/wordpress-plugins' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Festinger Vault', 'core-functionality' ) . ': ' . __( 'Plugins', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Plugins', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-festinger-vault-themes',
				title:       __( 'Vault Themes', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=festingervault#/item/wordpress-themes' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Festinger Vault', 'core-functionality' ) . ': ' . __( 'Themes', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Themes', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-festinger-vault-elementor-template-kits',
				title:       __( 'Elementor Template Kits', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=festingervault#/item/elementor-template-kits' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Festinger Vault', 'core-functionality' ) . ': ' . __( 'Elementor Template Kits', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-festinger-vault-history',
				title:       __( 'Vault History', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=festingervault#/history' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Festinger Vault', 'core-functionality' ) . ': ' . __( 'History', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'History', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-festinger-vault-settings',
				title:       __( 'Vault Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=festingervault#/settings' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Festinger Vault', 'core-functionality' ) . ': ' . __( 'Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-festinger-vault-support',
				title:       __( 'Vault Support', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=festingervault#/need-help' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Festinger Vault', 'core-functionality' ) . ': ' . __( 'support', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Support', 'core-functionality' ),
			),
		);
	}
}
