<?php
/**
 * Quick Featured Images Verification Guard.
 *
 * @package DeWittePrins\CoreFunctionality\Environment\Plugins
 * @author  Hans Schuijff <@hansschuijff>
 * @license GPL-2.0
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace DeWittePrins\CoreFunctionality\Environment\Plugins;

use DeWittePrins\CoreFunctionality\DTO\ToolbarShortcut;
use DeWittePrins\CoreFunctionality\Environment\DTO\ProofRequirement;
use Override;

use function __;

/**
 * Class QuickFeaturedImages
 *
 * Represents Quick Featured Images in the environment: proves it is active and supplies its admin links.
 *
 * @since 1.0.0
 */
class QuickFeaturedImages extends AbstractPlugin {

	/**
	 * Returns the environment id of this plugin or theme.
	 *
	 * @since  1.0.0
	 * @return string The id, also the key in environment-index.php.
	 */
	#[Override]
	public function get_id(): string {
		return 'quick-featured-images';
	}

	/**
	 * Returns what must exist to prove the plugin has started.
	 *
	 * @since  1.0.0
	 * @return ProofRequirement The proof criteria.
	 */
	#[Override]
	protected function get_proof_requirements(): ProofRequirement {
		return new ProofRequirement( constants: 'QFI_ROOT' );
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
			ToolbarShortcut::to_admin_page(
				id:          'cf-quick-featured-images',
				title:       __( 'Featured Images', 'core-functionality' ),
				path:        'admin.php?page=quick-featured-images-overview',
				label:       __( 'Quick Featured Images', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-quick-featured-images-tools',
				title:       __( 'Bulk Edit Featured Images', 'core-functionality' ),
				path:        'admin.php?page=quick-featured-images-tools',
				short_title: __( 'Bulk Edit', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-quick-featured-images-defaults',
				title:       __( 'Default Featured Images', 'core-functionality' ),
				path:        'admin.php?page=quick-featured-images-defaults',
				short_title: __( 'Default Images', 'core-functionality' ),
			),
			ToolbarShortcut::to_admin_page(
				id:          'cf-quick-featured-images-settings',
				title:       __( 'Quick Featured Images Settings', 'core-functionality' ),
				path:        'admin.php?page=quick-featured-images-settings',
				short_title: __( 'Settings', 'core-functionality' ),
			),
		);
	}
}
