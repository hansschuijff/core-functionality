<?php
/**
 * Spectra Legacy Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
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
 * Class SpectraLegacy
 *
 * Reports whether Spectra Legacy (formerly Ultimate Addons for Gutenberg) is active and started, and supplies the links of its admin menu.
 *
 * @since 1.0.0
 */
class SpectraLegacy extends AbstractPlugin {

	/**
	 * Returns the environment id of Spectra Legacy.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'spectra-legacy';
	}

	/**
	 * Returns what must exist to prove Spectra Legacy has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// Loaded only when the PHP and WordPress requirements are met (the plugin was Ultimate Addons for Gutenberg).
		return new ProofRequirement( classes: 'UAGB_Loader' );
	}

	/**
	 * Returns the admin toolbar links Spectra Legacy offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		// The menu head first, then the plugin's own admin menu.
		return array(
			new ToolbarShortcut(
				id:          'cf-spectra-legacy',
				title:       'Spectra Legacy',
				href:        admin_url( 'admin.php?page=spectra' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Legacy Dashboard', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-spectra-legacy-dashboard',
				title:       __( 'Spectra Legacy Dashboard', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Legacy Dashboard', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Dashboard', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-legacy-blocks',
				title:       __( 'Spectra Legacy Blocks', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra&path=blocks' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Legacy Blocks', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Blocks', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-legacy-popups',
				title:       __( 'Spectra Legacy Popup Builder', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=spectra-popup' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Legacy Popup Builder', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Popup Builder', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-legacy-ai-features',
				title:       __( 'Spectra Legacy AI Features', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra&path=ai-features' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Legacy AI Features', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'AI Features', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-legacy-settings',
				title:       __( 'Spectra Legacy Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra&path=settings' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Legacy Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-legacy-learn',
				title:       __( 'Learn Spectra Legacy', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra&path=learn' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Legacy Learn', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Learn', 'core-functionality' ),
			),
		);
	}
}
