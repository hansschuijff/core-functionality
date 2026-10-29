<?php
/**
 * Loco Translate Verification Guard.
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
 * Class LocoTranslate
 *
 * Represents Loco Translate in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class LocoTranslate extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'loco-translate';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( functions: 'loco_plugin_self' );
	}

	/**
	 * Returns the admin toolbar links this plugin offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		// The menu head first, then Loco's own admin menu (the older items keep their ids and titles).
		return array(
			new ToolbarShortcut(
				id:      'cf-loco-translate',
				title:   'Loco Translate',
				href:    admin_url( 'admin.php?page=loco' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Translations', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-loco-translate-home',
				title:   __( 'Translations', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=loco' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Translations', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:      'cf-loco-translate-themes',
				title:   __( 'Theme Translations', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=loco-theme' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Theme Translations', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Themes', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-loco-translate-plugins',
				title:   __( 'Plugin Translations', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=loco-plugin' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Plugin Translations', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Plugins', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-loco-translate-core',
				title:   __( 'WordPress Translations', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=loco-core' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'WordPress Translations', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'WordPress', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-loco-translate-languages',
				title:   __( 'Translation Languages', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=loco-lang' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Loco Translate Languages', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Languages', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:      'cf-loco-translate-settings',
				title:   __( 'Loco Translate Settings', 'core-functionality' ),
				href:    admin_url( 'admin.php?page=loco-config' ),
				label:   __( 'Link to', 'core-functionality' ) . ' ' . __( 'Loco Translate Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
			),
		);
	}
}
