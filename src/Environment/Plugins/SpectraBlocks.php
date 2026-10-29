<?php
/**
 * Spectra Blocks Verification Guard.
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
 * Class SpectraBlocks
 *
 * Reports whether Spectra Blocks is active and started, and supplies the links of its admin menu.
 *
 * @since 1.0.0
 */
class SpectraBlocks extends AbstractPlugin {

	/**
	 * Returns the environment id of Spectra Blocks.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'spectra-blocks';
	}

	/**
	 * Returns what must exist to prove Spectra Blocks has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		// Loaded only when the PHP and WordPress requirements are met and no conflicting Spectra 3 is running.
		return new ProofRequirement( classes: 'Spectra_Blocks_Loader' );
	}

	/**
	 * Returns the admin toolbar links Spectra Blocks offers for the quick menu.
	 *
	 * @since  1.0.0
	 * @return ToolbarShortcut[] The toolbar links.
	 */
	#[Override]
	public function get_toolbar_nodes(): array {
		// The menu head first, then the plugin's own admin menu.
		return array(
			new ToolbarShortcut(
				id:          'cf-spectra-blocks',
				title:       'Spectra Blocks',
				href:        admin_url( 'admin.php?page=spectra-blocks' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Blocks Dashboard', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
			new ToolbarShortcut(
				id:          'cf-spectra-blocks-dashboard',
				title:       __( 'Spectra Blocks Dashboard', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra-blocks' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Blocks Dashboard', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Dashboard', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-blocks-ai-features',
				title:       __( 'Spectra Blocks AI Features', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra-blocks&path=ai-features' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Blocks AI Features', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'AI Features', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-blocks-popups',
				title:       __( 'Spectra Blocks Popup Builder', 'core-functionality' ),
				href:        admin_url( 'edit.php?post_type=spectra-blocks-popup' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Blocks Popup Builder', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Popup Builder', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-blocks-learn',
				title:       __( 'Learn Spectra Blocks', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra-blocks&path=learn' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Blocks Learn', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Learn', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-blocks-settings',
				title:       __( 'Spectra Blocks Settings', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra-blocks&path=settings' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Blocks Settings', 'core-functionality' ),
				orientation: Orientation::ADMIN,
				short_title: __( 'Settings', 'core-functionality' ),
			),
			new ToolbarShortcut(
				id:          'cf-spectra-blocks-free-vs-pro',
				title:       __( 'Free vs Pro', 'core-functionality' ),
				href:        admin_url( 'admin.php?page=spectra-blocks&path=free-vs-pro' ),
				label:       __( 'Link to', 'core-functionality' ) . ' ' . __( 'Spectra Blocks Free vs Pro', 'core-functionality' ),
				orientation: Orientation::ADMIN,
			),
		);
	}
}
